<?php

namespace Database\Seeders;

use App\Modules\ChallengeTypes\Generators\WordSearchBuilder;
use App\Modules\ChallengeTypes\Handlers\WordPuzzle;
use App\Modules\Content\Actions\SaveContentItem;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Word puzzle content (`word_puzzle`, published, source "seed") from data/words.php:
 *
 * - `anagrams`: every word, letters scrambled deterministically; other list words made of the
 *   same letters are accepted too.
 * - `missing-letter`: every word with 1–3 letters hidden (more for higher grades).
 * - `word-search`: 3 puzzles per theme and grade (6×6 / 4 words reading right and down for grade 1,
 *   8×8 / 6 words with diagonals for grade 2, 10×10 / 7 words in all 8 directions for grade 3).
 *   Grids are built from a stable seed here and stored in the payload; placements in the answer.
 *
 * Grade → difficulty 1–2 / 3–4 / 5–6 (min age via ContentSeeder::minAgeFor). Stable `source_ref`s,
 * so re-running updates instead of duplicating.
 *
 * Word puzzles are language-bound (`cross_locale` = false): English words from data/words.php
 * (locale "en"), and French words from data/words_fr.php when present (same shape, uppercase
 * A–Z without accents, French theme names; locale "fr", refs prefixed "fr:").
 */
class WordPuzzleSeeder extends Seeder
{
    /** grade => [grid size, word count, direction level, difficulty] */
    private const array WORD_SEARCH = [
        1 => [6, 4, 'easy', 2],
        2 => [8, 6, 'medium', 4],
        3 => [10, 7, 'hard', 6],
    ];

    public string $frenchWordsPath = __DIR__.'/data/words_fr.php';

    /** @var array<string, int> */
    private array $categoryIds = [];

    /** @var array<string, ContentItem> existing seed items by source_ref */
    private array $existing = [];

    public function __construct(private readonly SaveContentItem $save, private readonly WordSearchBuilder $builder) {}

    public function run(): void
    {
        $this->categoryIds = Category::query()->pluck('id', 'slug')->map(fn ($id) => (int) $id)->all();
        $this->existing = ContentItem::query()->where('source_ref', 'like', 'seed:%')->get()->keyBy('source_ref')->all();

        /** @var array<string, array<int, list<array{0: string, 1: string}>>> $themes */
        $themes = require __DIR__.'/data/words.php';
        $french = is_file($this->frenchWordsPath) ? self::validWords(require $this->frenchWordsPath, 'words_fr.php') : null;

        Model::withoutEvents(function () use ($themes, $french): void {
            $this->seedAnagramsAndMissingLetters($themes, 'en');
            $this->seedWordSearches($themes, 'en');

            if ($french !== null) {
                $this->seedAnagramsAndMissingLetters($french, 'fr');
                $this->seedWordSearches($french, 'fr');
            }
        });
    }

    /**
     * @return array<string, array<int, list<array{0: string, 1: string}>>>
     */
    public static function validWords(mixed $themes, string $file): array
    {
        if (! is_array($themes)) {
            throw new \RuntimeException("{$file} must return theme => grade => [[WORD, hint]].");
        }

        foreach ($themes as $theme => $grades) {
            if (! is_string($theme) || trim($theme) === '' || ! is_array($grades)) {
                throw new \RuntimeException("{$file}: theme [{$theme}] must be a name with grades.");
            }

            foreach ($grades as $grade => $words) {
                if (! isset(self::WORD_SEARCH[$grade]) || ! is_array($words)) {
                    throw new \RuntimeException("{$file}: [{$theme}] grade [{$grade}] must be 1, 2 or 3 with a list of words.");
                }

                foreach ($words as $pair) {
                    if (! is_array($pair) || ! is_string($pair[0] ?? null) || ! preg_match('/^[A-Z]{2,}$/', $pair[0])
                        || ! is_string($pair[1] ?? null) || trim($pair[1]) === '') {
                        throw new \RuntimeException("{$file}: [{$theme}] grade [{$grade}] has an invalid entry (".json_encode($pair).'); words are uppercase A-Z without accents, with a hint.');
                    }
                }
            }
        }

        /** @var array<string, array<int, list<array{0: string, 1: string}>>> $themes */
        return $themes;
    }

