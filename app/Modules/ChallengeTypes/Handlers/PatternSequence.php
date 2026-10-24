<?php

namespace App\Modules\ChallengeTypes\Handlers;

use App\Modules\ChallengeTypes\Support\ClanArt;
use App\Modules\Content\Models\ContentItem;

/**
 * Pattern sequence ("Simon"): the app plays a sequence on pads, the member repeats it.
 *
 * payload:  { "pads": ["fox","owl","frog","panda"], "sequence": [2,0,3,1,…], "show_ms": 700 }
 * answer:   { }                                       (the sequence is the puzzle)
 * response: { "input": [2,0,3,1,…] }
 *
 * Correct when `input` equals `sequence` exactly. Pads are Clan kid avatar keys: 3 (difficulty
 * 1–2), 4 (3–6), 5 (7–8) or 6 (9–10); length 3–9; no pad three times in a row; `show_ms` (how
 * long each step lights up) goes from 900 down to 500. The app may slow it down for members with
 * calm timers; the server only sends it. Worth 1 point (length ≤ 4), 2 (5–6) or 3 (7+).
 */
class PatternSequence extends BrainGame
{
    /** Sequence length per difficulty 1–10. */
    public const array LENGTHS = [1 => 3, 2 => 3, 3 => 4, 4 => 4, 5 => 5, 6 => 6, 7 => 6, 8 => 7, 9 => 8, 10 => 9];

    /** Pads per difficulty 1–10. */
    public const array PADS = [1 => 3, 2 => 3, 3 => 4, 4 => 4, 5 => 4, 6 => 4, 7 => 5, 8 => 5, 9 => 6, 10 => 6];

    /** Longest input a response may send. */
    public const int MAX_INPUT = 20;

    public function key(): string
    {
        return 'pattern_sequence';
    }

    public function name(): string
    {
        return 'Pattern sequence';
    }

    public static function showMsFor(int $difficulty): int
    {
        return (int) (round((900 - (self::clampDifficulty($difficulty) - 1) * 400 / 9) / 10) * 10);
    }

    public static function pointsForLength(int $length): int
    {
        return match (true) {
            $length <= 4 => 1,
            $length <= 6 => 2,
            default => 3,
        };
    }

    public function validate(array $payload, array $answer): array
    {
        $errors = [];
        $pads = $payload['pads'] ?? null;
        $kids = array_flip(ClanArt::kidAvatars());

        if (! self::isStringList($pads) || count($pads) < 3 || count($pads) > 6 || count(array_unique($pads)) !== count($pads)
            || array_diff($pads, array_keys($kids)) !== []) {
            return ['payload.pads' => ['The pads must be 3–6 different Clan kid avatar keys.']];
        }

        $sequence = $payload['sequence'] ?? null;

        if (! self::isIntList($sequence, 0, count($pads) - 1) || count($sequence) < 3 || count($sequence) > 9) {
            $errors['payload.sequence'][] = 'The sequence must be 3–9 pad positions.';
        } elseif (self::hasTripleRepeat($sequence)) {
            $errors['payload.sequence'][] = 'No pad may light up three times in a row.';
        }

        $showMs = $payload['show_ms'] ?? null;

        if (! is_int($showMs) || $showMs < 200 || $showMs > 3000) {
            $errors['payload.show_ms'][] = 'show_ms must be 200–3000.';
        }

        return $errors;
    }

    public function forClient(ContentItem $item): array
    {
        return [
            'pads' => array_values((array) ($item->payload['pads'] ?? [])),
            'sequence' => array_values((array) ($item->payload['sequence'] ?? [])),
            'show_ms' => (int) ($item->payload['show_ms'] ?? 0),
        ];
    }

    public function validateResponse(ContentItem $item, array $response): array
    {
        $pads = count((array) ($item->payload['pads'] ?? []));
        $input = $response['input'] ?? null;

        if (! self::isIntList($input, 0, max(0, $pads - 1)) || count($input) > self::MAX_INPUT) {
            return ['response.input' => ['Send the pads tapped, in order: a list of positions from 0 to '.($pads - 1).' (at most '.self::MAX_INPUT.').']];
        }

        return [];
    }

    public function check(ContentItem $item, array $response): bool
    {
        $sequence = array_values((array) ($item->payload['sequence'] ?? []));

        return $sequence !== [] && ($response['input'] ?? null) === $sequence;
    }

    public function correctResponse(ContentItem $item): array
    {
        return ['input' => array_values((array) ($item->payload['sequence'] ?? []))];
    }

    public function points(ContentItem $item): int
    {
        return self::pointsForLength(count((array) ($item->payload['sequence'] ?? [])));
    }

    public function generate(string $seed, int $difficulty, array $options): array
    {
        $difficulty = self::clampDifficulty($difficulty);
        $rng = ClanArt::random($this->key(), $seed, $difficulty);
        $pads = array_slice($rng->shuffleArray(ClanArt::kidAvatars()), 0, self::PADS[$difficulty]);
        $sequence = [];

        for ($i = 0; $i < self::LENGTHS[$difficulty]; $i++) {
            $choices = range(0, count($pads) - 1);

            if ($i >= 2 && $sequence[$i - 1] === $sequence[$i - 2]) {
                $choices = array_values(array_diff($choices, [$sequence[$i - 1]]));
            }

            $sequence[] = $choices[$rng->getInt(0, count($choices) - 1)];
        }

        return [
            'payload' => ['pads' => $pads, 'sequence' => $sequence, 'show_ms' => self::showMsFor($difficulty)],
            'answer' => [],
        ];
    }

    public function pointsFor(array $options, int $difficulty): int
    {
        return self::pointsForLength(self::LENGTHS[self::clampDifficulty($difficulty)]);
    }

    /**
     * @param  list<int>  $sequence
     */
    public static function hasTripleRepeat(array $sequence): bool
    {
        for ($i = 2; $i < count($sequence); $i++) {
            if ($sequence[$i] === $sequence[$i - 1] && $sequence[$i] === $sequence[$i - 2]) {
                return true;
            }
        }

        return false;
    }
}
