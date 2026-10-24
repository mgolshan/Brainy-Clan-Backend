<?php

namespace App\Modules\ChallengeTypes\Handlers;

use App\Modules\ChallengeTypes\Support\ClanArt;
use App\Modules\Content\Models\ContentItem;

/**
 * Memory match: face-down cards, every face (a Clan kid avatar key) exactly twice.
 *
 * payload:  { "cols": 4, "cards": ["fox","owl","fox",…], "max_attempts": 12 }   (row-major positions)
 * answer:   { }                                                                (the cards are the puzzle)
 * response: { "attempts": [[0,2],[1,5],…] }                                    (every pair turned over, in order)
 *
 * The server replays the attempts: each is two different positions not yet matched (else 422);
 * correct when every pair ends up matched within `max_attempts`. A player with perfect memory
 * never needs more than 2 × pairs − 1 attempts; `max_attempts` adds a little slack on top
 * (more for the youngest levels). 3–10 pairs by difficulty; worth 2 points (≤ 6 pairs) or 3.
 */
class MemoryMatch extends BrainGame
{
    /** Pairs per difficulty 1–10. */
    public const array PAIRS = [1 => 3, 2 => 3, 3 => 4, 4 => 4, 5 => 6, 6 => 6, 7 => 8, 8 => 8, 9 => 10, 10 => 10];

    /** Columns per number of pairs (3 → 2×3, 4 → 2×4, 6 → 3×4, 8 → 4×4, 10 → 4×5). */
    public const array COLS = [3 => 3, 4 => 4, 6 => 4, 8 => 4, 10 => 5];

    /** Most attempts a response may list (anything longer is malformed). */
    public const int MAX_LISTED_ATTEMPTS = 200;

    public function key(): string
    {
        return 'memory_match';
    }

    public function name(): string
    {
        return 'Memory match';
    }

    public static function pairsFor(int $difficulty): int
    {
        return self::PAIRS[self::clampDifficulty($difficulty)];
    }

    /**
     * Allowed attempts: 2 × pairs + 2 for the youngest levels (1–4), 2 × pairs + 1 above —
     * always more than the 2 × pairs − 1 a perfect memory needs at worst.
     */
    public static function maxAttemptsFor(int $pairs, int $difficulty): int
    {
        return 2 * $pairs + (self::clampDifficulty($difficulty) <= 4 ? 2 : 1);
    }

    public static function pointsForPairs(int $pairs): int
    {
        return $pairs <= 6 ? 2 : 3;
    }

    public function validate(array $payload, array $answer): array
    {
        $errors = [];
        $cards = $payload['cards'] ?? null;

        if (! self::isStringList($cards) || ! $this->isPairedDeck($cards)) {
            return ['payload.cards' => ['The cards must be 3–10 Clan kid avatar keys, each exactly twice.']];
        }

        $pairs = intdiv(count($cards), 2);
        $cols = $payload['cols'] ?? null;

        if (! is_int($cols) || $cols < 2 || $cols > count($cards)) {
            $errors['payload.cols'][] = 'The number of columns must be between 2 and the number of cards.';
        }

        $max = $payload['max_attempts'] ?? null;

        if (! is_int($max) || $max < 2 * $pairs - 1 || $max > 4 * $pairs) {
            $errors['payload.max_attempts'][] = 'max_attempts must be between 2 × pairs − 1 and 4 × pairs.';
        }

        return $errors;
    }

    public function forClient(ContentItem $item): array
    {
        return [
            'cols' => (int) ($item->payload['cols'] ?? 0),
            'cards' => array_values((array) ($item->payload['cards'] ?? [])),
            'max_attempts' => (int) ($item->payload['max_attempts'] ?? 0),
        ];
    }

    public function validateResponse(ContentItem $item, array $response): array
    {
        $replay = $this->replay($item, $response['attempts'] ?? null);

        return $replay['error'] !== null ? ['response.attempts' => [$replay['error']]] : [];
    }

    public function check(ContentItem $item, array $response): bool
    {
        $replay = $this->replay($item, $response['attempts'] ?? null);
        $cards = (array) ($item->payload['cards'] ?? []);

        return $replay['error'] === null
            && $cards !== []
            && count($cards) === $replay['matched'] * 2
            && $replay['attempts'] <= (int) ($item->payload['max_attempts'] ?? 0);
    }

    public function correctResponse(ContentItem $item): array
    {
        return ['attempts' => null, 'cards' => array_values((array) ($item->payload['cards'] ?? []))];
    }

    public function points(ContentItem $item): int
    {
        return self::pointsForPairs(intdiv(count((array) ($item->payload['cards'] ?? [])), 2));
    }

    public function generate(string $seed, int $difficulty, array $options): array
    {
        $difficulty = self::clampDifficulty($difficulty);
        $pairs = self::pairsFor($difficulty);
        $rng = ClanArt::random($this->key(), $seed, $difficulty);
        $faces = array_slice($rng->shuffleArray(ClanArt::kidAvatars()), 0, $pairs);
        $cards = $rng->shuffleArray([...$faces, ...$faces]);

        return [
            'payload' => ['cols' => self::COLS[$pairs], 'cards' => $cards, 'max_attempts' => self::maxAttemptsFor($pairs, $difficulty)],
            'answer' => [],
        ];
    }

    public function pointsFor(array $options, int $difficulty): int
    {
        return self::pointsForPairs(self::pairsFor($difficulty));
    }

    /**
     * Replays the attempts: `error` says why the list is malformed (null = well-formed),
     * `matched` counts the matched pairs, `attempts` the attempts made.
     *
     * @return array{error: string|null, matched: int, attempts: int}
     */
    private function replay(ContentItem $item, mixed $attempts): array
    {
        $cards = array_values((array) ($item->payload['cards'] ?? []));
        $count = count($cards);

        if (! is_array($attempts) || ! array_is_list($attempts) || count($attempts) > self::MAX_LISTED_ATTEMPTS) {
            return ['error' => 'Send attempts as a list (at most '.self::MAX_LISTED_ATTEMPTS.') of [position, position] pairs.', 'matched' => 0, 'attempts' => 0];
        }

        $matched = [];

        foreach ($attempts as $index => $attempt) {
            if (! self::isIntList($attempt, 0, $count - 1) || count($attempt) !== 2) {
                return ['error' => "Attempt {$index} must be two positions from 0 to ".($count - 1).'.', 'matched' => 0, 'attempts' => 0];
            }

            [$first, $second] = $attempt;

            if ($first === $second) {
                return ['error' => "Attempt {$index} turns the same card twice.", 'matched' => 0, 'attempts' => 0];
            }

            if (isset($matched[$first]) || isset($matched[$second])) {
                return ['error' => "Attempt {$index} turns a card that is already matched.", 'matched' => 0, 'attempts' => 0];
            }

            if ($cards[$first] === $cards[$second]) {
                $matched[$first] = $matched[$second] = true;
            }
        }

        return ['error' => null, 'matched' => intdiv(count($matched), 2), 'attempts' => count($attempts)];
    }

    /**
     * @param  list<string>  $cards
     */
    private function isPairedDeck(array $cards): bool
    {
        $counts = array_count_values($cards);
        $kids = array_flip(ClanArt::kidAvatars());

        if (count($counts) < 3 || count($counts) > 10) {
            return false;
        }

        foreach ($counts as $face => $times) {
            if ($times !== 2 || ! isset($kids[$face])) {
                return false;
            }
        }

        return true;
    }
}
