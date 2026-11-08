<?php

namespace App\Modules\ChallengeTypes\Handlers;

use App\Modules\ChallengeTypes\Generators\CrossMathGenerator;
use App\Modules\ChallengeTypes\Generators\CrossMathSolver;
use App\Modules\Content\Models\ContentItem;

/**
 * Cross Math: a math crossword on a number lattice (docs/API.md "Level-up puzzles").
 *
 * payload:  { "rows": 5, "cols": 5, "cells": [[4,"+","?","=",9],["-",null,"*",null,"-"],…], "bank": [3,5,6] }
 * answer:   { "fills": [[0,2,5],…], "difficulty": 3 }        (the generator's solution; difficulty sets the points)
 * response: { "fills": [[0,2,5],…] }                         ([row, col, value] per blank)
 *
 * Numbers sit on even rows and columns, operators and `=` between them, null on odd/odd cells;
 * every even row and column is one equation evaluated with normal precedence. Correct when every
 * blank is filled, the values are exactly the bank and every equation holds (exact arithmetic,
 * so any valid arrangement counts). Fewer fills than blanks is a wrong answer; a fill outside the
 * grid, on a cell that is not "?", on the same cell twice, a value that is not a whole number, or
 * more fills than blanks → 422. 5×5 at difficulty 1–6, 7×7 at 7–10; worth 2, 3, 5 or 8 points.
 */
class CrossMath extends BrainGame
{
    /** Operators a cell may hold between two numbers. */
    public const array OPERATORS = ['+', '-', '*', '/'];

    public function __construct(private readonly CrossMathGenerator $generator) {}

    public function key(): string
    {
        return 'cross_math';
    }

    public function name(): string
    {
        return 'Cross Math';
    }

    public static function pointsForDifficulty(int $difficulty): int
    {
        return match (true) {
            $difficulty <= 3 => 2,
            $difficulty <= 6 => 3,
            $difficulty <= 8 => 5,
            default => 8,
        };
    }

    /**
     * The equations of a well-formed lattice (each: operand cells, operators, result cell), or
     * null when the cells are not a 5×5 / 7×7 lattice.
     *
     * @return list<array{operands: list<array{0: int, 1: int}>, ops: list<string>, result: array{0: int, 1: int}}>|null
     */
    public static function equations(mixed $cells): ?array
    {
        if (! is_array($cells) || ! array_is_list($cells) || ! in_array(count($cells), [5, 7], true)) {
            return null;
        }

        $size = count($cells);

        foreach ($cells as $r => $row) {
            if (! is_array($row) || ! array_is_list($row) || count($row) !== $size) {
                return null;
            }

            foreach ($row as $c => $cell) {
                $valid = match (true) {
                    $r % 2 === 0 && $c % 2 === 0 => $cell === '?' || (is_int($cell) && $cell >= 0),
                    $r % 2 === 0 => $c === $size - 2 ? $cell === '=' : in_array($cell, self::OPERATORS, true),
                    $c % 2 === 0 => $r === $size - 2 ? $cell === '=' : in_array($cell, self::OPERATORS, true),
                    default => $cell === null,
                };

                if (! $valid) {
                    return null;
                }
            }
        }

        $equations = [];

        for ($line = 0; $line < $size; $line += 2) {
            foreach ([true, false] as $isRow) {
                $operands = [];
                $ops = [];

                for ($at = 0; $at < $size - 1; $at += 2) {
                    $operands[] = $isRow ? [$line, $at] : [$at, $line];

                    if ($at + 1 < $size - 2) {
                        /** @var string $op */
                        $op = $isRow ? $cells[$line][$at + 1] : $cells[$at + 1][$line];
                        $ops[] = $op;
                    }
                }

                $equations[] = ['operands' => $operands, 'ops' => $ops, 'result' => $isRow ? [$line, $size - 1] : [$size - 1, $line]];
            }
        }

        return $equations;
    }

    public function validate(array $payload, array $answer): array
    {
        $cells = $payload['cells'] ?? null;
        $equations = self::equations($cells);

        if ($equations === null || ($payload['rows'] ?? null) !== count((array) $cells) || ($payload['cols'] ?? null) !== count((array) $cells)) {
            return ['payload.cells' => ['cells must be a 5×5 or 7×7 lattice (rows = cols = its size): numbers or "?" on even rows and columns, + - * / between them, "=" before each result, null elsewhere.']];
        }

        /** @var list<list<int|string|null>> $cells */
        $errors = [];
        $blanks = self::blanks($cells);
        $bank = $payload['bank'] ?? null;

        if (! self::isIntList($bank, 0, PHP_INT_MAX) || count($bank) !== count($blanks) || $bank === [] || $bank !== self::sorted($bank)) {
            $errors['payload.bank'][] = 'bank must list one whole number per "?" cell (at least one), sorted ascending.';
        }

        $fills = $answer['fills'] ?? null;

        if ($errors === [] && ($this->fillErrors($cells, $fills) !== null || ! $this->solves($cells, $equations, (array) $bank, (array) $fills))) {
            $errors['answer.fills'][] = 'fills must place the bank into the blanks ([row, col, value] each) so that every equation holds.';
        }

        if (isset($answer['difficulty']) && (! is_int($answer['difficulty']) || $answer['difficulty'] < 1 || $answer['difficulty'] > self::MAX_DIFFICULTY)) {
            $errors['answer.difficulty'][] = 'The difficulty must be 1–10.';
        }

        return $errors;
    }

