<?php

namespace App\Modules\ChallengeTypes\Generators;

/**
 * Counts the ways to place a Cross Math bank into the blanks so that every equation holds
 * (exact arithmetic with normal precedence, like the answer check — see evaluate()).
 *
 * The search follows a fixed schedule: core cells row by row (a blank core cell branches over
 * the distinct bank values left), and as soon as an equation's operands are known its result is
 * forced (a blank result must take that value from the bank, a given one must match) or, when
 * the result is already known, the equation is checked. Each blank is assigned exactly once, so
 * a complete schedule uses up the bank. The count stops at $limit; a search that visits more
 * than $budget nodes returns null (undecided), which callers treat as "not unique".
 */
class CrossMathSolver
{
    /** @var list<array{0: string, 1: int}> */
    private array $steps = [];

    /** @var list<array{operands: list<int>, result: int, ops: list<string>}> */
    private array $equations = [];

    /** @var array<int, int|null> */
    private array $values = [];

    /** @var array<int, bool> */
    private array $isBlank = [];

    /** @var array<int, int> value => copies left */
    private array $bank = [];

    private int $stepCount = 0;

    private int $found = 0;

    /** Search nodes visited by the last count() call. */
    public private(set) int $nodes = 0;

    private int $limit = 2;

    private int $budget = 0;

    /**
     * @param  array{n: int, numbers: list<list<int>>, row_ops: list<list<string>>, col_ops: list<list<string>>}  $grid
     * @param  list<array{0: int, 1: int}>  $blanks  grid coordinates [i, j] of the numbers to hide
     */
    public function count(array $grid, array $blanks, int $limit, int $budget): ?int
    {
        $n = $grid['n'];
        $m = $n + 1;
        $this->values = [];
        $this->isBlank = [];
        $this->bank = [];

        for ($i = 0; $i < $m; $i++) {
            for ($j = 0; $j < $m; $j++) {
                $this->values[$i * $m + $j] = $grid['numbers'][$i][$j];
            }
        }

        foreach ($blanks as [$i, $j]) {
            $index = $i * $m + $j;
            $value = (int) $this->values[$index];
            $this->bank[$value] = ($this->bank[$value] ?? 0) + 1;
            $this->values[$index] = null;
            $this->isBlank[$index] = true;
        }

        ksort($this->bank);
        $this->equations = [];

        for ($line = 0; $line < $m; $line++) {
            $this->equations[] = ['operands' => array_map(fn (int $j) => $line * $m + $j, range(0, $n - 1)), 'result' => $line * $m + $n, 'ops' => $grid['row_ops'][$line]];
            $this->equations[] = ['operands' => array_map(fn (int $i) => $i * $m + $line, range(0, $n - 1)), 'result' => $n * $m + $line, 'ops' => $grid['col_ops'][$line]];
        }

        $this->schedule($n);
        $this->stepCount = count($this->steps);
        $this->found = 0;
        $this->nodes = 0;
        $this->limit = $limit;
        $this->budget = $budget;
        $this->search(0);

        return $this->nodes > $this->budget ? null : $this->found;
    }

    /**
     * Value of `operands[0] ops[0] operands[1] …` with normal precedence in exact arithmetic
     * (intermediate fractions allowed), or null when it is not a whole number or divides by 0.
     *
     * @param  list<int>  $operands  2 or 3 numbers
     * @param  list<string>  $ops
     */
    public static function evaluate(array $operands, array $ops): ?int
    {
        if (count($operands) === 2) {
            return self::exact($operands[0], $ops[0], $operands[1]);
        }

        [$a, $b, $c] = $operands;
        $additive = [CrossMathGenerator::isAdditive($ops[0]), CrossMathGenerator::isAdditive($ops[1])];

        if (! $additive[0] && ! $additive[1]) {
            // (a op b) op c as a fraction: 7 ÷ 2 × 2 = 7.
            [$num, $den] = $ops[0] === '*' ? [$a * $b, 1] : [$a, $b];
            [$num, $den] = $ops[1] === '*' ? [$num * $c, $den] : [$num, $den * $c];

            return $den === 0 || $num % $den !== 0 ? null : intdiv($num, $den);
        }

        if ($additive[0] && ! $additive[1]) {
            $right = self::exact($b, $ops[1], $c);

            return $right === null ? null : self::exact($a, $ops[0], $right);
        }

        $left = self::exact($a, $ops[0], $b);

        return $left === null ? null : self::exact($left, $ops[1], $c);
    }

    private static function exact(int $a, string $op, int $b): ?int
    {
        return match ($op) {
            '+' => $a + $b,
            '-' => $a - $b,
            '*' => $a * $b,
            '/' => $b === 0 || $a % $b !== 0 ? null : intdiv($a, $b),
            default => null,
        };
    }

    /**
     * Given core cells first, then the blank ones in a staircase order (row t from column t, then
     * column t below it) so equations close early; after each cell, force or check every
     * equation whose operands are all known.
     */
    private function schedule(int $n): void
    {
        $m = $n + 1;
        $order = [];

        for ($t = 0; $t < $n; $t++) {
            for ($j = $t; $j < $n; $j++) {
                $order[] = $t * $m + $j;
            }

            for ($i = $t + 1; $i < $n; $i++) {
                $order[] = $i * $m + $t;
            }
        }

        usort($order, fn (int $a, int $b) => (int) isset($this->isBlank[$a]) <=> (int) isset($this->isBlank[$b]));
        $known = [];
        $done = [];
        $this->steps = [];

        foreach ($order as $index) {
            $known[$index] = true;

            if (isset($this->isBlank[$index])) {
                $this->steps[] = ['branch', $index];
            }

            do {
                $changed = false;

                foreach ($this->equations as $e => $equation) {
                    if (isset($done[$e]) || array_diff($equation['operands'], array_keys($known)) !== []) {
                        continue;
                    }

                    $this->steps[] = [isset($known[$equation['result']]) ? 'check' : 'force', $e];
                    $known[$equation['result']] = true;
                    $done[$e] = $changed = true;
                }
            } while ($changed);
        }
    }

    private function search(int $step): void
    {
        if ($this->found >= $this->limit || $this->nodes > $this->budget) {
            return;
        }

        $this->nodes++;

        if ($step === $this->stepCount) {
            $this->found++;

            return;
        }

        [$kind, $target] = $this->steps[$step];

        if ($kind === 'branch') {
            foreach ($this->bank as $value => $left) {
                if ($left > 0) {
                    $this->bank[$value]--;
                    $this->values[$target] = $value;
                    $this->search($step + 1);
                    $this->bank[$value]++;
                    $this->values[$target] = null;
                }
            }

            return;
        }

        $equation = $this->equations[$target];
        $operands = [];

        foreach ($equation['operands'] as $index) {
            $operands[] = (int) $this->values[$index];
        }

        $value = self::evaluate($operands, $equation['ops']);

        if ($value === null) {
            return;
        }

        $result = $equation['result'];

        if ($kind === 'check' || ! isset($this->isBlank[$result])) {
            if ($this->values[$result] === $value) {
                $this->search($step + 1);
            }

            return;
        }

        if (($this->bank[$value] ?? 0) > 0) {
            $this->bank[$value]--;
            $this->values[$result] = $value;
            $this->search($step + 1);
            $this->bank[$value]++;
            $this->values[$result] = null;
        }
    }
}
