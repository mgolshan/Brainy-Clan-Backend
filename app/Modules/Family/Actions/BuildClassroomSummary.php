<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Support\Iso;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Support\FamilyClock;
use App\Modules\Scoring\Models\PointEntry;
use App\Modules\Scoring\Models\Streak;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Class summary (docs/API.md "Classroom tools" → "Class summary export"): one row per active member,
 * for `GET /family/classroom/summary` (JSON) and `summary.csv`.
 *
 * - `period=season` (default) counts from `families.season_started_at`, or everything when there is
 *   no season; `period=all` counts everything.
 * - `points`: the member's ledger entries in this family within the period (corrections included).
 * - `games`: play sessions with at least one answer in the period; `accuracy_percent`: correct
 *   regular answers / regular answers in the period (redemption items left out, like `answered`),
 *   rounded, `null` without answers; `last_played_at`: the member's latest answer in the period.
 * - `current_streak` / `longest_streak`: the member's streak record (not period-bound); the current
 *   streak is 0 once a day was missed, like `/me/stats`.
 * - Rows sorted by nickname. Four queries for any class size (members, points, play, streaks).
 */
class BuildClassroomSummary
{
    public const string PERIOD_SEASON = 'season';

    public const string PERIOD_ALL = 'all';

    public const array PERIODS = [self::PERIOD_SEASON, self::PERIOD_ALL];

    /** CSV columns, in order (the JSON row keys). */
    public const array COLUMNS = ['member_id', 'nickname', 'role', 'points', 'games', 'accuracy_percent', 'current_streak', 'longest_streak', 'last_played_at'];

    /**
     * @return array{generated_at: string, season_started_at: string|null, rows: list<array{member_id: int, nickname: string, role: string, points: int, games: int, accuracy_percent: int|null, current_streak: int, longest_streak: int, last_played_at: string|null}>}
     */
    public function handle(Family $family, string $period = self::PERIOD_SEASON): array
    {
        $since = $period === self::PERIOD_SEASON ? $family->season_started_at : null;
        $members = $family->members()->orderBy('id')->get(['id', 'family_id', 'user_id', 'role', 'nickname']);
        $memberIds = $members->modelKeys();

        $points = $this->points($family, $memberIds, $since);
        $play = $this->play($family, $memberIds, $since);
        $streaks = Streak::query()->whereIn('member_id', $memberIds)->get()->keyBy('member_id');
        $yesterday = FamilyClock::today($family)->subDay();

        $rows = $members->map(function (FamilyMember $member) use ($points, $play, $streaks, $yesterday, $family): array {
            $stats = $play[$member->id] ?? null;
            $answered = (int) ($stats->answered ?? 0);
            /** @var Streak|null $streak */
            $streak = $streaks->get($member->id);
            $alive = $streak?->last_active_date !== null
                && CarbonImmutable::parse($streak->last_active_date->format('Y-m-d'), $family->timezone)->greaterThanOrEqualTo($yesterday);

            return [
                'member_id' => $member->id,
                'nickname' => $member->nickname,
                'role' => $member->role,
                'points' => $points[$member->id] ?? 0,
                'games' => (int) ($stats->games ?? 0),
                'accuracy_percent' => $answered > 0 ? (int) round(((int) $stats->correct) * 100 / $answered) : null,
                'current_streak' => $alive ? (int) $streak->current : 0,
                'longest_streak' => (int) ($streak->longest ?? 0),
                'last_played_at' => $stats?->last_played_at !== null ? Iso::format(CarbonImmutable::parse($stats->last_played_at, 'UTC')) : null,
            ];
        })->sort(fn (array $a, array $b): int => [mb_strtolower($a['nickname']), $a['member_id']] <=> [mb_strtolower($b['nickname']), $b['member_id']])->values()->all();

        return [
            'generated_at' => (string) Iso::format(now()),
            'season_started_at' => Iso::format($family->season_started_at),
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<int>  $memberIds
     * @return array<int, int> member id => points
     */
    private function points(Family $family, array $memberIds, ?CarbonInterface $since): array
    {
        return PointEntry::query()
            ->where('family_id', $family->id)
            ->whereIn('member_id', $memberIds)
            ->when($since !== null, fn ($query) => $query->where('created_at', '>=', $since))
            ->groupBy('member_id')
            ->selectRaw('member_id, SUM(amount) as points')
            ->pluck('points', 'member_id')
            ->map(fn ($points) => (int) $points)
            ->all();
    }

    /**
     * Games, answers and the latest answer per member, in one grouped query.
     *
     * @param  list<int>  $memberIds
     * @return array<int, object{games: int|string, answered: int|string|null, correct: int|string|null, last_played_at: string|null}>
     */
    private function play(Family $family, array $memberIds, ?CarbonInterface $since): array
    {
        if ($memberIds === []) {
            return [];
        }

        /** @var array<int, object{games: int|string, answered: int|string|null, correct: int|string|null, last_played_at: string|null}> $rows */
        $rows = DB::table('session_items')
            ->join('play_sessions', 'play_sessions.id', '=', 'session_items.play_session_id')
            ->where('play_sessions.family_id', $family->id)
            ->whereIn('play_sessions.member_id', $memberIds)
            ->whereNotNull('session_items.answered_at')
            ->when($since !== null, fn ($query) => $query->where('session_items.answered_at', '>=', $since))
            ->groupBy('play_sessions.member_id')
            ->selectRaw('play_sessions.member_id as member_id')
            ->selectRaw('COUNT(DISTINCT play_sessions.id) as games')
            ->selectRaw('SUM(CASE WHEN session_items.is_redemption = 0 THEN 1 ELSE 0 END) as answered')
            ->selectRaw('SUM(CASE WHEN session_items.is_redemption = 0 AND session_items.is_correct = 1 THEN 1 ELSE 0 END) as correct')
            ->selectRaw('MAX(session_items.answered_at) as last_played_at')
            ->get()
            ->keyBy('member_id')
            ->all();

        return $rows;
    }
}
