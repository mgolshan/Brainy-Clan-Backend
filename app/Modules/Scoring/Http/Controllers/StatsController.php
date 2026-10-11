<?php

namespace App\Modules\Scoring\Http\Controllers;

use App\Modules\Core\Http\CurrentMember;
use App\Modules\Scoring\Actions\ComputeMemberStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController
{
    use CurrentMember;

    public function __invoke(Request $request, ComputeMemberStats $stats): JsonResponse
    {
        return new JsonResponse(['data' => $stats->handle($this->currentMember($request))]);
    }
}
