<?php

namespace App\Modules\ChallengeTypes\Generators;

use InvalidArgumentException;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Seeded Sudoku generator and solver for 4×4 (2×2 boxes), 6×6 (2×3) and 9×9 (3×3).
 *
 * Generation: fill a random complete grid (backtracking with random digit order), then remove
 * cells in random order as long as the puzzle keeps exactly one solution, until the target number
 * of givens is reached. Same seed + size + givens → same puzzle. Grids are row arrays, 0 = empty.
 *
 * The solver uses bitmasks per row/column/box and always branches on the cell with the fewest
 * candidates, which keeps 9×9 uniqueness checks to well under a millisecond each.
 */
class SudokuGenerator
{
    /** Box shape [rows, cols] per size. */
    public const array BOXES = [4 => [2, 2], 6 => [2, 3], 9 => [3, 3]];

    /** @var array<int, array{rows: list<int>, cols: list<int>, boxes: list<int>}> */
    private static array $layouts = [];

    /** @var array<int, int> */
    private static array $popcount = [];

    /**
     * @return array{grid: list<list<int>>, solution: list<list<int>>, givens: int}
     */
    public function generate(string $seed, int $size, int $givens): array
    {
        $this->assertSize($size);
        $rng = new Randomizer(new Xoshiro256StarStar(hash('sha256', "sudoku|{$size}|{$seed}", true)));
        $cellCount = $size * $size;

        $solution = $this->fill($size, $rng);
        $puzzle = $solution;
        $remaining = $cellCount;
        $target = max(0, min($cellCount, $givens));

        foreach ($rng->shuffleArray(range(0, $cellCount - 1)) as $cell) {
            if ($remaining <= $target) {
                break;
            }

            $digit = $puzzle[$cell];
            $puzzle[$cell] = 0;

            if ($this->countSolutionsFlat($puzzle, $size, 2) === 1) {
                $remaining--;
            } else {
                $puzzle[$cell] = $digit;
            }
        }

        return [
            'grid' => $this->toRows($puzzle, $size),
            'solution' => $this->toRows($solution, $size),
            'givens' => $remaining,
        ];
    }

    /**
     * Number of solutions of a puzzle, counting up to $limit.
     *
     * @param  list<list<int>>  $grid
     */
    public function countSolutions(array $grid, int $limit = 2): int
    {
        $size = count($grid);
        $this->assertSize($size);

        return $this->countSolutionsFlat($this->toFlat($grid), $size, $limit);
    }

    /**
     * Whether $grid is a complete, valid Sudoku of its size: every row, column and box holds
     * 1..size exactly once.
     *
     * @param  list<list<int>>  $grid
     */
    public function isValidSolution(array $grid): bool
    {
        $size = count($grid);

        if (! isset(self::BOXES[$size])) {
            return false;
        }

        $layout = $this->layout($size);
        $full = (1 << $size) - 1;
        $rows = $cols = $boxes = array_fill(0, $size, 0);
        $flat = $this->toFlat($grid);

        if (count($flat) !== $size * $size) {
            return false;
        }

        foreach ($flat as $cell => $digit) {
            if ($digit < 1 || $digit > $size) {
                return false;
            }

            $bit = 1 << ($digit - 1);
            $r = $layout['rows'][$cell];
            $c = $layout['cols'][$cell];
            $b = $layout['boxes'][$cell];

            if (($rows[$r] & $bit) !== 0 || ($cols[$c] & $bit) !== 0 || ($boxes[$b] & $bit) !== 0) {
                return false;
            }

            $rows[$r] |= $bit;
            $cols[$c] |= $bit;
            $boxes[$b] |= $bit;
        }

        return array_sum($rows) === $full * $size;
    }

    /**
     * @return list<int>
     */
    private function fill(int $size, Randomizer $rng): array
    {
        $cells = array_fill(0, $size * $size, 0);
        $count = 0;
        $this->search($cells, $size, 1, $count, $rng);

        return $cells;
    }

    /**
     * @param  array<int, int>  $cells
     */
    private function countSolutionsFlat(array $cells, int $size, int $limit): int
    {
        $layout = $this->layout($size);
        $rows = $cols = $boxes = array_fill(0, $size, 0);

        foreach ($cells as $cell => $digit) {
            if ($digit === 0) {
                continue;
            }

            $bit = 1 << ($digit - 1);
            $r = $layout['rows'][$cell];
            $c = $layout['cols'][$cell];
            $b = $layout['boxes'][$cell];

            if ((($rows[$r] | $cols[$c] | $boxes[$b]) & $bit) !== 0) {
                return 0;
            }

            $rows[$r] |= $bit;
            $cols[$c] |= $bit;
            $boxes[$b] |= $bit;
        }

        $count = 0;
        $this->searchMasked($cells, $size, $rows, $cols, $boxes, $limit, $count, null);

        return $count;
    }

