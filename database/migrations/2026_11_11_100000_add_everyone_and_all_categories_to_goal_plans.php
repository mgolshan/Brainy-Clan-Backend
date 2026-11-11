<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Goals that explain themselves (docs/API.md "Goals", 2026-11-11):
     * - `everyone`: the team is the whole family; newcomers join the plan and its open period.
     * - `all_categories`: points in any category count (no scopes).
     *
     * Backfill (pre-launch, only demo/test plans exist): every existing plan becomes `everyone`, and
     * every active family member missing from a (not deleted) plan is added to it (`target_override`
     * null) and to its open period's target snapshot (target = the period's `target_points`).
     * Set-based and safe to re-run: one INSERT … SELECT for the plan members, then one CASE update
     * per chunk of open periods; members already present are left alone.
     */
    public function up(): void
    {
        Schema::table('goal_plans', function (Blueprint $table) {
            $table->boolean('everyone')->default(false)->after('is_active');
            $table->boolean('all_categories')->default(false)->after('everyone');
        });

        $this->backfill();
    }

    /**
     * Public so a test can run it again on purpose-built data (it is idempotent).
     */
    public function backfill(): void
    {
        DB::table('goal_plans')->update(['everyone' => true]);

        DB::table('goal_plan_members')->insertUsing(
            ['goal_plan_id', 'member_id', 'target_override'],
            DB::table('goal_plans')
                ->join('family_members', 'family_members.family_id', '=', 'goal_plans.family_id')
                ->where('family_members.status', 'active')
                ->whereNull('goal_plans.deleted_at')
                ->whereNotExists(fn ($query) => $query
                    ->selectRaw('1')
                    ->from('goal_plan_members as existing')
                    ->whereColumn('existing.goal_plan_id', 'goal_plans.id')
                    ->whereColumn('existing.member_id', 'family_members.id'))
                ->select(['goal_plans.id', 'family_members.id'])
                ->selectRaw('NULL'),
        );

        DB::table('goal_periods')
            ->join('goal_plans', 'goal_plans.id', '=', 'goal_periods.goal_plan_id')
            ->whereNull('goal_plans.deleted_at')
            ->where('goal_periods.status', 'open')
            ->select(['goal_periods.id', 'goal_periods.target_points', 'goal_periods.member_targets', 'goal_plans.family_id'])
            ->orderBy('goal_periods.id')
            ->chunk(500, function ($periods): void {
                $active = DB::table('family_members')
                    ->whereIn('family_id', $periods->pluck('family_id')->unique()->all())
                    ->where('status', 'active')
                    ->get(['id', 'family_id'])
                    ->groupBy('family_id');

                $changed = [];

                foreach ($periods as $period) {
                    $targets = json_decode((string) $period->member_targets, true);
                    $targets = is_array($targets) ? $targets : [];
                    $before = count($targets);

                    foreach ($active->get($period->family_id, collect()) as $member) {
                        $targets[(string) $member->id] ??= (int) $period->target_points;
                    }

                    if (count($targets) !== $before) {
                        $changed[(int) $period->id] = json_encode((object) $targets, JSON_THROW_ON_ERROR);
                    }
                }

                if ($changed === []) {
                    return;
                }

                $cases = implode(' ', array_fill(0, count($changed), 'WHEN ? THEN ?'));
                $marks = implode(',', array_fill(0, count($changed), '?'));
                $bindings = [];

                foreach ($changed as $id => $json) {
                    $bindings[] = $id;
                    $bindings[] = $json;
                }

                DB::update(
                    "UPDATE goal_periods SET member_targets = CASE id {$cases} END WHERE id IN ({$marks})",
                    [...$bindings, ...array_keys($changed)],
                );
            });
    }

    public function down(): void
    {
        Schema::table('goal_plans', function (Blueprint $table) {
            $table->dropColumn(['everyone', 'all_categories']);
        });
    }
};
