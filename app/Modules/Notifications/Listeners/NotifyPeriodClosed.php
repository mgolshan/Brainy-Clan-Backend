<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Goals\Events\PeriodClosed;
use App\Modules\Notifications\Actions\SendMemberNotification;
use App\Modules\Notifications\Support\GoalRecipients;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued: tells the plan's members that a goal period ended and the results are in.
 * Push `data`: `{ "type": "period_closed", "goal_id": …, "period_id": … }`.
 */
class NotifyPeriodClosed implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(private readonly SendMemberNotification $send) {}

    public function handle(PeriodClosed $event): void
    {
        $loaded = GoalRecipients::load($event->planId);

        if ($loaded === null) {
            return;
        }

        [$plan, $members] = $loaded;

        $this->send->handle($members, 'period_closed', 'Results are in', "{$plan->name}: see how everyone did.", [
            'goal_id' => $plan->id,
            'period_id' => $event->periodId,
        ], CarbonImmutable::now());
    }
}
