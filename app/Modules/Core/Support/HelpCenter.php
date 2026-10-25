<?php

namespace App\Modules\Core\Support;

use App\Modules\Billing\Support\PaddleConfig;
use App\Modules\Policy\PolicyResolver;
use App\Modules\Policy\Support\ThemeWorldCatalog;
use Illuminate\Support\HtmlString;
use Normalizer;

/**
 * The "How to play" help center (`/help`, docs/PLAN.md "How to play" help center (2026-10-24)).
 *
 * Content is structured data in `resources/help/{en,fr}.php` (sections → articles), plain text
 * only. `{name}` placeholders are filled from the same settings, config and world catalog the app
 * uses, so the numbers on the page are the app's numbers; a step or tip whose placeholder is empty
 * (e.g. no streak worlds) is left out. Everything is escaped when rendered; `**label**` becomes
 * bold after escaping (see inline()).
 *
 * Search (`?q=`, also mirrored by public/js/help-search.js): text is folded to lowercase ASCII
 * (accents removed), stop words are dropped and a plural "s" is trimmed; an article matches when
 * every remaining term appears in its title, summary, steps, tip or keywords. When none does,
 * articles matching at least one term are returned, best first.
 */
class HelpCenter
{
    /** Longest query the search looks at (characters) and most terms it uses. */
    public const int MAX_QUERY_LENGTH = 100;

    public const int MAX_TERMS = 8;

    /** Words ignored by the search (folded like the content: lowercase, no accents). */
    public const array STOP_WORDS = [
        'en' => ['a', 'an', 'and', 'are', 'at', 'be', 'can', 'do', 'does', 'for', 'from', 'get', 'how', 'i', 'in', 'is', 'it', 'me', 'my', 'of', 'on', 'or', 'our', 'the', 'to', 'what', 'when', 'where', 'why', 'with', 'you', 'your'],
        'fr' => ['a', 'au', 'aux', 'ce', 'comment', 'd', 'de', 'des', 'du', 'en', 'est', 'et', 'il', 'j', 'je', 'l', 'la', 'le', 'les', 'ma', 'mes', 'mon', 'ne', 'on', 'ou', 'par', 'pas', 'pour', 'pourquoi', 'quand', 'que', 'qui', 'quoi', 's', 'sa', 'se', 'ses', 'son', 'sur', 'ta', 'te', 'tes', 'ton', 'un', 'une', 'vos', 'votre', 'vous'],
    ];

    /** World names as in the app (`appearance.themes`), en and fr. */
    private const array WORLD_NAMES = [
        'playful' => ['Candy Land', 'Pays des bonbons'],
        'sleek' => ['Glass', 'Verre'],
        'modern' => ['Modern', 'Moderne'],
        'classic' => ['Classic', 'Classique'],
        'jungle' => ['Jungle Adventure', 'Aventure dans la jungle'],
        'space' => ['Space Explorers', 'Explorateurs de l’espace'],
        'neon' => ['Neon Arcade', 'Arcade néon'],
        'sea' => ['Under the Sea', 'Sous la mer'],
        'dino' => ['Dino Valley', 'Vallée des dinos'],
        'sticker' => ['Sticker Street', 'Rue des stickers'],
        'lofi' => ['Lo-fi Dusk', 'Crépuscule lo-fi'],
        'nature' => ['Calm Nature', 'Nature paisible'],
        'midnight' => ['Midnight', 'Minuit'],
    ];

    /** Named routes an article may link to (`links`). */
    private const array LINK_ROUTES = ['website.support', 'website.partners', 'legal.privacy', 'legal.terms', 'account.delete'];

    public function __construct(private readonly PolicyResolver $policy, private readonly ThemeWorldCatalog $worlds) {}

    /**
     * The sections of a language with placeholders filled, images and links resolved.
     *
     * @return list<array{id: string, title: string, summary: string, icon: string, articles: list<array{id: string, title: string, summary: string, steps: list<string>, tip: string|null, image: array{src: string, width: int, height: int, alt: string}|null, links: list<array{url: string, label: string}>, keywords: list<string>}>}>
     */
    public function sections(string $locale): array
    {
        $values = $this->placeholders($locale);
        $images = self::images();
        $lang = ['lang' => $locale];
        $sections = [];

        foreach (self::content($locale) as $section) {
            $articles = [];

            foreach ($section['articles'] as $article) {
                $image = null;

                if (isset($article['image'])) {
                    [$path, $width, $height] = $images[$article['image']];
                    $image = ['src' => '/'.$path, 'width' => $width, 'height' => $height, 'alt' => (string) ($article['alt'] ?? '')];
                }

                $articles[] = [
                    'id' => $article['id'],
                    'title' => $article['title'],
                    'summary' => $article['summary'],
                    'steps' => array_values(array_filter(array_map(fn (string $step): ?string => $this->fill($step, $values), $article['steps']))),
                    'tip' => isset($article['tip']) ? $this->fill($article['tip'], $values) : null,
                    'image' => $image,
                    'links' => array_map(fn (array $link): array => [
                        'url' => in_array($link['route'], self::LINK_ROUTES, true) ? route($link['route'], $lang) : route('website.support', $lang),
                        'label' => $link['label'],
                    ], $article['links'] ?? []),
                    'keywords' => $article['keywords'] ?? [],
                ];
            }

            $sections[] = [
                'id' => $section['id'],
                'title' => $section['title'],
                'summary' => $section['summary'],
                'icon' => '/images/site/'.$section['icon'],
                'articles' => $articles,
            ];
        }

        return $sections;
    }

