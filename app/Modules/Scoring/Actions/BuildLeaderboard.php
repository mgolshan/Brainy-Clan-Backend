<?php

namespace App\Modules\Scoring\Actions;

use App\Modules\Core\Support\Iso;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Support\FamilyClock;
use App\Modules\Scoring\Models\PointEntry;

/**
 * Family leaderboard for today / week / month / all, from the ledger in the family's timezone.
 * Ties share a rank (1, 1, 3).
 */
class BuildLeaderboard
{
    public const array PERIODS = ['today', 'week', 'month', 'all'];

    /**
     * @return array{period: string, starts_at: string|null, ends_at: string|null, team_total: int, entries: list<array<string, mixed>>}
     */
    public function handle(Family $family, string $period = 'week'): array
    {
        $start = $end = null;

        $query = PointEntry::query()->where('family_id', $family->id);

        if ($period !== 'all') {
            [$start, $end] = FamilyClock::window($family, $period);
            $query->where('created_at', '>=', $start)->where('created_at', '<', $end);
        }

        /** @var array<int, int> $totals */
        $totals = $query->groupBy('member_id')->selectRaw('member_id, SUM(amount) as points')->pluck('points', 'member_id')
            ->map(fn ($points) => (int) $points)->all();

        $members = $family->members()->with('user')->get();

        $rows = $members->map(fn (FamilyMember $m) => [
            'member_id' => $m->id,
            'nickname' => $m->nickname,
            'avatar' => $m->user->avatar,
            'points' => $totals[$m->id] ?? 0,
        ])->sortBy([['points', 'desc'], ['nickname', 'asc']])->values()->all();

        return [
            'period' => $period,
            'starts_at' => Iso::format($start),
            'ends_at' => Iso::format($end),
            'team_total' => array_sum(array_column($rows, 'points')),
            'entries' => self::rank($rows),
        ];
    }

    /**
     * Competition ranking on `points` (already sorted descending).
     *
     * @param  list<array<string, mixed>>  $rows  each with an int `points`
     * @return list<array<string, mixed>> the rows with `rank` added
     */
    public static function rank(array $rows): array
    {
        $ranked = [];
        $previous = null;
        $rank = 0;

        foreach ($rows as $index => $row) {
            $points = (int) $row['points'];

            if ($previous === null || $points !== $previous) {
                $rank = $index + 1;
                $previous = $points;
            }

            $ranked[] = [...$row, 'rank' => $rank];
        }

        return $ranked;
    }
}
