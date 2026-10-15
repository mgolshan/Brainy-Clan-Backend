<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Goals\Events\GoalReached;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Notifications\Actions\SendMemberNotification;
use App\Modules\Notifications\Support\GoalRecipients;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued: tells the plan's members that the goal was reached (cooperation) or who reached it (competition).
 * Push `data`: `{ "type": "goal_reached", "goal_id": …, "period_id": … }`.
 */
class NotifyGoalReached implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(private readonly SendMemberNotification $send) {}

    public function handle(GoalReached $event): void
    {
        $loaded = GoalRecipients::load($event->planId);

        if ($loaded === null) {
            return;
        }

        [$plan, $members] = $loaded;

        if ($plan->mode === GoalPlan::MODE_COOPERATION) {
            $body = "Team goal reached: {$plan->name}!";
        } else {
            $winners = $members->whereIn('id', $event->winnerMemberIds)->map(fn (FamilyMember $member) => $member->nickname)->join(', ', ' and ');
            $body = $winners !== '' ? "{$winners} reached the goal in {$plan->name}!" : "The goal in {$plan->name} was reached!";
        }

        $this->send->handle($members, 'goal_reached', 'Goal reached!', $body, [
            'goal_id' => $plan->id,
            'period_id' => $event->periodId,
        ], CarbonImmutable::now());
    }
}
