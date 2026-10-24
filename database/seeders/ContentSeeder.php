<?php

namespace Database\Seeders;

use App\Modules\Content\Actions\SaveContentItem;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Launch content for the two first challenge types (all published, source "seed"):
 *
 * - Multiple choice: hand-written items (data/multiple_choice.php) plus items generated from
 *   data/countries.php (capitals, continents), times tables, mental math and anagrams.
 * - Wisdom cards: public-domain quotes and original cards (data/wisdom_cards.php).
 * - The launch question bank (data/questions/*.php, see its README): multiple choice and wisdom
 *   cards in English and/or French, validated strictly by QuestionBank.
 * - French versions of generated items: capitals and continents from data/countries_fr.php
 *   (when present), times tables and mental math.
 *
 * Every item has a stable `source_ref`, so re-running updates instead of duplicating. Translations
 * share a `translation_group` (the English ref without "seed:", or `q:<id>` for the question bank);
 * English items of language-bound categories are `cross_locale` = false (docs/PLAN.md "Launch
 * question bank and French content"). Choice order is shuffled deterministically per item.
 */
class ContentSeeder extends Seeder
{
    private const array CONTINENTS = ['Europe', 'Asia', 'Africa', 'North America', 'South America', 'Oceania'];

    /** English continent => French name. */
    public const array CONTINENTS_FR = [
        'Europe' => 'Europe', 'Asia' => 'Asie', 'Africa' => 'Afrique',
        'North America' => 'Amérique du Nord', 'South America' => 'Amérique du Sud', 'Oceania' => 'Océanie',
    ];

    /**
     * Categories whose items depend on the English language (words, spelling, idioms): their
     * English items are never offered to members of another locale.
     */
    public const array LANGUAGE_BOUND_CATEGORIES = [
        'anagrams', 'missing-letter', 'word-search', 'spelling', 'idioms', 'synonyms', 'grades-1-3', 'grades-4-6', 'grades-7-9',
        'everyday-words', 'numbers-colours', 'phrases',
    ];

    public const array WISDOM_QUESTION = ['en' => 'What is the main idea?', 'fr' => "Quelle est l'idée principale ?"];

    public string $questionsPath = __DIR__.'/data/questions';

    public string $countriesFrPath = __DIR__.'/data/countries_fr.php';

    /** @var array<string, int> */
    private array $categoryIds = [];

    /** @var array<string, ContentItem> existing seed items by source_ref */
    private array $existing = [];

    public function __construct(private readonly SaveContentItem $save) {}

    public function run(): void
    {
        $this->prepare();

        // No audit-log rows for seed data.
        Model::withoutEvents(function (): void {
            $this->seedHandWritten();
            $this->seedGeography();
            $this->seedFrenchGeography();
            $this->seedTimesTables();
            $this->seedMentalMath();
            $this->seedAnagrams();
            $this->seedWisdomCards();
            $this->seedQuestionBank();
        });
    }

    /**
     * Seeds only the question bank (used by tests with fixture files).
     */
    public function runQuestionBank(): void
    {
        $this->prepare();
        Model::withoutEvents(fn () => $this->seedQuestionBank());
    }

    /**
     * Seeds only the French generated geography (used by tests with a fixture file).
     */
    public function runFrenchGeography(): void
    {
        $this->prepare();
        Model::withoutEvents(fn () => $this->seedFrenchGeography());
    }

    /**
     * Leaf category slugs (categories without children), the only valid question bank categories.
     *
     * @return list<string>
     */
    public static function leafCategorySlugs(): array
    {
        $parents = Category::query()->whereNotNull('parent_id')->distinct()->pluck('parent_id')->all();

        return Category::query()->whereNotIn('id', $parents)->orderBy('slug')->pluck('slug')->all();
    }

    private function prepare(): void
    {
        $this->categoryIds = Category::query()->pluck('id', 'slug')->map(fn ($id) => (int) $id)->all();
        $this->existing = ContentItem::query()->where('source_ref', 'like', 'seed:%')->get()->keyBy('source_ref')->all();
    }

    private function seedQuestionBank(): void
    {
        foreach (QuestionBank::load($this->questionsPath, self::leafCategorySlugs()) as $entry) {
            $crossLocale = $entry['cross_locale'] ?? ! in_array($entry['category'], self::LANGUAGE_BOUND_CATEGORIES, true);

            foreach ($entry['locales'] as $locale => $block) {
                $ref = "q:{$entry['id']}:{$locale}";
                [$choices, $answerId] = $this->shuffledChoices($block['answer'], $block['wrong'], $ref);

                $payload = $entry['type'] === 'wisdom_card'
                    ? ['card' => ['text' => $block['text'], 'author' => $block['author'], 'source' => $block['source']], 'question' => self::WISDOM_QUESTION[$locale], 'choices' => $choices]
                    : ['question' => $block['question'], 'choices' => $choices, 'image_url' => null];

                $this->store($entry['category'], $entry['type'], $entry['difficulty'], $payload,
                    ['choice_id' => $answerId, 'explanation' => $block['explanation']], $ref, [
                        'locale' => $locale,
                        'translation_group' => "q:{$entry['id']}",
                        'cross_locale' => $crossLocale,
                        'min_age' => $entry['min_age'] ?? self::minAgeFor($entry['difficulty']),
                    ]);
            }
        }
    }

    /**
     * French capitals and continents for the countries in data/countries_fr.php (optional file),
     * in the same translation groups as the English items. Distractors are French capitals of
     * the same scope / French continent names.
     */
    private function seedFrenchGeography(): void
    {
        if (! is_file($this->countriesFrPath)) {
            return;
        }

        /** @var list<array{0: string, 1: string, 2: string, 3: string, 4: int}> $countries */
        $countries = require __DIR__.'/data/countries.php';
        $french = $this->frenchCountries(array_column($countries, 0));

        foreach ($countries as [$country, , , $scope, $difficulty]) {
            if (! isset($french[$country])) {
                continue;
            }

            $fr = $french[$country];
            $sameScope = [];

            foreach ($countries as $other) {
                $capital = $french[$other[0]]['capital'] ?? null;

                if ($other[3] === $scope && $capital !== null && $capital !== $fr['capital'] && ! in_array($capital, $sameScope, true)) {
                    $sameScope[] = $capital;
                }
            }

            if (count($sameScope) >= 3) {
                $this->multipleChoice($scope, $difficulty,
                    $fr['capital_question'] ?? "Quelle est la capitale de ce pays : {$fr['country']} ?",
                    $fr['capital'], $this->pick($sameScope, 3, "capital:{$country}:fr"), null, "capital:{$country}:fr",
                    ['locale' => 'fr', 'translation_group' => "capital:{$country}"]);
            }

            $otherContinents = array_values(array_diff(array_values(self::CONTINENTS_FR), [$fr['continent']]));
            $this->multipleChoice('continents', max(1, min(10, $difficulty - 1)),
                $fr['continent_question'] ?? "Sur quel continent se trouve ce pays : {$fr['country']} ?",
                $fr['continent'], $this->pick($otherContinents, 3, "continent:{$country}:fr"), null, "continent:{$country}:fr",
                ['locale' => 'fr', 'translation_group' => "continent:{$country}"]);
        }
    }

    /**
     * @param  list<string>  $englishCountries
     * @return array<string, array{country: string, capital: string, continent: string, capital_question: string|null, continent_question: string|null}>
     */
    private function frenchCountries(array $englishCountries): array
    {
        $data = require $this->countriesFrPath;

        if (! is_array($data)) {
            throw new \RuntimeException('countries_fr.php must return an array.');
        }

        $known = array_flip($englishCountries);
        $out = [];

        foreach ($data as $country => $row) {
            if (! is_string($country) || ! isset($known[$country])) {
                throw new \RuntimeException("countries_fr.php: [{$country}] is not a country in countries.php.");
            }

            foreach (['country', 'capital', 'continent'] as $key) {
                if (! is_array($row) || ! is_string($row[$key] ?? null) || trim($row[$key]) === '') {
                    throw new \RuntimeException("countries_fr.php: [{$country}.{$key}] must be a non-empty string.");
                }
            }

            if (! in_array($row['continent'], self::CONTINENTS_FR, true)) {
                throw new \RuntimeException("countries_fr.php: [{$country}] continent [{$row['continent']}] is not one of: ".implode(', ', self::CONTINENTS_FR).'.');
            }

            foreach (['capital_question', 'continent_question'] as $key) {
                if (array_key_exists($key, $row) && (! is_string($row[$key]) || trim($row[$key]) === '')) {
                    throw new \RuntimeException("countries_fr.php: [{$country}.{$key}] must be a non-empty string when present.");
                }
            }

            $out[$country] = [
                'country' => $row['country'],
                'capital' => $row['capital'],
                'continent' => $row['continent'],
                'capital_question' => $row['capital_question'] ?? null,
                'continent_question' => $row['continent_question'] ?? null,
            ];
        }

        return $out;
    }

    private function seedHandWritten(): void
    {
        /** @var list<array{0: string, 1: int, 2: string, 3: string, 4: list<string>, 5: string|null}> $items */
        $items = require __DIR__.'/data/multiple_choice.php';

        foreach ($items as [$slug, $difficulty, $question, $correct, $wrong, $explanation]) {
            $this->multipleChoice($slug, $difficulty, $question, $correct, $wrong, $explanation, 'mc:'.md5($slug.'|'.$question.'|'.$correct),
                ['cross_locale' => ! in_array($slug, self::LANGUAGE_BOUND_CATEGORIES, true)]);
        }
    }

    private function seedGeography(): void
    {
        /** @var list<array{0: string, 1: string, 2: string, 3: string, 4: int}> $countries */
        $countries = require __DIR__.'/data/countries.php';

        foreach ($countries as $index => [$country, $capital, $continent, $scope, $difficulty]) {
            $sameScope = array_values(array_filter($countries, fn ($c) => $c[3] === $scope && $c[1] !== $capital));
            $wrong = $this->pick(array_column($sameScope, 1), 3, "capital:{$country}");

            $this->multipleChoice($scope, $difficulty, "What is the capital of {$country}?", $capital, $wrong, null, "capital:{$country}");

            $otherContinents = array_values(array_diff(self::CONTINENTS, [$continent]));
            $this->multipleChoice(
                'continents',
                max(1, min(10, $difficulty - 1)),
                "On which continent is {$country}?",
                $continent,
                $this->pick($otherContinents, 3, "continent:{$country}"),
                null,
                "continent:{$country}",
            );
        }
    }

    private function seedTimesTables(): void
    {
        $pairs = [[2, 3], [2, 7], [3, 4], [3, 6], [4, 5], [4, 8], [5, 5], [5, 9], [6, 6], [6, 7], [6, 8], [7, 7],
            [7, 8], [7, 9], [8, 8], [8, 9], [9, 9], [3, 12], [4, 11], [6, 12], [7, 12], [8, 12], [9, 11], [11, 12], [12, 12]];

        foreach ($pairs as [$a, $b]) {
            $answer = $a * $b;
            $difficulty = max($a, $b) <= 5 ? 1 : (max($a, $b) <= 9 ? 3 : 5);

            $wrong = $this->numericDistractors($answer, [$answer + $a, $answer - $b, ($a + 1) * $b, $a * ($b - 1)]);

            $this->multipleChoice('times-tables', $difficulty, "What is {$a} × {$b}?", (string) $answer, $wrong, null, "times:{$a}x{$b}");
            $this->multipleChoice('times-tables', $difficulty, "Combien font {$a} × {$b} ?", (string) $answer, $wrong, null, "times:{$a}x{$b}:fr",
                ['locale' => 'fr', 'translation_group' => "times:{$a}x{$b}"]);
        }
    }

    private function seedMentalMath(): void
    {
        $problems = [
            ['14 + 9', 23, 1], ['27 + 15', 42, 2], ['50 - 18', 32, 2], ['36 + 47', 83, 3], ['100 - 37', 63, 3],
            ['15 × 4', 60, 3], ['72 ÷ 8', 9, 3], ['125 + 75', 200, 3], ['90 - 46', 44, 4], ['25 × 6', 150, 4],
            ['144 ÷ 12', 12, 4], ['19 × 3', 57, 4], ['250 - 125', 125, 4], ['48 + 64', 112, 4], ['16 × 5', 80, 4],
            ['81 ÷ 9', 9, 3], ['99 + 99', 198, 5], ['13 × 7', 91, 5], ['1000 - 456', 544, 6], ['35 × 4', 140, 5],
        ];

        foreach ($problems as [$expression, $answer, $difficulty]) {
            $wrong = $this->numericDistractors($answer, [$answer + 10, $answer - 1, $answer + 2, $answer - 10]);

            $this->multipleChoice('mental-math', $difficulty, "What is {$expression}?", (string) $answer, $wrong, null, 'mental:'.$expression);
            $this->multipleChoice('mental-math', $difficulty, "Combien font {$expression} ?", (string) $answer, $wrong, null, "mental:{$expression}:fr",
                ['locale' => 'fr', 'translation_group' => 'mental:'.$expression]);
        }
    }

    private function seedAnagrams(): void
    {
        $words = [
            ['PPLEA', 'APPLE', 1], ['GERTI', 'TIGER', 2], ['SUOHE', 'HOUSE', 2], ['RBAEZ', 'ZEBRA', 2],
            ['NETLAP', 'PLANET', 3], ['GORENA', 'ORANGE', 3], ['NANABA', 'BANANA', 2], ['KEYMON', 'MONKEY', 3],
            ['CILPEN', 'PENCIL', 3], ['DOWNWI', 'WINDOW', 4], ['TRUTLE', 'TURTLE', 4], ['GLEJUN', 'JUNGLE', 4], ['KETROC', 'ROCKET', 4],
        ];
        $answers = array_column($words, 1);

        foreach ($words as [$scrambled, $word, $difficulty]) {
            $sameLength = array_values(array_filter($answers, fn ($w) => strlen($w) === strlen($word) && $w !== $word));
            $this->multipleChoice('anagrams', $difficulty, "Unscramble the letters: {$scrambled}", $word,
                $this->pick($sameLength, 3, "anagram:{$word}"), null, "anagram:{$word}", ['cross_locale' => false]);
        }
    }

    private function seedWisdomCards(): void
    {
        /** @var list<array{0: string, 1: int, 2: string, 3: string|null, 4: string|null, 5: string, 6: list<string>, 7: string}> $cards */
        $cards = require __DIR__.'/data/wisdom_cards.php';

        foreach ($cards as [$slug, $difficulty, $text, $author, $source, $correct, $wrong, $explanation]) {
            $ref = 'wisdom:'.md5($text);
            [$choices, $answerId] = $this->shuffledChoices($correct, $wrong, $ref);

            $this->store($slug, 'wisdom_card', $difficulty, [
                'card' => ['text' => $text, 'author' => $author, 'source' => $source],
                'question' => 'What is the main idea?',
                'choices' => $choices,
            ], ['choice_id' => $answerId, 'explanation' => $explanation], $ref);
        }
    }

    /**
     * @param  list<string>  $wrong
     * @param  array<string, mixed>  $overrides  see store()
     */
    private function multipleChoice(string $slug, int $difficulty, string $question, string $correct, array $wrong, ?string $explanation, string $ref, array $overrides = []): void
    {
        [$choices, $answerId] = $this->shuffledChoices($correct, $wrong, $ref);

        $this->store($slug, 'multiple_choice', $difficulty, [
            'question' => $question,
            'choices' => $choices,
            'image_url' => null,
        ], ['choice_id' => $answerId, 'explanation' => $explanation], $ref, $overrides);
    }

    /**
     * Defaults: English, `translation_group` = the ref, `cross_locale` true, min age from difficulty.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $answer
     * @param  array<string, mixed>  $overrides  locale, translation_group, cross_locale, min_age
     */
    private function store(string $slug, string $type, int $difficulty, array $payload, array $answer, string $ref, array $overrides = []): void
    {
        $categoryId = $this->categoryIds[$slug] ?? throw new \RuntimeException("Unknown category slug [{$slug}] in seed data.");
        $sourceRef = 'seed:'.$ref;

        $this->existing[$sourceRef] = $this->save->handle([
            'category_id' => $categoryId,
            'type_key' => $type,
            'locale' => 'en',
            'translation_group' => $ref,
            'cross_locale' => true,
            'difficulty' => $difficulty,
            'min_age' => self::minAgeFor($difficulty),
            'max_age' => null,
            'payload' => $payload,
            'answer' => $answer,
            'status' => ContentItem::STATUS_PUBLISHED,
            'source' => 'seed',
            'source_ref' => $sourceRef,
            ...$overrides,
        ], $this->existing[$sourceRef] ?? null);
    }

    /**
     * Rough reading/knowledge age for a difficulty level.
     */
    public static function minAgeFor(int $difficulty): int
    {
        return match (true) {
            $difficulty <= 2 => 5,
            $difficulty <= 4 => 7,
            $difficulty <= 6 => 9,
            default => 11,
        };
    }

    /**
     * @param  list<string>  $wrong
     * @return array{0: list<array{id: string, text: string}>, 1: string}
     */
    private function shuffledChoices(string $correct, array $wrong, string $seed): array
    {
        $texts = array_values(array_unique([$correct, ...$wrong]));
        mt_srand(crc32($seed));
        shuffle($texts);
        mt_srand();

        $choices = [];
        $answerId = 'a';

        foreach ($texts as $i => $text) {
            $id = chr(ord('a') + $i);
            $choices[] = ['id' => $id, 'text' => $text];

            if ($text === $correct) {
                $answerId = $id;
            }
        }

        return [$choices, $answerId];
    }

    /**
     * Deterministically pick $count values.
     *
     * @param  list<string>  $values
     * @return list<string>
     */
    private function pick(array $values, int $count, string $seed): array
    {
        mt_srand(crc32($seed));
        shuffle($values);
        mt_srand();

        return array_slice($values, 0, $count);
    }

    /**
     * @param  list<int>  $candidates
     * @return list<string>
     */
    private function numericDistractors(int $answer, array $candidates): array
    {
        $out = [];

        foreach ([...$candidates, $answer + 1, $answer - 2, $answer + 3] as $value) {
            if ($value > 0 && $value !== $answer && ! in_array((string) $value, $out, true)) {
                $out[] = (string) $value;
            }

            if (count($out) === 3) {
                break;
            }
        }

        return $out;
    }
}
