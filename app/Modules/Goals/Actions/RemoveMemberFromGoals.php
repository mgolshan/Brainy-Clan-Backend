<?php

namespace App\Modules\Goals\Actions;

use App\Modules\Goals\Models\GoalPeriod;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Goals\Models\GoalPlanMember;

/**
 * A member left the family: drop them from the family's goal plans and from the target
 * snapshot of open periods, so they no longer show up in current progress. Closed periods keep
 * their stored results (history never changes).
 */
class RemoveMemberFromGoals
{
    public function handle(int $memberId, int $familyId): void
    {
        $planIds = GoalPlan::withTrashed()->where('family_id', $familyId)->pluck('id');

        GoalPlanMember::query()->whereIn('goal_plan_id', $planIds)->where('member_id', $memberId)->delete();

        GoalPeriod::query()
            ->whereIn('goal_plan_id', $planIds)
            ->where('status', GoalPeriod::STATUS_OPEN)
            ->get()
            ->each(function (GoalPeriod $period) use ($memberId): void {
                $targets = $period->member_targets;

                if (array_key_exists($memberId, $targets)) {
                    unset($targets[$memberId]);
                    $period->forceFill(['member_targets' => (object) $targets])->save();
                }
            });
    }
}
