<?php

namespace App\Modules\ChallengeTypes\Generators;

use App\Modules\ChallengeTypes\Support\ClanArt;
use Random\Randomizer;

/**
 * Cross Math (number-lattice math crossword, docs/API.md "Level-up puzzles").
 *
 * A puzzle is an (n+1)×(n+1) grid of numbers N, n = 2 (5×5 cells) or 3 (7×7 cells): every row i
 * and column j reads `N[·][0] op … op N[·][n-1] = N[·][n]`, the last row and the last column
 * included (they meet in the corner). Evaluation uses normal precedence (× ÷ before + −, then
 * left to right); every number and every intermediate value is a whole number 0..max, every ÷ is
 * exact, and × / ÷ never use 0 or 1 (they would make trivial or ambiguous equations).
 *
 * Generation: sample the n×n core (biased to "nice" products so ÷ has divisible pairs), pick a
 * valid operator for each core row and column (preferring the level's newest operator), then
 * choose the last row's and last column's operators so both give the same corner. Blanks are
 * picked at random and the puzzle is kept only when CrossMathSolver proves the arrangement of the
 * bank unique within a node budget. All work is bounded: GRID_ATTEMPTS grids × BLANK_TRIES blank
 * sets (later attempts use fewer blanks); the last resort blanks only the results of the core
 * rows, which is always unique (each row equation fixes its own result).
 */
class CrossMathGenerator
{
    /**
     * Per difficulty: [operands per equation, operators, max value, blanks]. The last operator
     * listed is the level's "new" one, preferred when picking operators.
     */
    public const array LEVELS = [
        1 => [2, ['+'], 10, 3],
        2 => [2, ['+', '-'], 10, 4],
        3 => [2, ['+', '-'], 20, 5],
        4 => [2, ['+', '-', '*'], 20, 5],
        5 => [2, ['+', '-', '*'], 50, 6],
        6 => [2, ['+', '-', '*', '/'], 50, 7],
        7 => [3, ['+', '-'], 50, 8],
        8 => [3, ['+', '-', '*'], 100, 10],
        9 => [3, ['+', '-', '*', '/'], 100, 12],
        10 => [3, ['+', '-', '*', '/'], 200, 14],
    ];

    /** Grids tried before the last-resort blanks. */
    public const int GRID_ATTEMPTS = 40;

    /** Blank sets tried per grid. */
    public const int BLANK_TRIES = 3;

    /** After this many grid attempts, each further batch of attempts uses one blank fewer. */
    public const int BLANK_STEP = 10;

    /** Search nodes allowed per uniqueness check (beyond it the blank set is rejected). */
    public const int SOLVER_BUDGET = 30000;

    /** Search nodes allowed per puzzle, all checks together (the hard bound on the work). */
    public const int TOTAL_BUDGET = 60000;

    /** Values tried for the last core cell of a grid. */
    public const int CORNER_TRIES = 30;

    public function __construct(private readonly CrossMathSolver $solver) {}

    /**
     * @return array{payload: array{rows: int, cols: int, cells: list<list<int|string|null>>, bank: list<int>}, answer: array{fills: list<array{0: int, 1: int, 2: int}>}}
     */
    public function generate(string $seed, int $difficulty): array
    {
        [$n, $ops, $max, $blanks] = self::LEVELS[max(1, min(10, $difficulty))];
        $rng = ClanArt::random('cross_math', $seed, $difficulty);
        $last = null;
        $nodesLeft = self::TOTAL_BUDGET;

        for ($attempt = 0; $attempt < self::GRID_ATTEMPTS && $nodesLeft > 0; $attempt++) {
            $grid = $this->grid($rng, $n, $ops, $max);

            if ($grid === null) {
                continue;
            }

            $last = $grid;

            if ($attempt < self::GRID_ATTEMPTS / 2 && ! self::usesNewest($grid, $ops)) {
                continue;
            }

            $target = max($n, $blanks - intdiv($attempt, self::BLANK_STEP));

            for ($try = 0; $try < self::BLANK_TRIES && $nodesLeft > 0; $try++) {
                $cells = $this->pickBlanks($rng, $n, $target, $try > 0 || 4 * $target > 3 * ($n + 1) ** 2);
                $count = $this->solver->count($grid, $cells, 2, min(self::SOLVER_BUDGET, $nodesLeft));
                $nodesLeft -= $this->solver->nodes;

                if ($count === 1) {
                    return self::build($grid, $cells);
                }
            }
        }

        $grid = $last ?? self::plainGrid($n);
        $cells = [];

        for ($i = 0; $i < $n; $i++) {
            $cells[] = [$i, $n];
        }

        return self::build($grid, $cells);
    }

