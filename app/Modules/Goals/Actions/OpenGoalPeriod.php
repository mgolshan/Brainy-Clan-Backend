<?php

namespace App\Modules\Goals\Actions;

use App\Modules\Family\Support\FamilyClock;
use App\Modules\Goals\Models\GoalPeriod;
use App\Modules\Goals\Models\GoalPlan;
use Carbon\CarbonImmutable;

/**
 * Makes sure the period covering a moment exists for a plan (idempotent) and returns it.
 * Windows follow the family's timezone (weeks start Monday) and are clipped to the plan's
 * `starts_on` / `ends_on`, so a plan starting on a Wednesday has a short first week.
 * Targets are snapshotted into the period: plan edits apply from the next period.
 */
class OpenGoalPeriod
{
    public function handle(GoalPlan $plan, ?CarbonImmutable $at = null): ?GoalPeriod
    {
        $window = $this->window($plan, $at ?? CarbonImmutable::now());

        if ($window === null) {
            return null;
        }

        [$start, $end] = $window;

        /** @var GoalPeriod|null $existing */
        $existing = $plan->periods()->where('starts_at', $start)->first();

        if ($existing !== null) {
            return $existing;
        }

        $plan->loadMissing('planMembers');

        return $plan->periods()->create([
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => GoalPeriod::STATUS_OPEN,
            'target_points' => $plan->target_points,
            'member_targets' => $plan->planMembers
                ->mapWithKeys(fn ($m) => [(string) $m->member_id => $m->target_override ?? $plan->target_points])
                ->all(),
        ]);
    }

    /**
     * The plan's window containing `$at`, or null when the plan is inactive or `$at` is outside it.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function window(GoalPlan $plan, CarbonImmutable $at): ?array
    {
        if (! $plan->is_active) {
            return null;
        }

        $family = $plan->family;
        $tz = $family->timezone;
        $planStart = CarbonImmutable::parse($plan->starts_on->format('Y-m-d'), $tz)->utc();
        $planEnd = $plan->ends_on !== null ? CarbonImmutable::parse($plan->ends_on->format('Y-m-d'), $tz)->addDay()->utc() : null;

        if ($at->lessThan($planStart) || ($planEnd !== null && $at->greaterThanOrEqualTo($planEnd))) {
            return null;
        }

        [$start, $end] = FamilyClock::window($family, $plan->period_type, $at);

        return [
            $start->max($planStart),
            $planEnd !== null ? $end->min($planEnd) : $end,
        ];
    }
}