    /**
     * Ids of the articles matching a query, best first (see the class comment). Empty when the
     * query has no searchable term.
     *
     * @param  list<array{articles: list<array{id: string, title: string, summary: string, steps: list<string>, tip: string|null, keywords: list<string>}>}>  $sections
     * @return list<string>
     */
    public function search(array $sections, string $query, string $locale): array
    {
        $terms = self::terms($query, $locale);

        if ($terms === []) {
            return [];
        }

        $all = [];
        $some = [];

        foreach ($sections as $section) {
            foreach ($section['articles'] as $article) {
                $haystack = self::fold(implode(' ', [
                    $article['title'], $article['summary'], ...$article['steps'], $article['tip'] ?? '', ...$article['keywords'],
                ]));
                $title = self::fold($article['title']);
                $hits = count(array_filter($terms, fn (string $term): bool => str_contains($haystack, $term)));
                $titleHits = count(array_filter($terms, fn (string $term): bool => str_contains($title, $term)));

                if ($hits === count($terms)) {
                    $all[$article['id']] = $titleHits;
                } elseif ($hits > 0) {
                    $some[$article['id']] = $hits * 10 + $titleHits;
                }
            }
        }

        $ranked = $all !== [] ? $all : $some;
        // Stable: equal scores keep the page order.
        $order = array_flip(array_keys($ranked));
        uksort($ranked, fn (string $a, string $b): int => [$ranked[$b], $order[$a]] <=> [$ranked[$a], $order[$b]]);

        return array_map('strval', array_keys($ranked));
    }

    /**
     * The search terms of a query: folded, without stop words and one-letter words, a trailing
     * plural "s" trimmed from words longer than 3 letters, at most MAX_TERMS.
     *
     * @return list<string>
     */
    public static function terms(string $query, string $locale): array
    {
        $words = preg_split('/\s+/', self::fold(mb_substr($query, 0, self::MAX_QUERY_LENGTH)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = self::STOP_WORDS[$locale] ?? self::STOP_WORDS['en'];
        $terms = [];

        foreach ($words as $word) {
            if (strlen($word) < 2 || in_array($word, $stop, true)) {
                continue;
            }

            $terms[] = strlen($word) > 3 && str_ends_with($word, 's') ? substr($word, 0, -1) : $word;
        }

        return array_slice(array_values(array_unique($terms)), 0, self::MAX_TERMS);
    }

    /**
     * Lowercase ASCII words separated by single spaces: accents removed (NFD), œ/æ/ß spelled out,
     * every other character a space. help-search.js folds the same way.
     */
    public static function fold(string $text): string
    {
        $text = str_replace(['**', 'œ', 'Œ', 'æ', 'Æ', 'ß'], [' ', 'oe', 'oe', 'ae', 'ae', 'ss'], $text);
        $text = (string) preg_replace('/\p{Mn}+/u', '', (string) Normalizer::normalize($text, Normalizer::FORM_D));

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($text)));
    }

