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
 *
 * Every item has a stable `source_ref`, so re-running updates instead of duplicating.
 * Choice order is shuffled deterministically per item.
 */
class ContentSeeder extends Seeder
{
    private const array CONTINENTS = ['Europe', 'Asia', 'Africa', 'North America', 'South America', 'Oceania'];

    /** @var array<string, int> */
    private array $categoryIds = [];

    public function __construct(private readonly SaveContentItem $save) {}

    public function run(): void
    {
        $this->categoryIds = Category::query()->pluck('id', 'slug')->map(fn ($id) => (int) $id)->all();

        // No audit-log rows for seed data.
        Model::withoutEvents(function (): void {
            $this->seedHandWritten();
            $this->seedGeography();
            $this->seedTimesTables();
            $this->seedMentalMath();
            $this->seedAnagrams();
            $this->seedWisdomCards();
        });
    }

    private function seedHandWritten(): void
    {
        /** @var list<array{0: string, 1: int, 2: string, 3: string, 4: list<string>, 5: string|null}> $items */
        $items = require __DIR__.'/data/multiple_choice.php';

        foreach ($items as [$slug, $difficulty, $question, $correct, $wrong, $explanation]) {
            $this->multipleChoice($slug, $difficulty, $question, $correct, $wrong, $explanation, 'mc:'.md5($slug.'|'.$question.'|'.$correct));
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

            $this->multipleChoice('times-tables', $difficulty, "What is {$a} × {$b}?", (string) $answer,
                $this->numericDistractors($answer, [$answer + $a, $answer - $b, ($a + 1) * $b, $a * ($b - 1)]),
                null, "times:{$a}x{$b}");
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
            $this->multipleChoice('mental-math', $difficulty, "What is {$expression}?", (string) $answer,
                $this->numericDistractors($answer, [$answer + 10, $answer - 1, $answer + 2, $answer - 10]),
                null, 'mental:'.$expression);
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
                $this->pick($sameLength, 3, "anagram:{$word}"), null, "anagram:{$word}");
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
     */
    private function multipleChoice(string $slug, int $difficulty, string $question, string $correct, array $wrong, ?string $explanation, string $ref): void
    {
        [$choices, $answerId] = $this->shuffledChoices($correct, $wrong, $ref);

        $this->store($slug, 'multiple_choice', $difficulty, [
            'question' => $question,
            'choices' => $choices,
            'image_url' => null,
        ], ['choice_id' => $answerId, 'explanation' => $explanation], $ref);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $answer
     */
    private function store(string $slug, string $type, int $difficulty, array $payload, array $answer, string $ref): void
    {
        $categoryId = $this->categoryIds[$slug] ?? throw new \RuntimeException("Unknown category slug [{$slug}] in seed data.");
        $sourceRef = 'seed:'.$ref;

        $this->save->handle([
            'category_id' => $categoryId,
            'type_key' => $type,
            'locale' => 'en',
            'difficulty' => $difficulty,
            'min_age' => self::minAgeFor($difficulty),
            'max_age' => null,
            'payload' => $payload,
            'answer' => $answer,
            'status' => ContentItem::STATUS_PUBLISHED,
            'source' => 'seed',
            'source_ref' => $sourceRef,
        ], ContentItem::query()->where('source_ref', $sourceRef)->first());
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
