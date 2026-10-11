<?php

namespace App\Modules\Scoring\Http\Controllers;

use App\Modules\Core\Http\CurrentMember;
use App\Modules\Scoring\Actions\BuildLeaderboard;
use App\Modules\Scoring\Http\Requests\LeaderboardRequest;
use Illuminate\Http\JsonResponse;

class LeaderboardController
{
    use CurrentMember;

    public function __invoke(LeaderboardRequest $request, BuildLeaderboard $leaderboard): JsonResponse
    {
        $period = (string) $request->string('period', 'week');

        return new JsonResponse(['data' => $leaderboard->handle($this->currentMember($request)->family, $period)]);
    }
}
