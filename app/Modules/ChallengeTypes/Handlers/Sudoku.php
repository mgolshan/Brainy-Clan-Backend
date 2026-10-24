<?php

namespace App\Modules\ChallengeTypes\Handlers;

use App\Modules\ChallengeTypes\Contracts\ChallengeType;
use App\Modules\ChallengeTypes\Contracts\GeneratesItems;
use App\Modules\ChallengeTypes\Generators\SudokuGenerator;
use App\Modules\Content\Models\ContentItem;

/**
 * Sudoku, solved on the device and verified on submit.
 *
 * payload:  { "size": 4, "box": [2, 2], "grid": [[0,1,0,0], …] }   (0 = empty)
 * answer:   { "solution": [[…]] }                                   (never sent before answering)
 * response: { "grid": [[…]] }                                       (the full grid)
 *
 * Correct when the submitted grid keeps every given and is a valid complete Sudoku (generated
 * puzzles have a unique solution, so that is the stored solution). Generated per session from a
 * seed; generator options: `{ "size": 4 | 6 | 9 }`. Givens scale with difficulty.
 */
class Sudoku implements ChallengeType, GeneratesItems
{
    /** Points for a correct answer per size. */
    public const array POINTS = [4 => 3, 6 => 5, 9 => 8];

    /**
     * Givens per size: [difficulty at which the most givens are shown, difficulty with the
     * fewest, most givens, fewest givens]. Linear in between, clamped outside.
     */
    public const array GIVENS = [
        4 => [1, 3, 10, 6],
        6 => [3, 6, 22, 12],
        9 => [5, 10, 46, 28],
    ];

    public function __construct(private readonly SudokuGenerator $generator) {}

    public function key(): string
    {
        return 'sudoku';
    }

    public function name(): string
    {
        return 'Sudoku';
    }

    public function validate(array $payload, array $answer): array
    {
        $size = $payload['size'] ?? null;

        if (! is_int($size) || ! isset(SudokuGenerator::BOXES[$size])) {
            return ['payload.size' => ['The size must be 4, 6 or 9.']];
        }

        $errors = [];

        if (($payload['box'] ?? null) !== SudokuGenerator::BOXES[$size]) {
            $errors['payload.box'][] = 'The box must be '.json_encode(SudokuGenerator::BOXES[$size]).' for size '.$size.'.';
        }

        $grid = $payload['grid'] ?? null;
        $solution = $answer['solution'] ?? null;

        if (! $this->isGrid($grid, $size, allowEmpty: true)) {
            $errors['payload.grid'][] = "The grid must be {$size} rows of {$size} numbers from 0 to {$size}.";
        }

        if (! $this->isGrid($solution, $size, allowEmpty: false) || ! $this->generator->isValidSolution($solution)) {
            $errors['answer.solution'][] = 'The solution must be a complete, valid grid.';
        }

        if ($errors === []) {
            /** @var list<list<int>> $grid */
            /** @var list<list<int>> $solution */
            if (! $this->keepsGivens($grid, $solution)) {
                $errors['answer.solution'][] = 'The solution must keep every given of the grid.';
            } elseif ($this->generator->countSolutions($grid) !== 1) {
                $errors['payload.grid'][] = 'The puzzle must have exactly one solution.';
            }
        }

        return $errors;
    }

    public function forClient(ContentItem $item): array
    {
        $size = (int) ($item->payload['size'] ?? 0);

        return [
            'size' => $size,
            'box' => SudokuGenerator::BOXES[$size] ?? [],
            'grid' => array_values((array) ($item->payload['grid'] ?? [])),
        ];
    }

    public function validateResponse(ContentItem $item, array $response): array
    {
        $size = (int) ($item->payload['size'] ?? 0);

        if (! $this->isGrid($response['grid'] ?? null, $size, allowEmpty: true)) {
            return ['response.grid' => ["Send the full grid: {$size} rows of {$size} numbers from 0 to {$size} (0 = empty)."]];
        }

        return [];
    }

