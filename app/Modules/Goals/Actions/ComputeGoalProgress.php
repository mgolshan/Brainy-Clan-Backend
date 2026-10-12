<?php

namespace App\Modules\Goals\Actions;

use App\Modules\Content\Support\CategoryTree;
use App\Modules\Core\Support\Iso;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Goals\Models\GoalPeriod;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Scoring\Actions\BuildLeaderboard;
use App\Modules\Scoring\Models\PointEntry;
use Illuminate\Support\Collection;

/**
 * GoalProgress (docs/API.md) for a period, always computed from the points ledger: points
 * inside the window, in the plan's scope subtrees, by the period's members. Closed periods
 * return their stored result.
 *
 * - cooperation: `reached` when the team total meets the period target.
 * - competition: `reached` when at least one member met their own target.
 * Each entry's `target` is the member's snapshotted target (`target_override ?? target_points`).
 *
 * `many()` computes several open periods with a constant number of queries (one grouped ledger
 * query with a conditional sum per period, one member query), so listing plans never runs a
 * query per plan. Each period's `plan` relation must be set (with `categories` loaded, or it is
 * queried once per plan).
 */
class ComputeGoalProgress
{
    public function __construct(private readonly CategoryTree $tree) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(GoalPeriod $period): array
    {
        return $this->many(collect([$period]))[$period->id];
    }

    /**
     * @param  Collection<int, GoalPeriod>  $periods
     * @return array<int, array<string, mixed>> keyed by period id
     */
    public function many(Collection $periods): array
    {
        $results = [];
        $open = [];

        foreach ($periods as $period) {
            if ($period->status === GoalPeriod::STATUS_CLOSED && $period->result !== null) {
                $results[$period->id] = $period->result;
            } else {
                $open[] = $period;
            }
        }

        if ($open === []) {
            return $results;
        }

        /** @var array<int, array{period: GoalPeriod, targets: array<int, int>, categories: list<int>}> $windows */
        $windows = [];

        foreach ($open as $period) {
            $plan = $period->plan;
            $categoryIds = $plan->relationLoaded('categories')
                ? $plan->categories->pluck('id')
                : $plan->categories()->pluck('categories.id');

            $windows[$period->id] = [
                'period' => $period,
                'targets' => collect($period->member_targets)->mapWithKeys(fn ($target, $id) => [(int) $id => (int) $target])->all(),
                'categories' => $categoryIds->flatMap(fn ($id) => $this->tree->subtreeIds((int) $id))->unique()->values()->all(),
            ];
        }

        $totals = $this->ledgerTotals($windows);
        $memberIds = collect($windows)->flatMap(fn (array $window) => array_keys($window['targets']))->unique()->values()->all();
        $members = $memberIds === [] ? collect() : FamilyMember::query()->with('user')->whereIn('id', $memberIds)->get()->keyBy('id');

        foreach ($windows as $periodId => $window) {
            $results[$periodId] = $this->progress($window['period'], $window['targets'], $members, $totals[$periodId] ?? []);
        }

        return $results;
    }

    /**
     * Points per (period, member) in one grouped query: a conditional SUM per period over the
     * union of the periods' families, members and time ranges.
     *
     * @param  array<int, array{period: GoalPeriod, targets: array<int, int>, categories: list<int>}>  $windows
     * @return array<int, array<int, int>> period id => member id => points
     */
    private function ledgerTotals(array $windows): array
    {
        $windows = array_filter($windows, fn (array $window) => $window['targets'] !== [] && $window['categories'] !== []);

        if ($windows === []) {
            return [];
        }

        $query = PointEntry::query()->select('member_id');
        $familyIds = [];
        $memberIds = [];
        $start = null;
        $end = null;

        foreach ($windows as $periodId => $window) {
            $period = $window['period'];
            $memberIds = [...$memberIds, ...array_keys($window['targets'])];
            $familyIds[] = $period->plan->family_id;
            $start = $start === null || $period->starts_at->lt($start) ? $period->starts_at : $start;
            $end = $end === null || $period->ends_at->gt($end) ? $period->ends_at : $end;

            $memberMarks = implode(',', array_fill(0, count($window['targets']), '?'));
            $categoryMarks = implode(',', array_fill(0, count($window['categories']), '?'));

            $query->selectRaw(
                "SUM(CASE WHEN family_id = ? AND created_at >= ? AND created_at < ? AND member_id IN ({$memberMarks}) AND category_id IN ({$categoryMarks}) THEN amount ELSE 0 END) AS p{$periodId}",
                [$period->plan->family_id, $period->starts_at, $period->ends_at, ...array_keys($window['targets']), ...$window['categories']],
            );
        }

        $rows = $query
            ->whereIn('family_id', array_values(array_unique($familyIds)))
            ->whereIn('member_id', array_values(array_unique($memberIds)))
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->groupBy('member_id')
            ->toBase()
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            foreach (array_keys($windows) as $periodId) {
                $totals[$periodId][(int) $row->member_id] = (int) ($row->{"p{$periodId}"} ?? 0);
            }
        }

        return $totals;
    }

    /**
     * @param  array<int, int>  $targets
     * @param  Collection<int, FamilyMember>  $members
     * @param  array<int, int>  $totals
     * @return array<string, mixed>
     */
    private function progress(GoalPeriod $period, array $targets, Collection $members, array $totals): array
    {
        $plan = $period->plan;

        $rows = collect($targets)
            ->map(fn (int $target, int $memberId) => $members->get($memberId))
            ->filter()
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
