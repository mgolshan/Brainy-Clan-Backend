<?php

namespace Database\Seeders;

use RuntimeException;

/**
 * Loads and strictly validates the hand-written launch question bank
 * (database/seeders/data/questions/*.php, format in data/questions/README.md).
 *
 * Every problem throws a RuntimeException naming the file and entry id, so broken content
 * fails the seeder and CI (tests/Feature/Actions/QuestionBankTest.php loads the real files).
 *
 * @phpstan-type LocaleBlock array{question: string|null, text: string|null, author: string|null, source: string|null, answer: string, wrong: list<string>, explanation: string|null}
 * @phpstan-type Entry array{id: string, file: string, type: string, category: string, difficulty: int, min_age: int|null, cross_locale: bool|null, locales: array<string, LocaleBlock>}
 */
class QuestionBank
{
    public const array LOCALES = ['en', 'fr'];

    /** type => number of wrong answers */
    public const array WRONG_COUNT = ['multiple_choice' => 3, 'wisdom_card' => 2];

    /**
     * @param  list<string>  $leafSlugs  leaf category slugs entries may use
     * @return list<Entry>
     */
    public static function load(string $directory, array $leafSlugs): array
    {
        $files = is_dir($directory) ? (glob(rtrim($directory, '/\\').'/*.php') ?: []) : [];
        sort($files);

        $leaves = array_flip($leafSlugs);
        $entries = [];
        $seen = [];

        foreach ($files as $file) {
            $name = basename($file);
            $data = require $file;

            if (! is_array($data) || ! array_is_list($data)) {
                throw new RuntimeException("Question bank [{$name}] must return a list of entries.");
            }

            foreach ($data as $index => $raw) {
                $entry = self::validate($raw, $name, $index, $leaves);

                if (isset($seen[$entry['id']])) {
                    throw new RuntimeException("Question bank: duplicate id [{$entry['id']}] in [{$name}] (first in [{$seen[$entry['id']]}]).");
                }

                $seen[$entry['id']] = $name;
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param  array<string, int>  $leaves
     * @return Entry
     */
    private static function validate(mixed $raw, string $file, int $index, array $leaves): array
    {
        if (! is_array($raw)) {
            throw new RuntimeException("Question bank [{$file}] entry #{$index} is not an array.");
        }

        $id = $raw['id'] ?? null;

        if (! is_string($id) || trim($id) === '') {
            throw new RuntimeException("Question bank [{$file}] entry #{$index} has no id.");
        }

        $fail = fn (string $message) => new RuntimeException("Question bank [{$file}] entry [{$id}]: {$message}");

        if (strlen($id) > 60) {
            throw $fail('id is longer than 60 characters.');
        }

        $type = $raw['type'] ?? 'multiple_choice';

        if (! is_string($type) || ! isset(self::WRONG_COUNT[$type])) {
            throw $fail('unknown type (multiple_choice or wisdom_card).');
        }

        $category = $raw['category'] ?? null;

        if (! is_string($category) || ! isset($leaves[$category])) {
            throw $fail('category ['.(is_string($category) ? $category : gettype($category)).'] is not a leaf category slug.');
        }

        $difficulty = $raw['difficulty'] ?? null;

        if (! is_int($difficulty) || $difficulty < 1 || $difficulty > 10) {
            throw $fail('difficulty must be an integer from 1 to 10.');
        }

        $minAge = $raw['min_age'] ?? null;

        if ($minAge !== null && (! is_int($minAge) || $minAge < 0 || $minAge > 120)) {
            throw $fail('min_age must be an integer from 0 to 120.');
        }

        $crossLocale = $raw['cross_locale'] ?? null;

        if ($crossLocale !== null && ! is_bool($crossLocale)) {
            throw $fail('cross_locale must be true or false.');
        }

        $locales = [];

        foreach (self::LOCALES as $locale) {
            if (array_key_exists($locale, $raw)) {
                $locales[$locale] = self::validateLocale($raw[$locale], $type, $locale, $fail);
            }
        }

        if ($locales === []) {
            throw $fail('needs an "en" and/or "fr" block.');
        }

        return [
            'id' => $id,
            'file' => $file,
            'type' => $type,
            'category' => $category,
            'difficulty' => $difficulty,
            'min_age' => $minAge,
            'cross_locale' => $crossLocale,
            'locales' => $locales,
        ];
    }

    /**
     * @param  \Closure(string): RuntimeException  $fail
     * @return LocaleBlock
     */
    private static function validateLocale(mixed $block, string $type, string $locale, \Closure $fail): array
    {
        if (! is_array($block)) {
            throw $fail("[{$locale}] must be an array.");
        }

        $required = fn (string $key): string => is_string($block[$key] ?? null) && trim($block[$key]) !== ''
            ? trim($block[$key])
            : throw $fail("[{$locale}.{$key}] must be a non-empty string.");

        $optional = function (string $key) use ($block, $locale, $fail): ?string {
            $value = $block[$key] ?? null;

            if ($value === null) {
                return null;
            }

            if (! is_string($value) || trim($value) === '') {
                throw $fail("[{$locale}.{$key}] must be null or a non-empty string.");
            }

            return trim($value);
        };

        $isWisdom = $type === 'wisdom_card';
        $answer = $required('answer');
        $wrong = $block['wrong'] ?? null;
        $count = self::WRONG_COUNT[$type];

        if (! is_array($wrong) || ! array_is_list($wrong) || count($wrong) !== $count) {
            throw $fail("[{$locale}.wrong] must list exactly {$count} wrong answers.");
        }

        $normalized = [mb_strtolower($answer)];

        foreach ($wrong as $i => $value) {
            if (! is_string($value) || trim($value) === '') {
                throw $fail("[{$locale}.wrong.{$i}] must be a non-empty string.");
            }

            $key = mb_strtolower(trim($value));

            if (in_array($key, $normalized, true)) {
                throw $fail("[{$locale}.wrong] repeats the answer or another wrong answer [{$value}].");
            }

            $normalized[] = $key;
            $wrong[$i] = trim($value);
        }

        return [
            'question' => $isWisdom ? null : $required('question'),
            'text' => $isWisdom ? $required('text') : null,
            'author' => $isWisdom ? $optional('author') : null,
            'source' => $isWisdom ? $optional('source') : null,
            'answer' => $answer,
            'wrong' => array_values($wrong),
            'explanation' => $optional('explanation'),
        ];
    }
}