    /**
     * Escaped text with `**label**` turned into <strong>label</strong>.
     */
    public static function inline(string $text): HtmlString
    {
        return new HtmlString((string) preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', e($text)));
    }

    /**
     * Text without the `**` markers (meta descriptions, search index attributes).
     */
    public static function plain(string $text): string
    {
        return str_replace('**', '', $text);
    }

    /**
     * Raw content of a language (`resources/help/{locale}.php`).
     *
     * @return list<array{id: string, title: string, summary: string, icon: string, articles: list<array{id: string, title: string, summary: string, steps: list<string>, tip?: string, image?: string, alt?: string, links?: list<array{route: string, label: string}>, keywords?: list<string>}>}>
     */
    public static function content(string $locale): array
    {
        /** @var list<array{id: string, title: string, summary: string, icon: string, articles: list<array{id: string, title: string, summary: string, steps: list<string>, tip?: string, image?: string, alt?: string, links?: list<array{route: string, label: string}>, keywords?: list<string>}>}> $content */
        $content = require resource_path('help/'.(in_array($locale, ['en', 'fr'], true) ? $locale : 'en').'.php');

        return $content;
    }

    /**
     * Screenshot registry: key => [path under public/, width, height].
     *
     * @return array<string, array{0: string, 1: int, 2: int}>
     */
    public static function images(): array
    {
        /** @var array<string, array{0: string, 1: int, 2: int}> $images */
        $images = require resource_path('help/images.php');

        return $images;
    }

    /**
     * Placeholder values for a language, from the live settings, config and world catalog.
     *
     * @return array<string, string>
     */
    public function placeholders(string $locale): array
    {
        $fr = $locale === 'fr';
        $lockout = (array) config('auth.lockout.email_ip.durations', [300, 3600, 86400]);
        $byUnlock = ['free' => [], 'points' => [], 'streak' => [], 'family_plan' => []];

        foreach ($this->worlds->all() as $key => $world) {
            $name = self::WORLD_NAMES[$key][$fr ? 1 : 0] ?? ucfirst($key);
            $byUnlock[$world['unlock_type']][] = match ($world['unlock_type']) {
                'points' => $name.' ('.$world['required'].' points)',
                'streak' => $name.' ('.($fr ? $world['required'].' jours' : $world['required'].' days').')',
                default => $name,
            };
        }

        return [
            'invite_days' => (string) $this->policy->inviteTtlDays(),
            'code_minutes' => (string) $this->policy->signInCodeTtlMinutes(),
            'target' => (string) max(1, (int) $this->policy->setting('play.target_points', 'free')),
            'lives' => (string) max(1, (int) ceil(max(1, (int) $this->policy->setting('play.target_points', 'free')) * (int) $this->policy->setting('play.blunder_cap_pct', 'free') / 100)),
            'redemption_items' => (string) max(1, (int) $this->policy->setting('play.redemption_items', 'free')),
            'free_members' => (string) (int) $this->policy->setting('limits.max_members', 'free'),
            'family_members' => (string) (int) $this->policy->setting('limits.max_members', 'family'),
            'free_categories' => (string) (int) $this->policy->setting('limits.max_categories', 'free'),
            'free_goals' => (string) ($this->policy->maxActiveGoalsFor('free') ?? ''),
            'family_second_chances' => (string) (int) $this->policy->setting('second_chance.free_per_day', 'family'),
            'chat_free' => self::duration($this->policy->chatRetentionDays('free') * 86400, $fr),
            'chat_family' => self::duration($this->policy->chatRetentionDays('family') * 86400, $fr),
            'worlds_free' => self::sentenceList($byUnlock['free'], $fr),
            'worlds_points' => self::sentenceList($byUnlock['points'], $fr),
            'worlds_streak' => self::sentenceList($byUnlock['streak'], $fr),
            'worlds_family' => self::sentenceList($byUnlock['family_plan'], $fr),
            'lock_attempts' => (string) (int) config('auth.lockout.email_ip.attempts', 5),
            'lock_first' => self::duration((int) ($lockout[0] ?? 300), $fr),
            'lock_second' => self::duration((int) ($lockout[1] ?? 3600), $fr),
            'lock_third' => self::duration((int) ($lockout[2] ?? 86400), $fr),
            'reset_minutes' => (string) (int) config('auth.passwords.users.expire', 60),
            // Empty (the step is dropped) until web checkout (Paddle) is configured.
            'web_checkout' => PaddleConfig::webCheckoutAvailable() ? Brand::domain().'/subscribe' : '',
        ];
    }

    /**
     * The text with its placeholders filled, or null when one of them is empty.
     *
     * @param  array<string, string>  $values
     */
    private function fill(string $text, array $values): ?string
    {
        $empty = false;
        $filled = (string) preg_replace_callback('/\{([a-z_]+)\}/', function (array $match) use ($values, &$empty): string {
            $value = $values[$match[1]] ?? '';
            $empty = $empty || $value === '';

            return $value;
        }, $text);

        return $empty ? null : $filled;
    }

    /**
     * "5 minutes", "1 hour", "24 hours", "7 days", "1 year" (fr: "1 heure", "1 an"...).
     */
    private static function duration(int $seconds, bool $fr): string
    {
        [$count, $en, $frUnit] = match (true) {
            $seconds >= 86400 * 365 && $seconds % (86400 * 365) === 0 => [intdiv($seconds, 86400 * 365), 'year', 'an'],
            $seconds >= 86400 * 2 && $seconds % 86400 === 0 => [intdiv($seconds, 86400), 'day', 'jour'],
            $seconds >= 3600 && $seconds % 3600 === 0 => [intdiv($seconds, 3600), 'hour', 'heure'],
            default => [max(1, intdiv($seconds, 60)), 'minute', 'minute'],
        };

        return $count.' '.($fr ? $frUnit : $en).($count > 1 ? 's' : '');
    }

    /**
     * "A, B and C" / "A, B et C"; empty string for an empty list.
     *
     * @param  list<string>  $items
     */
    private static function sentenceList(array $items, bool $fr): string
    {
        if (count($items) < 2) {
            return $items[0] ?? '';
        }

        $last = array_pop($items);

        return implode(', ', $items).($fr ? ' et ' : ' and ').$last;
    }
}
