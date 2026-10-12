<?php

namespace App\Modules\Scoring\Http\Controllers;

use App\Modules\Core\Http\CurrentMember;
use App\Modules\Scoring\Actions\ComputeMemberStats;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Scoring')]
class StatsController
{
    use CurrentMember;

    /**
     * My points and streak.
     *
     * Points today / this week / total (family timezone) and the current and longest streak.
     */
    public function __invoke(Request $request, ComputeMemberStats $stats): JsonResponse
    {
        return new JsonResponse(['data' => $stats->handle($this->currentMember($request))]);
    }
}
