<?php

namespace App\Modules\Scoring\Http\Controllers;

use App\Modules\Core\Http\CurrentMember;
use App\Modules\Scoring\Actions\BuildLeaderboard;
use App\Modules\Scoring\Http\Requests\LeaderboardRequest;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Scoring', 'Points, streaks and the family leaderboard (from the points ledger).', 5)]
class LeaderboardController
{
    use CurrentMember;

    /**
     * Family leaderboard.
     *
     * Points per member in the period (`today`, `week` (default), `month`, `all`), computed in the
     * family's timezone from the points ledger. `starts_at` inclusive, `ends_at` exclusive (both
     * `null` for `all`). Entries are sorted by `rank`; ties share a rank.
     *
     * @response array{data: array{period: 'today'|'week'|'month'|'all', starts_at: string|null, ends_at: string|null, team_total: int, entries: list<array{member_id: int, nickname: string, avatar: string|null, points: int, rank: int}>}}
     */
    public function __invoke(LeaderboardRequest $request, BuildLeaderboard $leaderboard): JsonResponse
    {
        $period = (string) $request->string('period', 'week');

        return new JsonResponse(['data' => $leaderboard->handle($this->currentMember($request)->family, $period)]);
    }
}
