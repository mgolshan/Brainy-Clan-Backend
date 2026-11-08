<?php

namespace App\Modules\ChallengeTypes\Handlers;

use App\Modules\ChallengeTypes\Support\ClanArt;
use App\Modules\Content\Models\ContentItem;

/**
 * Code Breaker (Mastermind with Clan avatar pegs, docs/API.md "Level-up puzzles").
 *
 * payload:  { "pegs": ["fox","owl","frog","panda"], "length": 3, "repeats": false, "max_guesses": 10, "code": [2,0,3] }
 * answer:   { }                                      (the code is the puzzle; the app scores every guess)
 * response: { "guesses": [[0,1,2],[2,0,1],…] }       (every guess, in order, as indices into pegs)
 *
 * Feedback per guess (standard Mastermind, see feedback()): `exact` = positions that agree,
 * `near` = Σ over pegs of min(count in guess, count in code) − exact. Correct when a guess equals
 * the code within `max_guesses`. Each guess must have `length` entries 0 ≤ i < pegs (repeats are
 * allowed in a guess even when the code has none); more than `max_guesses` guesses or any guess
 * after the matching one → 422. Pegs are Clan kid avatar keys; worth 2 (length 3), 3 (4) or 5 (5).
 */
class CodeBreaker extends BrainGame
{
    /** Per difficulty 1–10: [length, pegs, repeats, max guesses]. */
    public const array LEVELS = [
        1 => [3, 4, false, 10],
        2 => [3, 5, false, 10],
        3 => [4, 4, false, 10],
        4 => [4, 5, false, 10],
        5 => [4, 6, false, 10],
        6 => [4, 6, true, 10],
        7 => [5, 6, false, 10],
        8 => [5, 7, true, 10],
        9 => [5, 8, true, 10],
        10 => [5, 8, true, 8],
    ];

    /** Points per code length. */
    public const array POINTS = [3 => 2, 4 => 3, 5 => 5];

    public function key(): string
    {
        return 'code_breaker';
    }

    public function name(): string
    {
        return 'Code Breaker';
    }

    /**
     * Mastermind feedback for a guess.
     *
     * @param  list<int>  $guess
     * @param  list<int>  $code
     * @return array{exact: int, near: int}
     */
    public static function feedback(array $guess, array $code): array
    {
        $exact = 0;

        foreach ($code as $i => $peg) {
            if (($guess[$i] ?? null) === $peg) {
                $exact++;
            }
        }

        $inGuess = array_count_values($guess);
        $common = 0;

        foreach (array_count_values($code) as $peg => $times) {
            $common += min($times, $inGuess[$peg] ?? 0);
        }

        return ['exact' => $exact, 'near' => $common - $exact];
    }

    public function validate(array $payload, array $answer): array
    {
        $errors = [];
        $pegs = $payload['pegs'] ?? null;

        if (! self::isStringList($pegs) || count($pegs) < 4 || count($pegs) > 8 || count(array_unique($pegs)) !== count($pegs)
            || array_diff($pegs, ClanArt::kidAvatars()) !== []) {
            return ['payload.pegs' => ['The pegs must be 4–8 different Clan kid avatar keys.']];
        }

        $length = $payload['length'] ?? null;
        $repeats = $payload['repeats'] ?? null;

        if (! is_int($length) || $length < 3 || $length > 5) {
            return ['payload.length' => ['length must be 3–5.']];
        }

        if (! is_bool($repeats)) {
            $errors['payload.repeats'][] = 'repeats must be true or false.';
        }

        $max = $payload['max_guesses'] ?? null;

        if (! is_int($max) || $max < 1 || $max > 20) {
            $errors['payload.max_guesses'][] = 'max_guesses must be 1–20.';
        }

        $code = $payload['code'] ?? null;

        if (! self::isIntList($code, 0, count($pegs) - 1) || count($code) !== $length
            || ($repeats === false && count(array_unique($code)) !== count($code))) {
            $errors['payload.code'][] = "The code must be {$length} peg positions (0–".(count($pegs) - 1).'), all different unless repeats is true.';
        }

        return $errors;
    }

    public function forClient(ContentItem $item): array
    {
        return [
            'pegs' => array_values((array) ($item->payload['pegs'] ?? [])),
            'length' => (int) ($item->payload['length'] ?? 0),
            'repeats' => (bool) ($item->payload['repeats'] ?? false),
            'max_guesses' => (int) ($item->payload['max_guesses'] ?? 0),
            'code' => array_values((array) ($item->payload['code'] ?? [])),
        ];
    }

    public function validateResponse(ContentItem $item, array $response): array
    {
        $error = $this->replay($item, $response['guesses'] ?? null);

        return $error !== null ? ['response.guesses' => [$error]] : [];
    }

    public function check(ContentItem $item, array $response): bool
    {
        $guesses = $response['guesses'] ?? null;
        $code = array_values((array) ($item->payload['code'] ?? []));

        return $this->replay($item, $guesses) === null && $code !== [] && in_array($code, (array) $guesses, true);
    }

    public function correctResponse(ContentItem $item): array
    {
        return ['guesses' => null, 'code' => array_values((array) ($item->payload['code'] ?? []))];
    }

    public function points(ContentItem $item): int
    {
        return self::POINTS[(int) ($item->payload['length'] ?? 0)] ?? 2;
    }

    public function generate(string $seed, int $difficulty, array $options): array
    {
        $difficulty = self::clampDifficulty($difficulty);
        [$length, $pegCount, $repeats, $maxGuesses] = self::LEVELS[$difficulty];
        $rng = ClanArt::random($this->key(), $seed, $difficulty);
        $pegs = array_slice($rng->shuffleArray(ClanArt::kidAvatars()), 0, $pegCount);
        $code = $repeats
            ? array_map(fn () => $rng->getInt(0, $pegCount - 1), range(1, $length))
            : array_slice($rng->shuffleArray(range(0, $pegCount - 1)), 0, $length);

        return [
            'payload' => ['pegs' => $pegs, 'length' => $length, 'repeats' => $repeats, 'max_guesses' => $maxGuesses, 'code' => $code],
            'answer' => [],
        ];
    }

    public function pointsFor(array $options, int $difficulty): int
    {
        return self::POINTS[self::LEVELS[self::clampDifficulty($difficulty)][0]];
    }

    /**
     * Why the guesses are malformed (null = well-formed).
     */
    private function replay(ContentItem $item, mixed $guesses): ?string
    {
        $pegs = count((array) ($item->payload['pegs'] ?? []));
        $length = (int) ($item->payload['length'] ?? 0);
        $max = (int) ($item->payload['max_guesses'] ?? 0);
        $code = array_values((array) ($item->payload['code'] ?? []));

        if (! is_array($guesses) || ! array_is_list($guesses)) {
            return 'Send guesses as a list of guesses (each a list of peg positions).';
        }

        if (count($guesses) > $max) {
            return "At most {$max} guesses.";
        }

        foreach ($guesses as $index => $guess) {
            if (! self::isIntList($guess, 0, $pegs - 1) || count($guess) !== $length) {
                return "Guess {$index} must be {$length} peg positions from 0 to ".($pegs - 1).'.';
            }

            if ($guess === $code && $index < count($guesses) - 1) {
                return "Guess {$index} already broke the code; no guesses may follow it.";
            }
        }

        return null;
    }
}