    /**
     * One valid number grid with its operators, or null when this sample does not work out.
     *
     * @param  list<string>  $ops
     * @return array{n: int, numbers: list<list<int>>, row_ops: list<list<string>>, col_ops: list<list<string>>}|null
     */
    private function grid(Randomizer $rng, int $n, array $ops, int $max): ?array
    {
        $cap = $rng->getInt(max(3, intdiv($max, $n * $n)), max(3, intdiv($max, 2)));
        $last = $n - 1;
        $numbers = array_fill(0, $n + 1, array_fill(0, $n + 1, 0));
        $rowOps = array_fill(0, $n + 1, []);
        $colOps = array_fill(0, $n + 1, []);

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $numbers[$i][$j] = $this->sample($rng, $cap, $ops);
            }
        }

        // Every core row and column except the last ones (they share the last core cell).
        for ($line = 0; $line < $last; $line++) {
            $row = $this->pickOps($rng, array_slice($numbers[$line], 0, $n), $ops, $max);
            $col = $this->pickOps($rng, array_column(array_slice($numbers, 0, $n), $line), $ops, $max);

            if ($row === null || $col === null) {
                return null;
            }

            [$rowOps[$line], $numbers[$line][$n]] = $row;
            [$colOps[$line], $numbers[$n][$line]] = $col;
        }

        // The last core cell: the first value (in random order) for which the last core row and
        // column work and the last row and column agree on the corner.
        foreach (array_slice($rng->shuffleArray(range(1, $cap)), 0, self::CORNER_TRIES) as $value) {
            $numbers[$last][$last] = $value;
            $row = $this->pickOps($rng, array_slice($numbers[$last], 0, $n), $ops, $max);
            $col = $this->pickOps($rng, array_column(array_slice($numbers, 0, $n), $last), $ops, $max);

            if ($row === null || $col === null) {
                continue;
            }

            [$rowOps[$last], $numbers[$last][$n]] = $row;
            [$colOps[$last], $numbers[$n][$last]] = $col;
            $rowEnds = self::results(array_slice($numbers[$n], 0, $n), $ops, $max);
            $colEnds = self::results(array_column(array_slice($numbers, 0, $n), $n), $ops, $max);
            $corners = array_values(array_intersect(array_keys($rowEnds), array_keys($colEnds)));

            if ($corners === []) {
                continue;
            }

            sort($corners);
            $corner = $corners[$rng->getInt(0, count($corners) - 1)];
            $rowOps[$n] = $rowEnds[$corner][$rng->getInt(0, count($rowEnds[$corner]) - 1)];
            $colOps[$n] = $colEnds[$corner][$rng->getInt(0, count($colEnds[$corner]) - 1)];
            $numbers[$n][$n] = $corner;

            return ['n' => $n, 'numbers' => $numbers, 'row_ops' => $rowOps, 'col_ops' => $colOps];
        }

        return null;
    }

    /**
     * Whether the grid uses the level's newest operator (e.g. ÷ from difficulty 6).
     *
     * @param  array{n: int, numbers: list<list<int>>, row_ops: list<list<string>>, col_ops: list<list<string>>}  $grid
     * @param  list<string>  $ops
     */
    private static function usesNewest(array $grid, array $ops): bool
    {
        return in_array($ops[count($ops) - 1], array_merge(...$grid['row_ops'], ...$grid['col_ops']), true);
    }

    /**
     * A core number: often a small product (so × and ÷ find partners), else anything up to $cap.
     *
     * @param  list<string>  $ops
     */
    private function sample(Randomizer $rng, int $cap, array $ops): int
    {
        if (array_intersect($ops, ['*', '/']) !== [] && $rng->getInt(1, 10) <= 4) {
            $value = $rng->getInt(2, 9) * $rng->getInt(1, 9);

            if ($value <= $cap) {
                return $value;
            }
        }

        return $rng->getInt(1, $cap);
    }

    /**
     * A valid operator combination for these operands (the level's newest operator first half the
     * time, otherwise in random order) and its result.
     *
     * @param  list<int>  $operands
     * @param  list<string>  $ops
     * @return array{0: list<string>, 1: int}|null
     */
    private function pickOps(Randomizer $rng, array $operands, array $ops, int $max): ?array
    {
        $combos = $rng->shuffleArray(self::combos($ops, count($operands) - 1));
        $newest = $ops[count($ops) - 1];

        if (count($ops) > 1 && $rng->getInt(0, 1) === 1) {
            usort($combos, fn (array $a, array $b) => (int) in_array($newest, $b, true) <=> (int) in_array($newest, $a, true));
        }

        foreach ($combos as $combo) {
            $value = self::evaluateStrict($operands, $combo, $max);

            if ($value !== null) {
                return [$combo, $value];
            }
        }

        return null;
    }

    /**
     * Every valid result of these operands: value => operator combinations giving it.
     *
     * @param  list<int>  $operands
     * @param  list<string>  $ops
     * @return array<int, list<list<string>>>
     */
    private static function results(array $operands, array $ops, int $max): array
    {
        $results = [];

        foreach (self::combos($ops, count($operands) - 1) as $combo) {
            $value = self::evaluateStrict($operands, $combo, $max);

            if ($value !== null) {
                $results[$value][] = $combo;
            }
        }

        return $results;
    }

    /**
     * All operator sequences of a length.
     *
     * @param  list<string>  $ops
     * @return list<list<string>>
     */
    private static function combos(array $ops, int $length): array
    {
        $combos = [[]];

        for ($i = 0; $i < $length; $i++) {
            $next = [];

            foreach ($combos as $combo) {
                foreach ($ops as $op) {
                    $next[] = [...$combo, $op];
                }
            }

            $combos = $next;
        }

        return $combos;
    }

    /**
     * Evaluates with normal precedence, requiring every intermediate value and the result to be
     * a whole number 0..max, exact division, and no × / ÷ with 0 or 1. Null when any rule fails.
     *
     * @param  list<int>  $operands
     * @param  list<string>  $ops
     */
    public static function evaluateStrict(array $operands, array $ops, int $max): ?int
    {
        if (count($operands) === 2) {
            return self::apply($operands[0], $ops[0], $operands[1], $max);
        }

        [$a, $b, $c] = $operands;

        if (self::isAdditive($ops[0]) && ! self::isAdditive($ops[1])) {
            $right = self::apply($b, $ops[1], $c, $max);

            return $right === null ? null : self::apply($a, $ops[0], $right, $max);
        }

        $left = self::apply($a, $ops[0], $b, $max);

        return $left === null ? null : self::apply($left, $ops[1], $c, $max);
    }

    public static function isAdditive(string $op): bool
    {
        return $op === '+' || $op === '-';
    }

    private static function apply(int $a, string $op, int $b, int $max): ?int
    {
        $value = match ($op) {
            '+' => $a + $b,
            '-' => $a - $b,
            '*' => $a <= 1 || $b <= 1 ? null : $a * $b,
            '/' => $a === 0 || $b <= 1 || $a % $b !== 0 ? null : intdiv($a, $b),
            default => null,
        };

        return $value !== null && $value >= 0 && $value <= $max ? $value : null;
    }

    /**
     * $count number cells (grid coordinates [i, j]) to blank: at random, or ($coreGivens) keeping
     * the shown numbers in the n×n core first — results can then be worked out, and the
     * uniqueness check is much faster for nearly empty grids.
     *
     * @return list<array{0: int, 1: int}>
     */
    private function pickBlanks(Randomizer $rng, int $n, int $count, bool $coreGivens): array
    {
        $all = [];

        for ($i = 0; $i <= $n; $i++) {
            for ($j = 0; $j <= $n; $j++) {
                $all[] = [$i, $j];
            }
        }

        $all = $rng->shuffleArray($all);

        if ($coreGivens) {
            usort($all, fn (array $a, array $b) => (int) ($b[0] < $n && $b[1] < $n) <=> (int) ($a[0] < $n && $a[1] < $n));
            $picked = array_slice($all, count($all) - $count);
        } else {
            $picked = array_slice($all, 0, $count);
        }

        usort($picked, fn (array $a, array $b) => $a <=> $b);

        return $picked;
    }

    /**
     * All ones and plus signs: valid at every level (the corner is n², at most 9).
     *
     * @return array{n: int, numbers: list<list<int>>, row_ops: list<list<string>>, col_ops: list<list<string>>}
     */
    private static function plainGrid(int $n): array
    {
        $numbers = array_fill(0, $n + 1, array_fill(0, $n + 1, 1));

        for ($i = 0; $i < $n; $i++) {
            $numbers[$i][$n] = $n;
            $numbers[$n][$i] = $n;
        }

        $numbers[$n][$n] = $n * $n;
        $ops = array_fill(0, $n + 1, array_fill(0, $n - 1, '+'));

        return ['n' => $n, 'numbers' => $numbers, 'row_ops' => $ops, 'col_ops' => $ops];
    }

    /**
     * The payload cells (numbers, "?", operators, "=", null) and the fills of the blanks.
     *
     * @param  array{n: int, numbers: list<list<int>>, row_ops: list<list<string>>, col_ops: list<list<string>>}  $grid
     * @param  list<array{0: int, 1: int}>  $blanks
     * @return array{payload: array{rows: int, cols: int, cells: list<list<int|string|null>>, bank: list<int>}, answer: array{fills: list<array{0: int, 1: int, 2: int}>}}
     */
    private static function build(array $grid, array $blanks): array
    {
        $n = $grid['n'];
        $size = 2 * $n + 1;
        $blank = [];

        foreach ($blanks as [$i, $j]) {
            $blank["{$i}:{$j}"] = true;
        }

        $cells = [];
        $fills = [];
        $bank = [];

        for ($r = 0; $r < $size; $r++) {
            $row = [];

            for ($c = 0; $c < $size; $c++) {
                $row[] = match (true) {
                    $r % 2 === 0 && $c % 2 === 0 => isset($blank[($r / 2).':'.($c / 2)]) ? '?' : $grid['numbers'][$r / 2][$c / 2],
                    $r % 2 === 0 => intdiv($c, 2) === $n - 1 ? '=' : $grid['row_ops'][$r / 2][intdiv($c, 2)],
                    $c % 2 === 0 => intdiv($r, 2) === $n - 1 ? '=' : $grid['col_ops'][$c / 2][intdiv($r, 2)],
                    default => null,
                };

                if ($row[$c] === '?') {
                    $value = $grid['numbers'][$r / 2][$c / 2];
                    $fills[] = [$r, $c, $value];
                    $bank[] = $value;
                }
            }

            $cells[] = $row;
        }

        sort($bank);

        return [
            'payload' => ['rows' => $size, 'cols' => $size, 'cells' => $cells, 'bank' => $bank],
            'answer' => ['fills' => $fills],
        ];
    }
}
