<?php

namespace App\Modules\Goals\Actions;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Goals\Models\GoalPeriod;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Goals\Models\GoalPlanMember;
use Illuminate\Support\Facades\DB;

/**
 * `everyone` plans (docs/API.md "Goals", 2026-11-11): the team is the whole family.
 *
 * - {@see self::newcomer()}: a member just became active in the family (joined by invite, created
 *   the family, …) and joins every `everyone` plan of the family that is not deleted (paused ones
 *   too, so they are on the team when it resumes), with `target_override` null, and the target
 *   snapshot of each open period with the period's `target_points`. Only points earned after they
 *   joined count, because the ledger only has those.
 * - {@see self::wholeFamily()}: a plan switched to `everyone`: every missing active member joins it
 *   and its open period right away.
 *
 * Members already on a plan or in a period keep their override / snapshotted target. A fixed number
 * of queries for any number of plans and members: plan ids, one INSERT (duplicates ignored by the
 * unique (plan, member) index), the open periods (locked) and one CASE update of their snapshots.
 */
class JoinEveryoneGoalPlans
{
    public function newcomer(int $memberId, int $familyId): void
    {
        $planIds = GoalPlan::query()
            ->where('family_id', $familyId)
            ->where('everyone', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->add($planIds, [$memberId]);
    }

    public function wholeFamily(GoalPlan $plan): void
    {
        $memberIds = FamilyMember::query()
            ->where('family_id', $plan->family_id)
            ->where('status', FamilyMember::STATUS_ACTIVE)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->add([$plan->id], $memberIds);
        $plan->unsetRelation('planMembers');
    }

    /**
     * @param  list<int>  $planIds
     * @param  list<int>  $memberIds
     */
    private function add(array $planIds, array $memberIds): void
    {
        if ($planIds === [] || $memberIds === []) {
            return;
        }

        DB::transaction(function () use ($planIds, $memberIds): void {
            $rows = [];

            foreach ($planIds as $planId) {
                foreach ($memberIds as $memberId) {
                    $rows[] = ['goal_plan_id' => $planId, 'member_id' => $memberId, 'target_override' => null];
                }
            }

            GoalPlanMember::query()->insertOrIgnore($rows);

            $periods = GoalPeriod::query()
                ->whereIn('goal_plan_id', $planIds)
                ->where('status', GoalPeriod::STATUS_OPEN)
                ->lockForUpdate()
                ->get(['id', 'target_points', 'member_targets']);

            $changed = [];

            foreach ($periods as $period) {
                $targets = $period->member_targets;
                $before = count($targets);

                foreach ($memberIds as $memberId) {
                    $targets[(string) $memberId] ??= $period->target_points;
                }

                if (count($targets) !== $before) {
                    $changed[$period->id] = json_encode((object) $targets, JSON_THROW_ON_ERROR);
                }
            }

            self::writeTargets($changed);
        });
    }

    /**
     * One UPDATE … CASE for several periods' target snapshots.
     *
     * @param  array<int, string>  $targetsById  period id => JSON object
     */
    public static function writeTargets(array $targetsById): void
    {
        if ($targetsById === []) {
            return;
        }

        $cases = implode(' ', array_fill(0, count($targetsById), 'WHEN ? THEN ?'));
        $marks = implode(',', array_fill(0, count($targetsById), '?'));
        $bindings = [];

        foreach ($targetsById as $id => $json) {
            $bindings[] = $id;
            $bindings[] = $json;
        }

        DB::update(
            "UPDATE goal_periods SET member_targets = CASE id {$cases} END, updated_at = ? WHERE id IN ({$marks})",
            [...$bindings, now(), ...array_keys($targetsById)],
        );
    }
}
