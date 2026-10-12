<?php

namespace App\Modules\Goals\Actions;

use App\Modules\Family\Models\Family;
use App\Modules\Goals\Models\GoalPeriod;
use App\Modules\Goals\Models\GoalPlan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Loads a family's plans (active first, newest first) with scopes, members, the current period
 * and its progress. The current period is opened on demand, so the app is right even between two
 * scheduler runs; ended periods are only ever closed by the scheduled job.
 *
 * Constant number of queries for any number of plans: relations are eager loaded, current
 * periods are fetched in one query (a period is only created when a new window starts) and
 * progress for all plans comes from one grouped ledger query (ComputeGoalProgress::many()).
 */
class LoadGoalPlans
{
    public function __construct(
        private readonly OpenGoalPeriod $open,
        private readonly ComputeGoalProgress $progress,
    ) {}

    /**
     * @return Collection<int, GoalPlan>
     */
    public function forFamily(Family $family): Collection
    {
        $plans = GoalPlan::query()
            ->with(['categories', 'planMembers.member.user'])
            ->where('family_id', $family->id)
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get();

        return $this->prepareMany($plans, $family);
    }

    public function prepare(GoalPlan $plan, ?Family $family = null): GoalPlan
    {
        $plan->loadMissing('categories', 'planMembers.member.user');

        return $this->prepareMany(new Collection([$plan]), $family ?? $plan->family)->first() ?? $plan;
    }

    /**
     * @param  Collection<int, GoalPlan>  $plans
     * @return Collection<int, GoalPlan>
     */
    private function prepareMany(Collection $plans, Family $family): Collection
    {
        $now = CarbonImmutable::now();
        $plans->each(fn (GoalPlan $plan) => $plan->setRelation('family', $family));

        $existing = $plans->isEmpty() ? new Collection : GoalPeriod::query()
            ->whereIn('goal_plan_id', $plans->modelKeys())
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->get();

        $current = new Collection;

        foreach ($plans as $plan) {
            $window = $this->open->window($plan, $now);
            $period = null;

            if ($window !== null) {
                $period = $existing->first(fn (GoalPeriod $p) => $p->goal_plan_id === $plan->id && $p->starts_at->equalTo($window[0]))
                    ?? $this->open->handle($plan, $now);
            }

            if ($period?->status === GoalPeriod::STATUS_OPEN) {
                $period->setRelation('plan', $plan);
                $current->push($period);
            }

            $plan->setRelation('currentPeriod', $period?->status === GoalPeriod::STATUS_OPEN ? $period : null);
        }

        $progress = $this->progress->many($current);

        foreach ($current as $period) {
            $period->progress = $progress[$period->id];
        }

        return $plans;
    }
}
