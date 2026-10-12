<?php

namespace App\Modules\ChallengeTypes\Support;

/**
 * One generator attached to a category (`categories.generators` JSON list):
 * `{ "type": "sudoku", "options": { "size": 4 }, "min_difficulty": 1, "max_difficulty": 3 }`.
 * The difficulty range says which members it suits (overlap with their policy range).
 */
final readonly class GeneratorSpec
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public int $categoryId,
        public string $type,
        public array $options,
        public int $minDifficulty,
        public int $maxDifficulty,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(int $categoryId, array $raw): ?self
    {
        $type = $raw['type'] ?? null;

        if (! is_string($type) || $type === '') {
            return null;
        }

        $min = max(1, min(10, (int) ($raw['min_difficulty'] ?? 1)));
        $max = max($min, min(10, (int) ($raw['max_difficulty'] ?? 10)));

        return new self($categoryId, $type, is_array($raw['options'] ?? null) ? $raw['options'] : [], $min, $max);
    }

    /**
     * Distance between this spec's difficulty range and [min, max] (0 = they overlap).
     */
    public function distanceTo(int $min, int $max): int
    {
        return match (true) {
            $max < $this->minDifficulty => $this->minDifficulty - $max,
            $min > $this->maxDifficulty => $min - $this->maxDifficulty,
            default => 0,
        };
    }

    /**
     * The difficulty range to generate in for a member range: the overlap, or the spec's
     * nearest edge when they do not overlap.
     *
     * @return array{0: int, 1: int}
     */
    public function rangeFor(int $min, int $max): array
    {
        $low = max($min, $this->minDifficulty);
        $high = min($max, $this->maxDifficulty);

        if ($low <= $high) {
            return [$low, $high];
        }

        $edge = $max < $this->minDifficulty ? $this->minDifficulty : $this->maxDifficulty;

        return [$edge, $edge];
    }
}