    public function forClient(ContentItem $item): array
    {
        return [
            'rows' => (int) ($item->payload['rows'] ?? 0),
            'cols' => (int) ($item->payload['cols'] ?? 0),
            'cells' => array_values((array) ($item->payload['cells'] ?? [])),
            'bank' => array_values((array) ($item->payload['bank'] ?? [])),
        ];
    }

    public function validateResponse(ContentItem $item, array $response): array
    {
        $cells = (array) ($item->payload['cells'] ?? []);
        $error = $this->fillErrors($cells, $response['fills'] ?? null, partial: true);

        return $error !== null ? ['response.fills' => [$error]] : [];
    }

    public function check(ContentItem $item, array $response): bool
    {
        $cells = $item->payload['cells'] ?? null;
        $equations = self::equations($cells);
        $fills = $response['fills'] ?? null;

        if ($equations === null || $this->fillErrors((array) $cells, $fills) !== null) {
            return false;
        }

        /** @var list<list<int|string|null>> $cells */
        return $this->solves($cells, $equations, (array) ($item->payload['bank'] ?? []), (array) $fills);
    }

    public function correctResponse(ContentItem $item): array
    {
        return ['fills' => array_values((array) ($item->answer['fills'] ?? []))];
    }

    public function points(ContentItem $item): int
    {
        $difficulty = $item->answer['difficulty'] ?? null;

        if (is_int($difficulty)) {
            return self::pointsForDifficulty(self::clampDifficulty($difficulty));
        }

        $blanks = count((array) ($item->payload['bank'] ?? []));

        return (int) ($item->payload['rows'] ?? 5) === 7 ? ($blanks >= 12 ? 8 : 5) : ($blanks <= 4 ? 2 : 3);
    }

    public function generate(string $seed, int $difficulty, array $options): array
    {
        $difficulty = self::clampDifficulty($difficulty);
        $built = $this->generator->generate($seed, $difficulty);

        return [
            'payload' => $built['payload'],
            'answer' => [...$built['answer'], 'difficulty' => $difficulty],
        ];
    }

    public function pointsFor(array $options, int $difficulty): int
    {
        return self::pointsForDifficulty(self::clampDifficulty($difficulty));
    }

    /**
     * Why the fills are malformed (null = well-formed). With $partial, fewer fills than blanks
     * are allowed (that is a wrong answer, not a malformed one).
     *
     * @param  array<mixed>  $cells
     */
    private function fillErrors(array $cells, mixed $fills, bool $partial = false): ?string
    {
        $blanks = self::blanks($cells);

        if (! is_array($fills) || ! array_is_list($fills)) {
            return 'Send fills as a list of [row, col, value].';
        }

        if (count($fills) > count($blanks)) {
            return 'There are only '.count($blanks).' blanks to fill.';
        }

        $seen = [];

        foreach ($fills as $index => $fill) {
            if (! is_array($fill) || ! array_is_list($fill) || count($fill) !== 3 || ! is_int($fill[0]) || ! is_int($fill[1]) || ! is_int($fill[2])) {
                return "Fill {$index} must be [row, col, value] (whole numbers).";
            }

            [$r, $c] = $fill;

            if (! isset($blanks["{$r}:{$c}"])) {
                return "Fill {$index} is not on a blank (\"?\") cell.";
            }

            if (isset($seen["{$r}:{$c}"])) {
                return "Fill {$index} fills the same cell twice.";
            }

            $seen["{$r}:{$c}"] = true;
        }

        return ! $partial && count($seen) !== count($blanks) ? 'Every blank must be filled.' : null;
    }

    /**
     * Whether complete, well-formed fills use exactly the bank and make every equation hold.
     *
     * @param  list<list<int|string|null>>  $cells
     * @param  list<array{operands: list<array{0: int, 1: int}>, ops: list<string>, result: array{0: int, 1: int}}>  $equations
     * @param  array<mixed>  $bank
     * @param  array<mixed>  $fills
     */
    private function solves(array $cells, array $equations, array $bank, array $fills): bool
    {
        $values = [];

        foreach ($fills as $fill) {
            /** @var array{0: int, 1: int, 2: int} $fill */
            $cells[$fill[0]][$fill[1]] = $fill[2];
            $values[] = $fill[2];
        }

        if (self::sorted($values) !== self::sorted(array_values($bank))) {
            return false;
        }

        foreach ($equations as $equation) {
            $operands = [];

            foreach ($equation['operands'] as [$r, $c]) {
                $operands[] = (int) $cells[$r][$c];
            }

            if (CrossMathSolver::evaluate($operands, $equation['ops']) !== $cells[$equation['result'][0]][$equation['result'][1]]) {
                return false;
            }
        }

        return true;
    }

    /**
     * The "?" cells, keyed "row:col".
     *
     * @param  array<mixed>  $cells
     * @return array<string, true>
     */
    private static function blanks(array $cells): array
    {
        $blanks = [];

        foreach ($cells as $r => $row) {
            foreach ((array) $row as $c => $cell) {
                if ($cell === '?') {
                    $blanks["{$r}:{$c}"] = true;
                }
            }
        }

        return $blanks;
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private static function sorted(array $values): array
    {
        sort($values);

        return $values;
    }
}
