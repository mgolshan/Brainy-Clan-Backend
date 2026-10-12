<?php

namespace App\Modules\ChallengeTypes\Support;

use InvalidArgumentException;

/**
 * An exact fraction (always reduced, positive denominator), for checking math answers without
 * floating-point surprises: "3/4" = "6/8" = "0.75".
 */
final readonly class Rational
{
    public int $numerator;

    public int $denominator;

    public function __construct(int $numerator, int $denominator = 1)
    {
        if ($denominator === 0) {
            throw new InvalidArgumentException('Denominator cannot be zero.');
        }

        if ($denominator < 0) {
            $numerator = -$numerator;
            $denominator = -$denominator;
        }

        $gcd = self::gcd(abs($numerator), $denominator);
        $this->numerator = intdiv($numerator, $gcd);
        $this->denominator = intdiv($denominator, $gcd);
    }

    /**
     * Parses "56", "-3", "0.75", "0,75", "3/4", "1 1/4" (mixed number). Returns null when the
     * text is not a number in one of those notations. `reduced` tells whether a typed fraction is in
     * lowest terms ("7/8", "1 1/2"; not "14/16"); integers and decimals count as reduced.
     *
     * @return array{value: self, notation: 'integer'|'decimal'|'fraction', reduced: bool}|null
     */
    public static function parse(string $text): ?array
    {
        $text = trim(str_replace(['−', '–', ','], ['-', '-', '.'], $text));

        if ($text === '' || strlen($text) > 32) {
            return null;
        }

        if (preg_match('/^([+-]?)(\d{1,9})$/', $text, $m) === 1) {
            return ['value' => new self(self::signed($m[1], (int) $m[2])), 'notation' => 'integer', 'reduced' => true];
        }

        if (preg_match('/^([+-]?)(\d{0,9})\.(\d{1,9})$/', $text, $m) === 1) {
            $scale = 10 ** strlen($m[3]);
            $numerator = (int) $m[2] * $scale + (int) $m[3];

            return ['value' => new self(self::signed($m[1], $numerator), $scale), 'notation' => 'decimal', 'reduced' => true];
        }

        if (preg_match('/^([+-]?)(?:(\d{1,6})\s+)?(\d{1,6})\s*\/\s*(\d{1,6})$/', $text, $m) === 1 && (int) $m[4] !== 0) {
            $whole = $m[2] !== '' ? (int) $m[2] : 0;
            $numerator = $whole * (int) $m[4] + (int) $m[3];

            return ['value' => new self(self::signed($m[1], $numerator), (int) $m[4]), 'notation' => 'fraction', 'reduced' => self::gcd((int) $m[3], (int) $m[4]) === 1];
        }

        return null;
    }

    public function add(self $other): self
    {
        return new self($this->numerator * $other->denominator + $other->numerator * $this->denominator, $this->denominator * $other->denominator);
    }

    public function subtract(self $other): self
    {
        return $this->add(new self(-$other->numerator, $other->denominator));
    }

    public function multiply(self $other): self
    {
        return new self($this->numerator * $other->numerator, $this->denominator * $other->denominator);
    }

    public function divide(self $other): self
    {
        return new self($this->numerator * $other->denominator, $this->denominator * $other->numerator);
    }

    public function equals(self $other): bool
    {
        return $this->numerator === $other->numerator && $this->denominator === $other->denominator;
    }

    public function isInteger(): bool
    {
        return $this->denominator === 1;
    }

    /**
     * Whether the value has a finite decimal expansion (denominator only has factors 2 and 5).
     */
    public function isTerminatingDecimal(): bool
    {
        $d = $this->denominator;

        foreach ([2, 5] as $factor) {
            while ($d % $factor === 0) {
                $d = intdiv($d, $factor);
            }
        }

        return $d === 1;
    }

    /**
     * "3/4", or "2" for whole numbers.
     */
    public function toFractionString(): string
    {
        return $this->isInteger() ? (string) $this->numerator : $this->numerator.'/'.$this->denominator;
    }

    /**
     * "0.75" (only exact for terminating decimals; otherwise rounded to 6 places).
     */
    public function toDecimalString(): string
    {
        if ($this->isInteger()) {
            return (string) $this->numerator;
        }

        $formatted = rtrim(rtrim(number_format($this->numerator / $this->denominator, 6, '.', ''), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }

    private static function signed(string $sign, int $value): int
    {
        return $sign === '-' ? -$value : $value;
    }

    private static function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return max(1, $a);
    }
}