    public function check(ContentItem $item, array $response): bool
    {
        $size = (int) ($item->payload['size'] ?? 0);
        $grid = $response['grid'] ?? null;
        $givens = $item->payload['grid'] ?? null;

        if (! $this->isGrid($grid, $size, allowEmpty: false) || ! $this->isGrid($givens, $size, allowEmpty: true)) {
            return false;
        }

        /** @var list<list<int>> $grid */
        /** @var list<list<int>> $givens */
        return $this->keepsGivens($givens, $grid) && $this->generator->isValidSolution($grid);
    }

    public function correctResponse(ContentItem $item): array
    {
        return ['grid' => array_values((array) ($item->answer['solution'] ?? []))];
    }

    public function explanation(ContentItem $item): ?string
    {
        return null;
    }

    public function points(ContentItem $item): int
    {
        return self::POINTS[(int) ($item->payload['size'] ?? 0)] ?? 1;
    }

    public function generate(string $seed, int $difficulty, array $options): array
    {
        $size = $this->sizeOption($options);
        $puzzle = $this->generator->generate($seed, $size, self::givensFor($size, $difficulty));

        return [
            'payload' => ['size' => $size, 'box' => SudokuGenerator::BOXES[$size], 'grid' => $puzzle['grid']],
            'answer' => ['solution' => $puzzle['solution']],
        ];
    }

    public function pointsFor(array $options, int $difficulty): int
    {
        return self::POINTS[$this->sizeOption($options)];
    }

    public function validateOptions(array $options): array
    {
        return in_array((int) ($options['size'] ?? 0), [4, 6, 9], true) ? [] : ['options.size' => ['The size must be 4, 6 or 9.']];
    }

    /**
     * Target number of givens for a size and difficulty (1–10).
     */
    public static function givensFor(int $size, int $difficulty): int
    {
        [$easiest, $hardest, $most, $fewest] = self::GIVENS[$size];
        $position = ($difficulty - $easiest) / max(1, $hardest - $easiest);
        $position = max(0.0, min(1.0, $position));

        return (int) round($most - $position * ($most - $fewest));
    }

    /**
     * CSV: `grid` and `solution` as digit strings row by row (0 or . = empty), e.g. 16 digits for 4×4.
     */
    public function fromFlatRow(array $row): array
    {
        $grid = $this->parseDigits((string) ($row['grid'] ?? ''));
        $solution = $this->parseDigits((string) ($row['solution'] ?? ''));
        $size = count($grid);

        return [
            'payload' => ['size' => $size, 'box' => SudokuGenerator::BOXES[$size] ?? [], 'grid' => $grid],
            'answer' => ['solution' => $solution],
        ];
    }

    public function flatColumns(): array
    {
        return ['grid', 'solution'];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function sizeOption(array $options): int
    {
        $size = (int) ($options['size'] ?? 4);

        return isset(self::POINTS[$size]) ? $size : 4;
    }

    /**
     * @phpstan-assert-if-true list<list<int>> $grid
     */
    private function isGrid(mixed $grid, int $size, bool $allowEmpty): bool
    {
        if (! isset(SudokuGenerator::BOXES[$size]) || ! is_array($grid) || ! array_is_list($grid) || count($grid) !== $size) {
            return false;
        }

        foreach ($grid as $row) {
            if (! is_array($row) || ! array_is_list($row) || count($row) !== $size) {
                return false;
            }

            foreach ($row as $value) {
                if (! is_int($value) || $value < ($allowEmpty ? 0 : 1) || $value > $size) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  list<list<int>>  $givens
     * @param  list<list<int>>  $grid
     */
    private function keepsGivens(array $givens, array $grid): bool
    {
        foreach ($givens as $r => $row) {
            foreach ($row as $c => $value) {
                if ($value !== 0 && $grid[$r][$c] !== $value) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return list<list<int>>
     */
    private function parseDigits(string $digits): array
    {
        $cells = array_map(fn (string $ch) => $ch === '.' ? 0 : (int) $ch, str_split((string) preg_replace('/[^0-9.]/', '', $digits)));
        $size = (int) round(sqrt(count($cells)));

        return $size > 0 && $size * $size === count($cells) ? array_chunk($cells, $size) : [];
    }
}
