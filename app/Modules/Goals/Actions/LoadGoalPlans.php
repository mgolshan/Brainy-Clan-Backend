<?php

namespace App\Modules\Goals\Actions;

use App\Modules\Family\Models\Family;
use App\Modules\Goals\Models\GoalPeriod;
use App\Modules\Goals\Models\GoalPlan;
use Illuminate\Database\Eloquent\Collection;

/**
 * Loads a family's plans (active first, newest first) with scopes, members and the current
 * period. The current period is opened on demand, so the app is right even between two
 * scheduler runs; ended periods are only ever closed by the scheduled job.
 */
class LoadGoalPlans
{
    public function __construct(private readonly OpenGoalPeriod $open) {}

    /**
     * @return Collection<int, GoalPlan>
     */
    public function forFamily(Family $family): Collection
    {
        $plans = GoalPlan::query()
            ->where('family_id', $family->id)
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get();

        $plans->each(fn (GoalPlan $plan) => $this->prepare($plan, $family));

        return $plans;
    }

    public function prepare(GoalPlan $plan, ?Family $family = null): GoalPlan
    {
        if ($family !== null) {
            $plan->setRelation('family', $family);
        }

        $plan->loadMissing('categories', 'planMembers.member.user');

        $period = $this->open->handle($plan);
        $plan->setRelation('currentPeriod', $period?->status === GoalPeriod::STATUS_OPEN ? $period->setRelation('plan', $plan) : null);

        return $plan;
    }
}