    /**
     * Fills $cells in place with the first solution found (random digit order when $rng is given).
     *
     * @param  array<int, int>  $cells
     */
    private function search(array &$cells, int $size, int $limit, int &$count, ?Randomizer $rng): void
    {
        $layout = $this->layout($size);
        $rows = $cols = $boxes = array_fill(0, $size, 0);

        foreach ($cells as $cell => $digit) {
            if ($digit !== 0) {
                $bit = 1 << ($digit - 1);
                $rows[$layout['rows'][$cell]] |= $bit;
                $cols[$layout['cols'][$cell]] |= $bit;
                $boxes[$layout['boxes'][$cell]] |= $bit;
            }
        }

        $this->searchMasked($cells, $size, $rows, $cols, $boxes, $limit, $count, $rng, keepFirst: true);
    }

    /**
     * Depth-first search choosing the empty cell with the fewest candidates. Counts solutions up
     * to $limit; with $keepFirst the first solution is left in $cells.
     *
     * @param  array<int, int>  $cells
     * @param  array<int, int>  $rows
     * @param  array<int, int>  $cols
     * @param  array<int, int>  $boxes
     */
    private function searchMasked(array &$cells, int $size, array &$rows, array &$cols, array &$boxes, int $limit, int &$count, ?Randomizer $rng, bool $keepFirst = false): bool
    {
        $layout = $this->layout($size);
        $full = (1 << $size) - 1;
        $popcount = $this->popcounts();
        $best = -1;
        $bestMask = 0;
        $bestCount = PHP_INT_MAX;

        foreach ($cells as $cell => $digit) {
            if ($digit !== 0) {
                continue;
            }

            $mask = $full & ~($rows[$layout['rows'][$cell]] | $cols[$layout['cols'][$cell]] | $boxes[$layout['boxes'][$cell]]);
            $candidates = $popcount[$mask];

            if ($candidates === 0) {
                return false;
            }

            if ($candidates < $bestCount) {
                $best = $cell;
                $bestMask = $mask;
                $bestCount = $candidates;

                if ($candidates === 1) {
                    break;
                }
            }
        }

        if ($best === -1) {
            $count++;

            return $keepFirst || $count >= $limit;
        }

        $digits = [];

        for ($d = 1; $d <= $size; $d++) {
            if (($bestMask & (1 << ($d - 1))) !== 0) {
                $digits[] = $d;
            }
        }

        if ($rng !== null) {
            $digits = $rng->shuffleArray($digits);
        }

        $r = $layout['rows'][$best];
        $c = $layout['cols'][$best];
        $b = $layout['boxes'][$best];

        foreach ($digits as $digit) {
            $bit = 1 << ($digit - 1);
            $cells[$best] = $digit;
            $rows[$r] |= $bit;
            $cols[$c] |= $bit;
            $boxes[$b] |= $bit;

            $stop = $this->searchMasked($cells, $size, $rows, $cols, $boxes, $limit, $count, $rng, $keepFirst);

            if ($stop && $keepFirst) {
                return true;
            }

            $cells[$best] = 0;
            $rows[$r] &= ~$bit;
            $cols[$c] &= ~$bit;
            $boxes[$b] &= ~$bit;

            if ($stop) {
                return true;
            }
        }

        return false;
    }

    /**
     * Row, column and box index of every cell.
     *
     * @return array{rows: list<int>, cols: list<int>, boxes: list<int>}
     */
    private function layout(int $size): array
    {
        if (isset(self::$layouts[$size])) {
            return self::$layouts[$size];
        }

        [$boxRows, $boxCols] = self::BOXES[$size];
        $layout = ['rows' => [], 'cols' => [], 'boxes' => []];

        for ($cell = 0; $cell < $size * $size; $cell++) {
            $r = intdiv($cell, $size);
            $c = $cell % $size;
            $layout['rows'][] = $r;
            $layout['cols'][] = $c;
            $layout['boxes'][] = intdiv($r, $boxRows) * intdiv($size, $boxCols) + intdiv($c, $boxCols);
        }

        return self::$layouts[$size] = $layout;
    }

    /**
     * @return array<int, int>
     */
    private function popcounts(): array
    {
        if (self::$popcount === []) {
            for ($mask = 0; $mask < 512; $mask++) {
                self::$popcount[$mask] = substr_count(decbin($mask), '1');
            }
        }

        return self::$popcount;
    }

    /**
     * @param  list<list<int>>  $grid
     * @return list<int>
     */
    private function toFlat(array $grid): array
    {
        $flat = [];

        foreach ($grid as $row) {
            foreach ($row as $value) {
                $flat[] = (int) $value;
            }
        }

        return $flat;
    }

    /**
     * @param  list<int>  $flat
     * @return list<list<int>>
     */
    private function toRows(array $flat, int $size): array
    {
        return array_chunk($flat, $size);
    }

    private function assertSize(int $size): void
    {
        if (! isset(self::BOXES[$size])) {
            throw new InvalidArgumentException("Unsupported Sudoku size [{$size}].");
        }
    }
}
