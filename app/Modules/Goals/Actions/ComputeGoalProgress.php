<?php

namespace App\Modules\Goals\Actions;

use App\Modules\Content\Support\CategoryTree;
use App\Modules\Core\Support\Iso;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Goals\Models\GoalPeriod;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Scoring\Actions\BuildLeaderboard;
use App\Modules\Scoring\Models\PointEntry;

/**
 * GoalProgress (docs/API.md) for a period, always computed from the points ledger: points
 * inside the window, in the plan's scope subtrees, by the period's members. Closed periods
 * return their stored result.
 *
 * - cooperation: `reached` when the team total meets the period target.
 * - competition: `reached` when at least one member met their own target.
 * Each entry's `target` is the member's snapshotted target (`target_override ?? target_points`).
 */
class ComputeGoalProgress
{
    public function __construct(private readonly CategoryTree $tree) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(GoalPeriod $period): array
    {
        if ($period->status === GoalPeriod::STATUS_CLOSED && $period->result !== null) {
            return $period->result;
        }

        $plan = $period->plan;
        /** @var array<int, int> $targets */
        $targets = collect($period->member_targets)->mapWithKeys(fn ($target, $id) => [(int) $id => (int) $target])->all();
        $memberIds = array_keys($targets);
        $categoryIds = $plan->categories()->pluck('categories.id')
            ->flatMap(fn ($id) => $this->tree->subtreeIds((int) $id))->unique()->values()->all();

        /** @var array<int, int> $totals */
        $totals = $memberIds === [] || $categoryIds === [] ? [] : PointEntry::query()
            ->where('family_id', $plan->family_id)
            ->whereIn('member_id', $memberIds)
            ->whereIn('category_id', $categoryIds)
            ->where('created_at', '>=', $period->starts_at)
            ->where('created_at', '<', $period->ends_at)
            ->groupBy('member_id')
            ->selectRaw('member_id, SUM(amount) as points')
            ->pluck('points', 'member_id')
            ->map(fn ($points) => (int) $points)
            ->all();

        $rows = FamilyMember::query()->with('user')->whereIn('id', $memberIds)->get()
            ->map(fn (FamilyMember $member) => [
                'member_id' => $member->id,
                'nickname' => $member->nickname,
                'avatar' => $member->user->avatar,
                'points' => $totals[$member->id] ?? 0,
                'target' => $targets[$member->id],
                'reached' => ($totals[$member->id] ?? 0) >= $targets[$member->id],
            ])
            ->sortBy([['points', 'desc'], ['nickname', 'asc']])
            ->values()->all();

        $teamTotal = array_sum(array_column($rows, 'points'));

        return [
            'period_id' => $period->id,
            'starts_at' => Iso::format($period->starts_at),
            'ends_at' => Iso::format($period->ends_at),
            'status' => $period->status,
            'team_total' => $teamTotal,
            'target_points' => $period->target_points,
            'reached' => $plan->mode === GoalPlan::MODE_COOPERATION
                ? $teamTotal >= $period->target_points
                : in_array(true, array_column($rows, 'reached'), true),
            'entries' => BuildLeaderboard::rank($rows),
        ];
    }

    /**
     * Winners of a finished period: competition = everyone on rank 1 with more than 0 points;
     * cooperation = every member when the team reached the target, else nobody.
     *
     * @param  array<string, mixed>  $progress
     * @return list<int>
     */
    public function winners(GoalPlan $plan, array $progress): array
    {
        /** @var list<array{member_id: int, points: int, rank: int}> $entries */
        $entries = $progress['entries'];

        if ($plan->mode === GoalPlan::MODE_COOPERATION) {
            return $progress['reached'] ? array_column($entries, 'member_id') : [];
        }

        return array_values(array_map(
            fn (array $entry) => $entry['member_id'],
            array_filter($entries, fn (array $entry) => $entry['rank'] === 1 && $entry['points'] > 0),
        ));
    }
}
