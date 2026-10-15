<?php

namespace App\Modules\Notifications\Support;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Goals\Models\GoalPlan;
use Illuminate\Support\Collection;

/**
 * Loads a goal plan with its members (user + family set, for preferences) in a constant number of queries.
 */
class GoalRecipients
{
    /**
     * @return array{0: GoalPlan, 1: Collection<int, FamilyMember>}|null
     */
    public static function load(int $planId): ?array
    {
        $plan = GoalPlan::query()->with(['family', 'planMembers.member.user'])->find($planId);

        if ($plan === null) {
            return null;
        }

        $members = $plan->planMembers->map(function ($planMember) use ($plan) {
            $member = $planMember->member;
            $member->setRelation('family', $plan->family);

            return $member;
        })->values();

        return [$plan, $members];
    }
}
