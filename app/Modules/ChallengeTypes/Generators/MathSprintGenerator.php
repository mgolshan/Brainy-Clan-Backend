<?php

namespace App\Modules\ChallengeTypes\Generators;

use App\Modules\ChallengeTypes\Support\Rational;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Seeded math questions, scaled by difficulty (1–10; the member's policy range picks it):
 *
 * - `times_tables`: 1–5 tables → up to 12×12 → division facts and bigger tables.
 * - `mental`: small sums → two/three-digit ± → ×/÷ → percentages → decimals → squares,
 *   brackets and negative results.
 * - `fractions`: same-denominator sums → simplifying → "1/4 of 20" → different denominators →
 *   multiplying and dividing fractions.
 *
 * Same seed + mode + difficulty → same question.
 */
class MathSprintGenerator
{
    public const array MODES = ['times_tables', 'mental', 'fractions'];

    /**
     * @return array{question: string, answer_format: 'integer'|'decimal'|'fraction', time_limit_ms: int, value: Rational, reduced_only: bool}
     */
    public function generate(string $seed, string $mode, int $difficulty): array
    {
        $difficulty = max(1, min(10, $difficulty));
        $rng = new Randomizer(new Xoshiro256StarStar(hash('sha256', "math|{$mode}|{$difficulty}|{$seed}", true)));

        [$question, $value, $format] = match ($mode) {
            'mental' => $this->mental($rng, $difficulty),
            'fractions' => $this->fractions($rng, $difficulty),
            default => $this->timesTables($rng, $difficulty),
        };

        return [
            'question' => $question,
            'answer_format' => $format,
            'time_limit_ms' => $this->timeLimit($mode, $difficulty),
            'value' => $value,
            'reduced_only' => str_starts_with($question, 'Simplify '),
        ];
    }

    /**
     * Advisory countdown in milliseconds (the app hides it for calm timers).
     */
    private function timeLimit(string $mode, int $difficulty): int
    {
        return match ($mode) {
            'mental' => 15000 + 2000 * max(0, $difficulty - 4),
            'fractions' => 30000 + 3000 * max(0, $difficulty - 4),
            default => $difficulty <= 2 ? 12000 : 10000,
        };
    }

    /**
     * @return array{0: string, 1: Rational, 2: 'integer'}
     */
    private function timesTables(Randomizer $rng, int $d): array
    {
        [$aMin, $aMax, $bMin, $bMax] = match (true) {
            $d <= 1 => [1, 5, 1, 5],
            $d <= 2 => [2, 5, 1, 10],
            $d <= 3 => [2, 10, 2, 10],
            $d <= 6 => [2, 12, 2, 12],
            default => [3, 15, 3, 12],
        };
        $a = $rng->getInt($aMin, $aMax);
        $b = $rng->getInt($bMin, $bMax);

        if ($d >= 5 && $rng->getInt(0, 1) === 1) {
            return [($a * $b).' ÷ '.$a, new Rational($b), 'integer'];
        }

        return ["{$a} × {$b}", new Rational($a * $b), 'integer'];
    }

    /**
     * @return array{0: string, 1: Rational, 2: 'integer'|'decimal'}
     */
    private function mental(Randomizer $rng, int $d): array
    {
        $plusOrMinus = function (int $a, int $b) use ($rng): array {
            if ($rng->getInt(0, 1) === 1 && $a >= $b) {
                return ["{$a} - {$b}", new Rational($a - $b), 'integer'];
            }

            return ["{$a} + {$b}", new Rational($a + $b), 'integer'];
        };

        switch ($d) {
            case 1:
                $a = $rng->getInt(1, 9);
                $b = $rng->getInt(1, 9);

                return ["{$a} + {$b}", new Rational($a + $b), 'integer'];
            case 2:
                return $plusOrMinus($rng->getInt(10, 20), $rng->getInt(1, 9));
            case 3:
                return $plusOrMinus($rng->getInt(20, 99), $rng->getInt(10, 19));
            case 4:
                if ($rng->getInt(0, 2) === 0) {
                    $a = $rng->getInt(2, 9);
                    $b = $rng->getInt(11, 20);

                    return ["{$a} × {$b}", new Rational($a * $b), 'integer'];
                }

                return $plusOrMinus($rng->getInt(25, 99), $rng->getInt(25, 99));
            case 5:
                if ($rng->getInt(0, 1) === 1) {
                    $divisor = $rng->getInt(2, 9);
                    $quotient = $rng->getInt(11, 25);

                    return [($divisor * $quotient)." ÷ {$divisor}", new Rational($quotient), 'integer'];
                }

                return $plusOrMinus($rng->getInt(100, 999), $rng->getInt(10, 99));
            case 6:
                $percent = $rng->pickArrayKeys([10 => 1, 20 => 1, 25 => 1, 50 => 1, 75 => 1], 1)[0];
                $step = intdiv(100, $this->gcd($percent, 100));
                $base = $step * $rng->getInt(1, max(1, intdiv(400, $step)));

                return ["{$percent}% of {$base}", new Rational(intdiv($percent * $base, 100)), 'integer'];
            case 7:
                $a = new Rational($rng->getInt(11, 99), 10);
                $b = new Rational($rng->getInt(101, 999), 100);
                $minus = $rng->getInt(0, 1) === 1 && $a->subtract($b)->numerator >= 0;

                return [
                    $a->toDecimalString().($minus ? ' - ' : ' + ').$b->toDecimalString(),
                    $minus ? $a->subtract($b) : $a->add($b),
                    'decimal',
                ];
            case 8:
                if ($rng->getInt(0, 1) === 1) {
                    $n = $rng->getInt(11, 20);

                    return ["{$n}²", new Rational($n * $n), 'integer'];
                }

                $a = $rng->getInt(5, 30);
                $b = $rng->getInt(5, 30);
                $c = $rng->getInt(2, 9);

                return ["({$a} + {$b}) × {$c}", new Rational(($a + $b) * $c), 'integer'];
            case 9:
                if ($rng->getInt(0, 1) === 1) {
                    $a = $rng->getInt(10, 50);
                    $b = $rng->getInt($a + 1, $a + 60);

                    return ["{$a} - {$b}", new Rational($a - $b), 'integer'];
                }

                $factor = new Rational($rng->getInt(1, 9) * 2 + 1, 2);
                $n = $rng->getInt(4, 30);

                return [$factor->toDecimalString()." × {$n}", $factor->multiply(new Rational($n)), 'decimal'];
            default:
                $a = $rng->getInt(12, 29);
                $b = $rng->getInt(12, 29);

                return ["{$a} × {$b}", new Rational($a * $b), 'integer'];
        }
    }

    /**
     * @return array{0: string, 1: Rational, 2: 'integer'|'fraction'}
     */
    private function fractions(Randomizer $rng, int $d): array
    {
        $denominators = [2, 3, 4, 5, 6, 8, 10];

        if ($d <= 2) {
            $den = $rng->getInt(3, 10);
            $a = $rng->getInt(1, $den - 2);
            $b = $rng->getInt(1, $den - 1 - $a);

            return ["{$a}/{$den} + {$b}/{$den}", new Rational($a + $b, $den), 'fraction'];
        }

        if ($d === 3) {
            if ($rng->getInt(0, 1) === 1) {
                $den = $rng->getInt(4, 12);
                $a = $rng->getInt(2, $den - 1);
                $b = $rng->getInt(1, $a - 1);

                return ["{$a}/{$den} - {$b}/{$den}", new Rational($a - $b, $den), 'fraction'];
            }

            do {
                $q = $rng->getInt(2, 9);
                $p = $rng->getInt(1, $q - 1);
            } while ($this->gcd($p, $q) !== 1);
            $k = $rng->getInt(2, 5);

            return ['Simplify '.($p * $k).'/'.($q * $k), new Rational($p, $q), 'fraction'];
        }

        if ($d === 4) {
            $den = $rng->pickArrayKeys(array_flip([2, 3, 4, 5, 10]), 1)[0];
            $whole = $den * $rng->getInt(2, 12);

            return ["1/{$den} of {$whole}", new Rational(intdiv($whole, $den)), 'integer'];
        }

        $x = $this->randomFraction($rng, $denominators);
        $y = $this->randomFraction($rng, $denominators);

        if ($d <= 6) {
            return ["{$x->toFractionString()} + {$y->toFractionString()}", $x->add($y), 'fraction'];
        }

        if ($d === 7) {
            [$big, $small] = $x->subtract($y)->numerator >= 0 ? [$x, $y] : [$y, $x];

            if ($big->equals($small)) {
                $big = $big->add(new Rational(1, 2));
            }

            return ["{$big->toFractionString()} - {$small->toFractionString()}", $big->subtract($small), 'fraction'];
        }

        if ($d === 8) {
            return ["{$x->toFractionString()} × {$y->toFractionString()}", $x->multiply($y), 'fraction'];
        }

        if ($rng->getInt(0, 1) === 1) {
            $den = $rng->pickArrayKeys(array_flip([3, 4, 5, 6, 8]), 1)[0];
            $num = $rng->getInt(2, $den - 1);
            $whole = $den * $rng->getInt(3, 15);

            return ["{$num}/{$den} of {$whole}", new Rational(intdiv($num * $whole, $den)), 'integer'];
        }

        return ["{$x->toFractionString()} ÷ {$y->toFractionString()}", $x->divide($y), 'fraction'];
    }

    /**
     * A proper fraction (in lowest terms) with one of the given denominators.
     *
     * @param  list<int>  $denominators
     */
    private function randomFraction(Randomizer $rng, array $denominators): Rational
    {
        do {
            $den = $denominators[$rng->getInt(0, count($denominators) - 1)];
            $num = $rng->getInt(1, $den - 1);
        } while ($this->gcd($num, $den) !== 1);

        return new Rational($num, $den);
    }

    private function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a;
    }
}
