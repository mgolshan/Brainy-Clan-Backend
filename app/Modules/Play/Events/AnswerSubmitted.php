<?php

namespace App\Modules\Play\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A member answered a session item; the server already decided correctness and points.
 * Dispatched inside the answer transaction: synchronous listeners (the ledger) join it,
 * queued listeners run after commit.
 */
class AnswerSubmitted
{
    use Dispatchable;

    public function __construct(
        public readonly int $sessionItemId,
        public readonly int $sessionId,
        public readonly int $familyId,
        public readonly int $memberId,
        public readonly int $categoryId,
        public readonly bool $isCorrect,
        public readonly int $pointsAwarded,
        public readonly bool $isRedemption,
    ) {}
}
