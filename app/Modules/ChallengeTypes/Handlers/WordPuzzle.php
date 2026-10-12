<?php

namespace App\Modules\ChallengeTypes\Handlers;

use App\Modules\ChallengeTypes\Contracts\ChallengeType;
use App\Modules\ChallengeTypes\Generators\WordSearchBuilder;
use App\Modules\ChallengeTypes\Support\Words;
use App\Modules\Content\Models\ContentItem;

/**
 * Word puzzles (content items), by `payload.variant`:
 *
 * - `anagram`:        payload { variant, letters: ["T","A","C"], hint }  answer { words: ["CAT","ACT"] }
 *                     response { word }  — any listed word made of exactly those letters.
 * - `missing_letter`: payload { variant, pattern: "EL_PHANT", hint }     answer { word: "ELEPHANT" }
 *                     response { word }
 * - `word_search`:    payload { variant, grid: [["C","A","T"], …], words: ["CAT", …] }
 *                     answer { found: [{ word, start: [r, c], end: [r, c] }] }
 *                     response { found: [...] } — correct when every word is found at a valid
 *                     placement (straight line, 8 directions, start/end in either order).
 *
 * Words compare case-insensitively and Unicode-aware (NFC + multibyte case folding, see Words),
 * so French accents work. A word search is worth 3 points, the others 1.
 */
class WordPuzzle implements ChallengeType
{
    public const array VARIANTS = ['anagram', 'missing_letter', 'word_search'];

    public const int WORD_SEARCH_POINTS = 3;

    public function key(): string
    {
        return 'word_puzzle';
    }

    public function name(): string
    {
        return 'Word puzzle';
    }

    public function validate(array $payload, array $answer): array
    {
        return match ($payload['variant'] ?? null) {
            'anagram' => $this->validateAnagram($payload, $answer),
            'missing_letter' => $this->validateMissingLetter($payload, $answer),
            'word_search' => $this->validateWordSearch($payload, $answer),
            default => ['payload.variant' => ['The variant must be one of: '.implode(', ', self::VARIANTS).'.']],
        };
    }

    public function forClient(ContentItem $item): array
    {
        $payload = $item->payload;
        $hint = $payload['hint'] ?? null;
        $hint = is_string($hint) && $hint !== '' ? $hint : null;

        return match ($payload['variant'] ?? null) {
            'anagram' => ['variant' => 'anagram', 'letters' => array_values((array) ($payload['letters'] ?? [])), 'hint' => $hint],
            'missing_letter' => ['variant' => 'missing_letter', 'pattern' => (string) ($payload['pattern'] ?? ''), 'hint' => $hint],
            'word_search' => ['variant' => 'word_search', 'grid' => array_values((array) ($payload['grid'] ?? [])), 'words' => array_values((array) ($payload['words'] ?? []))],
            default => ['variant' => (string) ($payload['variant'] ?? '')],
        };
    }

    public function validateResponse(ContentItem $item, array $response): array
    {
        if (($item->payload['variant'] ?? null) !== 'word_search') {
            $word = $response['word'] ?? null;

            return is_string($word) && preg_match("/^[\p{L}\p{M}' -]{1,40}$/u", trim($word)) === 1
                ? []
                : ['response.word' => ['Type a word (letters only).']];
        }

        $found = $response['found'] ?? null;

        if (! is_array($found) || ! array_is_list($found)) {
            return ['response.found' => ['Send the found words as a list of { word, start, end }.']];
        }

        $grid = (array) ($item->payload['grid'] ?? []);
        $rows = count($grid);
        $cols = count((array) ($grid[0] ?? []));
        $words = array_map(fn ($w) => Words::fold((string) $w), (array) ($item->payload['words'] ?? []));

        foreach ($found as $index => $entry) {
            if (! is_array($entry) || ! is_string($entry['word'] ?? null)
                || ! $this->isCell($entry['start'] ?? null, $rows, $cols) || ! $this->isCell($entry['end'] ?? null, $rows, $cols)) {
                return ["response.found.{$index}" => ['Each entry needs a word and start/end cells [row, col] inside the grid.']];
            }

            if (! in_array(Words::fold($entry['word']), $words, true)) {
                return ["response.found.{$index}.word" => ['That word is not in this puzzle.']];
            }
        }

        return [];
    }

    public function check(ContentItem $item, array $response): bool
    {
        return match ($item->payload['variant'] ?? null) {
            'anagram' => $this->checkAnagram($item, $response),
            'missing_letter' => is_string($response['word'] ?? null) && $this->isAccepted($item, $response['word']),
            'word_search' => $this->checkWordSearch($item, $response),
            default => false,
        };
    }

    public function correctResponse(ContentItem $item): array
    {
        if (($item->payload['variant'] ?? null) === 'word_search') {
            return ['found' => array_values((array) ($item->answer['found'] ?? []))];
        }

        return ['word' => $this->acceptedWords($item)[0] ?? ''];
    }

