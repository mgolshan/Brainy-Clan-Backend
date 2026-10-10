<?php

namespace App\Modules\Scoring\Listeners;

use App\Modules\Play\Events\AnswerSubmitted;
use App\Modules\Scoring\Actions\AppendPoints;
use App\Modules\Scoring\Models\PointEntry;

/**
 * Synchronous: appends the ledger row inside the answer's transaction, so session points and
 * the ledger can never disagree.
 */
class RecordAnswerPoints
{
    public function __construct(private readonly AppendPoints $append) {}

    public function handle(AnswerSubmitted $event): void
    {
        if ($event->pointsAwarded <= 0) {
            return;
        }

        $this->append->handle(
            $event->familyId,
            $event->memberId,
            $event->categoryId,
            $event->pointsAwarded,
            PointEntry::REASON_CORRECT_ANSWER,
            $event->sessionId,
            $event->sessionItemId,
        );
    }
}
