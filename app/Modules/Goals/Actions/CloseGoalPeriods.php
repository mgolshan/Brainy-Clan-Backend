<?php

namespace App\Modules\Goals\Actions;

use App\Modules\Goals\Events\GoalReached;
use App\Modules\Goals\Events\PeriodClosed;
use App\Modules\Goals\Models\GoalPeriod;
use App\Modules\Goals\Models\GoalPlan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Scheduled: closes every open period that has ended (writes the result, dispatches
 * PeriodClosed / GoalReached), opens the following period, and opens current periods for
 * active plans that have none yet (e.g. a plan starting today). Missed runs catch up period
 * by period, so history has no gaps.
 */
class CloseGoalPeriods
{
    public function __construct(
        private readonly ComputeGoalProgress $progress,
        private readonly OpenGoalPeriod $open,
    ) {}

    /**
     * @return int number of closed periods
     */
    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $closed = 0;

        do {
            $ended = GoalPeriod::query()
                ->with('plan.family')
                ->where('status', GoalPeriod::STATUS_OPEN)
                ->where('ends_at', '<=', $now)
                ->orderBy('ends_at')
                ->limit(100)
                ->get();

            foreach ($ended as $period) {
                $this->close($period);
                $closed++;
                $this->open->handle($period->plan, CarbonImmutable::parse($period->ends_at));
            }
        } while ($ended->isNotEmpty());

        GoalPlan::query()->with('family')->where('is_active', true)
            ->each(fn (GoalPlan $plan) => $this->open->handle($plan, $now));

        return $closed;
    }

    public function close(GoalPeriod $period): void
    {
        $plan = $period->plan;

        /** @var array{reached: bool, winner_member_ids: list<int>} $result */
        $result = DB::transaction(function () use ($period, $plan): array {
            $progress = $this->progress->handle($period);
            $result = [
                ...$progress,
                'status' => GoalPeriod::STATUS_CLOSED,
                'winner_member_ids' => $this->progress->winners($plan, $progress),
            ];

            $period->forceFill(['status' => GoalPeriod::STATUS_CLOSED, 'result' => $result, 'closed_at' => now()])->save();

            return $result;
        });

        PeriodClosed::dispatch($period->id, $plan->id, $plan->family_id, $result['reached'], $result['winner_member_ids']);

        if ($result['reached']) {
            GoalReached::dispatch($period->id, $plan->id, $plan->family_id, $result['winner_member_ids']);
        }
    }
}