    public function explanation(ContentItem $item): ?string
    {
        $explanation = $item->answer['explanation'] ?? null;

        return is_string($explanation) && $explanation !== '' ? $explanation : null;
    }

    public function points(ContentItem $item): int
    {
        return ($item->payload['variant'] ?? null) === 'word_search' ? self::WORD_SEARCH_POINTS : 1;
    }

    /**
     * CSV columns: `variant`; anagram/missing_letter: `word`, `hint`, optional `letters` / `pattern`
     * (derived from the word when empty), `also_accepted` (space-separated); word_search: `words`
     * (space- or comma-separated) and optional `size` (default 8) — the grid is built from the row.
     */
    public function fromFlatRow(array $row): array
    {
        $variant = strtolower(trim((string) ($row['variant'] ?? '')));
        $word = Words::display((string) ($row['word'] ?? ''));
        $hint = trim((string) ($row['hint'] ?? ''));
        $hint = $hint !== '' ? $hint : null;

        if ($variant === 'word_search') {
            $words = array_values(array_filter(preg_split('/[\s,;]+/u', Words::display((string) ($row['words'] ?? ''))) ?: []));
            $size = max(5, min(14, (int) ($row['size'] ?? 8)));
            $built = (new WordSearchBuilder)->build($words, $size, WordSearchBuilder::LEVELS[$size <= 6 ? 'easy' : ($size <= 8 ? 'medium' : 'hard')], implode(',', $words));

            return [
                'payload' => ['variant' => 'word_search', 'grid' => $built['grid'], 'words' => array_column($built['found'], 'word')],
                'answer' => ['found' => $built['found']],
            ];
        }

        if ($variant === 'anagram') {
            $letters = trim((string) ($row['letters'] ?? ''));
            $letters = $letters !== '' ? Words::letters(Words::display($letters)) : self::scramble($word, $word);
            $also = array_filter(preg_split('/[\s,;]+/u', (string) ($row['also_accepted'] ?? '')) ?: []);

            return [
                'payload' => ['variant' => 'anagram', 'letters' => $letters, 'hint' => $hint],
                'answer' => ['words' => array_values(array_unique([$word, ...array_map(fn ($w) => Words::display($w), $also)]))],
            ];
        }

        $pattern = Words::display((string) ($row['pattern'] ?? ''));

        return [
            'payload' => ['variant' => $variant, 'pattern' => $pattern !== '' ? $pattern : self::hideLetters($word, 1, $word), 'hint' => $hint],
            'answer' => ['word' => $word],
        ];
    }

    public function flatColumns(): array
    {
        return ['variant', 'word', 'hint', 'letters', 'pattern', 'also_accepted', 'words', 'size'];
    }

    /**
     * The word's letters in a seeded order that never spells the word itself.
     *
     * @return list<string>
     */
    public static function scramble(string $word, string $seed): array
    {
        $letters = Words::letters(Words::display($word));

        if (count(array_unique($letters)) < 2) {
            return $letters;
        }

        mt_srand(crc32('anagram|'.$seed));

        do {
            shuffle($letters);
        } while (Words::equals(implode('', $letters), $word));

        mt_srand();

        return $letters;
    }

