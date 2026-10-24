<?php

namespace App\Modules\ChallengeTypes\Handlers;

use App\Modules\ChallengeTypes\Generators\NonogramGenerator;
use App\Modules\Content\Models\ContentItem;

/**
 * Nonogram (picture logic puzzle): fill cells so every row and column matches its clue runs.
 *
 * payload:  { "rows": 5, "cols": 5, "row_clues": [[1,1],[5],…], "col_clues": [[2],[4],…] }
 * answer:   { "solution": [[0,1,…],…], "picture": "heart", "title": "A heart" }   (title in the member's language)
 * response: { "grid": [[0,1,…],…] }                                              (1 = filled)
 *
 * Correct when the grid satisfies every row and column clue; generated puzzles are solvable line
 * by line, so that is the stored solution. A grid of the wrong size (or cells other than 0/1) is a
 * 422. 5×5 (difficulty ≤ 5), 8×8 (6–8) or 10×10 (9–10); worth 3, 5 or 8 points.
 */
class Nonogram extends BrainGame
{
    /** Points per size. */
    public const array POINTS = [5 => 3, 8 => 5, 10 => 8];

    public function __construct(private readonly NonogramGenerator $generator) {}

    public function key(): string
    {
        return 'nonogram';
    }

    public function name(): string
    {
        return 'Nonogram';
    }

    public static function sizeFor(int $difficulty): int
    {
        return match (true) {
            $difficulty <= 5 => 5,
            $difficulty <= 8 => 8,
            default => 10,
        };
    }

    public function validate(array $payload, array $answer): array
    {
        $rows = $payload['rows'] ?? null;
        $cols = $payload['cols'] ?? null;

        if (! is_int($rows) || ! is_int($cols) || $rows < 2 || $rows > 15 || $cols < 2 || $cols > 15) {
            return ['payload.rows' => ['rows and cols must be 2–15.']];
        }

        $errors = [];
        $rowClues = $payload['row_clues'] ?? null;
        $colClues = $payload['col_clues'] ?? null;

        if (! $this->areClues($rowClues, $rows, $cols)) {
            $errors['payload.row_clues'][] = "row_clues must be {$rows} lists of run lengths.";
        }

        if (! $this->areClues($colClues, $cols, $rows)) {
            $errors['payload.col_clues'][] = "col_clues must be {$cols} lists of run lengths.";
        }

        $solution = $answer['solution'] ?? null;

        if (! $this->isGrid($solution, $rows, $cols)) {
            $errors['answer.solution'][] = "The solution must be {$rows} rows of {$cols} cells (0 or 1).";
        }

        if ($errors === []) {
            /** @var list<list<int>> $rowClues */
            /** @var list<list<int>> $colClues */
            /** @var list<list<int>> $solution */
            if (NonogramGenerator::clues($solution) !== ['rows' => $rowClues, 'cols' => $colClues]) {
                $errors['answer.solution'][] = 'The solution does not match the clues.';
            } elseif ($this->generator->solve($rowClues, $colClues) !== $solution) {
                $errors['payload.row_clues'][] = 'The puzzle must have exactly one solution, found line by line.';
            }
        }

        if (isset($answer['title']) && ! is_string($answer['title'])) {
            $errors['answer.title'][] = 'The title must be text.';
        }

        return $errors;
    }

    public function forClient(ContentItem $item): array
    {
        return [
            'rows' => (int) ($item->payload['rows'] ?? 0),
            'cols' => (int) ($item->payload['cols'] ?? 0),
            'row_clues' => array_values((array) ($item->payload['row_clues'] ?? [])),
            'col_clues' => array_values((array) ($item->payload['col_clues'] ?? [])),
        ];
    }

    public function validateResponse(ContentItem $item, array $response): array
    {
        $rows = (int) ($item->payload['rows'] ?? 0);
        $cols = (int) ($item->payload['cols'] ?? 0);

        if (! $this->isGrid($response['grid'] ?? null, $rows, $cols)) {
            return ['response.grid' => ["Send the full grid: {$rows} rows of {$cols} cells (1 = filled, 0 = empty)."]];
        }

        return [];
    }

    public function check(ContentItem $item, array $response): bool
    {
        $rows = (int) ($item->payload['rows'] ?? 0);
        $cols = (int) ($item->payload['cols'] ?? 0);
        $grid = $response['grid'] ?? null;

        if (! $this->isGrid($grid, $rows, $cols)) {
            return false;
        }

        return NonogramGenerator::clues($grid) === [
            'rows' => array_values((array) ($item->payload['row_clues'] ?? [])),
            'cols' => array_values((array) ($item->payload['col_clues'] ?? [])),
        ];
    }

    public function correctResponse(ContentItem $item): array
    {
        $title = $item->answer['title'] ?? null;

        return [
            'grid' => array_values((array) ($item->answer['solution'] ?? [])),
            'title' => is_string($title) && $title !== '' ? $title : null,
        ];
    }

    public function points(ContentItem $item): int
    {
        return self::POINTS[(int) ($item->payload['rows'] ?? 0)] ?? 3;
    }

    public function generate(string $seed, int $difficulty, array $options): array
    {
        $size = self::sizeFor(self::clampDifficulty($difficulty));
        $picture = $this->generator->generate($seed, $size);
        $clues = NonogramGenerator::clues($picture['solution']);
        $locale = ($options['locale'] ?? 'en') === 'fr' ? 'fr' : 'en';

        return [
            'payload' => ['rows' => $size, 'cols' => $size, 'row_clues' => $clues['rows'], 'col_clues' => $clues['cols']],
            'answer' => ['solution' => $picture['solution'], 'picture' => $picture['picture'], 'title' => $picture['titles'][$locale]],
        ];
    }

    public function pointsFor(array $options, int $difficulty): int
    {
        return self::POINTS[self::sizeFor(self::clampDifficulty($difficulty))];
    }

    /**
     * @phpstan-assert-if-true list<list<int>> $grid
     */
    private function isGrid(mixed $grid, int $rows, int $cols): bool
    {
        if ($rows < 1 || ! is_array($grid) || ! array_is_list($grid) || count($grid) !== $rows) {
            return false;
        }

        foreach ($grid as $row) {
            if (! self::isIntList($row, 0, 1) || count($row) !== $cols) {
                return false;
            }
        }

        return true;
    }

    private function areClues(mixed $clues, int $lines, int $length): bool
    {
        if (! is_array($clues) || ! array_is_list($clues) || count($clues) !== $lines) {
            return false;
        }

        foreach ($clues as $line) {
            if (! self::isIntList($line, 1, $length) || array_sum($line) + count($line) - 1 > $length) {
                return false;
            }
        }

        return true;
    }
}