    /**
     * @param  array<string, array<int, list<array{0: string, 1: string}>>>  $themes
     */
    private function seedAnagramsAndMissingLetters(array $themes, string $locale): void
    {
        $byLetters = [];

        foreach ($themes as $grades) {
            foreach ($grades as $words) {
                foreach ($words as [$word]) {
                    $byLetters[$this->sortedLetters($word)][$word] = true;
                }
            }
        }

        $seen = [];

        foreach ($themes as $grades) {
            foreach ($grades as $grade => $words) {
                foreach ($words as [$word, $hint]) {
                    if (isset($seen[$word])) {
                        continue;
                    }

                    $seen[$word] = true;
                    $difficulty = ($grade * 2 - 1) + (strlen($word) >= [1 => 4, 2 => 6, 3 => 9][$grade] ? 1 : 0);
                    $alternatives = array_keys($byLetters[$this->sortedLetters($word)]);

                    $this->store('anagrams', $difficulty, [
                        'variant' => 'anagram',
                        'letters' => WordPuzzle::scramble($word, $word),
                        'hint' => $hint,
                    ], ['words' => array_values(array_unique([$word, ...$alternatives]))], "anagram:{$word}", $locale);

                    $hidden = match ($grade) {
                        1 => 1,
                        2 => strlen($word) >= 5 ? 2 : 1,
                        default => strlen($word) >= 8 ? 3 : 2,
                    };

                    $this->store('missing-letter', $difficulty, [
                        'variant' => 'missing_letter',
                        'pattern' => WordPuzzle::hideLetters($word, $hidden, $word),
                        'hint' => $hint,
                    ], ['word' => $word], "missing:{$word}", $locale);
                }
            }
        }
    }

    /**
     * @param  array<string, array<int, list<array{0: string, 1: string}>>>  $themes
     */
    private function seedWordSearches(array $themes, string $locale): void
    {
        foreach ($themes as $theme => $grades) {
            foreach ($grades as $grade => $words) {
                [$size, $count, $level, $difficulty] = self::WORD_SEARCH[$grade];
                $list = array_values(array_filter(array_column($words, 0), fn (string $w) => strlen($w) <= $size));

                for ($n = 1; $n <= 3; $n++) {
                    $ref = ($locale === 'en' ? '' : "{$locale}:")."wordsearch:{$theme}:{$grade}:{$n}";
                    mt_srand(crc32($ref));
                    shuffle($list);
                    mt_srand();
                    $picked = array_slice($list, 0, $count);
                    $built = $this->builder->build($picked, $size, WordSearchBuilder::LEVELS[$level], $ref);

                    $this->store('word-search', $difficulty, [
                        'variant' => 'word_search',
                        'grid' => $built['grid'],
                        'words' => array_column($built['found'], 'word'),
                        'hint' => $theme,
                    ], ['found' => $built['found']], $ref, $locale, prefixed: true);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $answer
     */
    private function store(string $slug, int $difficulty, array $payload, array $answer, string $ref, string $locale, bool $prefixed = false): void
    {
        $categoryId = $this->categoryIds[$slug] ?? throw new \RuntimeException("Unknown category slug [{$slug}].");
        $sourceRef = 'seed:'.($prefixed || $locale === 'en' ? '' : "{$locale}:").$ref;

        $this->existing[$sourceRef] = $this->save->handle([
            'category_id' => $categoryId,
            'type_key' => 'word_puzzle',
            'locale' => $locale,
            'translation_group' => null,
            'cross_locale' => false,
            'difficulty' => $difficulty,
            'min_age' => ContentSeeder::minAgeFor($difficulty),
            'max_age' => null,
            'payload' => $payload,
            'answer' => $answer,
            'status' => ContentItem::STATUS_PUBLISHED,
            'source' => 'seed',
            'source_ref' => $sourceRef,
        ], $this->existing[$sourceRef] ?? null);
    }

    private function sortedLetters(string $word): string
    {
        $letters = str_split($word);
        sort($letters);

        return implode('', $letters);
    }
}
