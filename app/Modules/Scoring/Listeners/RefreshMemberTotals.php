<?php

namespace App\Modules\Scoring\Listeners;

use App\Modules\Play\Events\AnswerSubmitted;
use App\Modules\Play\Events\SessionCompleted;
use App\Modules\Play\Models\PlaySession;
use App\Modules\Scoring\Models\MemberPeriodTotal;
use App\Modules\Scoring\Models\PointEntry;

/**
 * Queued: rebuilds the member's cached total for the session's goal period from the ledger.
 * Sessions are not tied to goal periods until B6 (goal_period_id is null), so this is a no-op
 * for now; goal progress itself is always computed from the ledger.
 */
class RefreshMemberTotals
{
    public bool $afterCommit = true;

    public function handle(AnswerSubmitted|SessionCompleted $event): void
    {
        $periodId = PlaySession::query()->whereKey($event->sessionId)->value('goal_period_id');

        if ($periodId === null) {
            return;
        }

        MemberPeriodTotal::query()->updateOrCreate(
            ['member_id' => $event->memberId, 'goal_period_id' => $periodId],
            [
                'points' => (int) PointEntry::query()->where('member_id', $event->memberId)->where('goal_period_id', $periodId)->sum('amount'),
                'sessions' => PlaySession::query()->where('member_id', $event->memberId)->where('goal_period_id', $periodId)->where('status', PlaySession::STATUS_COMPLETED)->count(),
            ],
        );
    }
}
