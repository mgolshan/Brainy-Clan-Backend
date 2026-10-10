<?php

namespace App\Modules\Scoring\Listeners;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Play\Events\AnswerSubmitted;
use App\Modules\Scoring\Actions\RecordDailyActivity;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued: any answer makes the member active today (family timezone).
 */
class UpdateStreak implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(private readonly RecordDailyActivity $activity) {}

    public function handle(AnswerSubmitted $event): void
    {
        $member = FamilyMember::query()->with('family')->find($event->memberId);

        if ($member !== null) {
            $this->activity->handle($member);
        }
    }
}
