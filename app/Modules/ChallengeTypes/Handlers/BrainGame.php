<?php

namespace App\Modules\ChallengeTypes\Handlers;

use App\Modules\ChallengeTypes\Contracts\ChallengeType;
use App\Modules\ChallengeTypes\Contracts\GeneratesItems;
use App\Modules\Content\Models\ContentItem;

/**
 * Shared plumbing of the brain games (memory_match, pattern_sequence, nonogram,
 * spot_difference): server-generated skill games whose payload gives the app everything it
 * needs to play; the server replays/verifies the submitted moves (docs/API.md "Brain games").
 * They take no generator options: the difficulty decides the size.
 */
abstract class BrainGame implements ChallengeType, GeneratesItems
{
    /** The highest difficulty level. */
    protected const int MAX_DIFFICULTY = 10;

    public function explanation(ContentItem $item): ?string
    {
        return null;
    }

    public function validateOptions(array $options): array
    {
        unset($options['locale']);

        return $options === [] ? [] : ['options' => ["{$this->name()} takes no options (the difficulty sets the size)."]];
    }

    /**
     * CSV: `payload` and `answer` as JSON objects (brain games are normally generated per session).
     */
    public function fromFlatRow(array $row): array
    {
        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        $answer = json_decode((string) ($row['answer'] ?? ''), true);

        return [
            'payload' => is_array($payload) ? $payload : [],
            'answer' => is_array($answer) ? $answer : [],
        ];
    }

    public function flatColumns(): array
    {
        return ['payload', 'answer'];
    }

    protected static function clampDifficulty(int $difficulty): int
    {
        return max(1, min(self::MAX_DIFFICULTY, $difficulty));
    }

    /**
     * @phpstan-assert-if-true list<int> $value
     */
    protected static function isIntList(mixed $value, int $min, int $max): bool
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $entry) {
            if (! is_int($entry) || $entry < $min || $entry > $max) {
                return false;
            }
        }

        return true;
    }

    /**
     * @phpstan-assert-if-true list<string> $value
     */
    protected static function isStringList(mixed $value): bool
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $entry) {
            if (! is_string($entry) || $entry === '') {
                return false;
            }
        }

        return true;
    }
}