    /**
     * The word with $count letters replaced by "_" (seeded; never the whole word).
     */
    public static function hideLetters(string $word, int $count, string $seed): string
    {
        $letters = Words::letters(Words::display($word));
        $positions = range(0, count($letters) - 1);
        mt_srand(crc32('missing|'.$seed));
        shuffle($positions);
        mt_srand();

        foreach (array_slice($positions, 0, max(1, min($count, count($letters) - 1))) as $position) {
            $letters[$position] = '_';
        }

        return implode('', $letters);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function checkAnagram(ContentItem $item, array $response): bool
    {
        if (! is_string($response['word'] ?? null)) {
            return false;
        }

        return $this->isAccepted($item, $response['word'])
            && $this->sortedLetters($response['word']) === $this->sortedLetters(implode('', (array) ($item->payload['letters'] ?? [])));
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function checkWordSearch(ContentItem $item, array $response): bool
    {
        /** @var list<list<string>> $grid */
        $grid = array_values((array) ($item->payload['grid'] ?? []));
        $rows = count($grid);
        $cols = count($grid[0] ?? []);
        $found = $response['found'] ?? null;

        if (! is_array($found)) {
            return false;
        }

        foreach ((array) ($item->payload['words'] ?? []) as $word) {
            $located = false;

            foreach ($found as $entry) {
                if (is_array($entry) && is_string($entry['word'] ?? null) && Words::equals($entry['word'], (string) $word)
                    && $this->isCell($entry['start'] ?? null, $rows, $cols) && $this->isCell($entry['end'] ?? null, $rows, $cols)
                    && WordSearchBuilder::spells($grid, (string) $word, $entry['start'], $entry['end'])) {
                    $located = true;
                    break;
                }
            }

            if (! $located) {
                return false;
            }
        }

        return true;
    }

    private function isAccepted(ContentItem $item, string $word): bool
    {
        foreach ($this->acceptedWords($item) as $accepted) {
            if (Words::equals($accepted, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function acceptedWords(ContentItem $item): array
    {
        $words = $item->answer['words'] ?? [$item->answer['word'] ?? ''];

        return array_values(array_map(fn ($w) => Words::display((string) $w), (array) $words));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $answer
     * @return array<string, list<string>>
     */
    private function validateAnagram(array $payload, array $answer): array
    {
        $errors = [];
        $letters = $payload['letters'] ?? null;
        $words = $answer['words'] ?? null;

        if (! is_array($letters) || count($letters) < 2 || array_filter($letters, fn ($l) => ! is_string($l) || mb_strlen($l) !== 1 || preg_match('/^\p{L}$/u', $l) !== 1) !== []) {
            $errors['payload.letters'][] = 'Letters must be a list of at least 2 single letters.';
        }

        if (isset($payload['hint']) && ! is_string($payload['hint'])) {
            $errors['payload.hint'][] = 'The hint must be text or null.';
        }

        if (! is_array($words) || $words === [] || array_filter($words, fn ($w) => ! is_string($w) || trim($w) === '') !== []) {
            $errors['answer.words'][] = 'List at least one accepted word.';
        } elseif (is_array($letters) && ! isset($errors['payload.letters'])) {
            $sorted = $this->sortedLetters(implode('', $letters));

            foreach ($words as $word) {
                if ($this->sortedLetters((string) $word) !== $sorted) {
                    $errors['answer.words'][] = "\"{$word}\" does not use exactly the given letters.";
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $answer
     * @return array<string, list<string>>
     */
    private function validateMissingLetter(array $payload, array $answer): array
    {
        $errors = [];
        $pattern = $payload['pattern'] ?? null;
        $word = $answer['word'] ?? null;

        if (! is_string($pattern) || ! str_contains($pattern, '_')) {
            $errors['payload.pattern'][] = 'The pattern must contain at least one "_".';
        }

        if (! is_string($word) || trim($word) === '') {
            $errors['answer.word'][] = 'The answer word is required.';
        }

        if ($errors === [] && is_string($pattern) && is_string($word)) {
            $wordLetters = Words::letters(Words::fold($word));
            $patternLetters = Words::letters(Words::fold($pattern));
            $fits = count($wordLetters) === count($patternLetters);

            foreach ($patternLetters as $i => $letter) {
                if ($fits && $letter !== '_' && $letter !== $wordLetters[$i]) {
                    $fits = false;
                }
            }

            if (! $fits) {
                $errors['answer.word'][] = 'The word must fit the pattern.';
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $answer
     * @return array<string, list<string>>
     */
    private function validateWordSearch(array $payload, array $answer): array
    {
        $grid = $payload['grid'] ?? null;
        $words = $payload['words'] ?? null;

        if (! is_array($grid) || $grid === [] || ! array_is_list($grid)) {
            return ['payload.grid' => ['The grid must be a list of rows of letters.']];
        }

        $cols = count((array) $grid[0]);

        foreach ($grid as $row) {
            if (! is_array($row) || count($row) !== $cols || array_filter($row, fn ($l) => ! is_string($l) || mb_strlen($l) !== 1) !== []) {
                return ['payload.grid' => ['Every row must have the same number of single letters.']];
            }
        }

        if (! is_array($words) || $words === []) {
            return ['payload.words' => ['List the words to find.']];
        }

        $found = (array) ($answer['found'] ?? []);

        foreach ($words as $word) {
            $placement = collect($found)->first(fn ($f) => is_array($f) && Words::equals((string) ($f['word'] ?? ''), (string) $word));

            if (! is_array($placement) || ! $this->isCell($placement['start'] ?? null, count($grid), $cols) || ! $this->isCell($placement['end'] ?? null, count($grid), $cols)
                || ! WordSearchBuilder::spells($grid, (string) $word, $placement['start'], $placement['end'])) {
                return ['answer.found' => ["\"{$word}\" needs a valid placement in the grid."]];
            }
        }

        return [];
    }

    /**
     * @phpstan-assert-if-true array{0: int, 1: int} $cell
     */
    private function isCell(mixed $cell, int $rows, int $cols): bool
    {
        return is_array($cell) && array_is_list($cell) && count($cell) === 2
            && is_int($cell[0]) && is_int($cell[1])
            && $cell[0] >= 0 && $cell[0] < $rows && $cell[1] >= 0 && $cell[1] < $cols;
    }

    private function sortedLetters(string $word): string
    {
        $letters = Words::letters(Words::fold($word));
        sort($letters);

        return implode('', $letters);
    }
}
