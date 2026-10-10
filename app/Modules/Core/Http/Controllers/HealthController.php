<?php

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Support\Iso;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Http\JsonResponse;

class HealthController
{
    public function __invoke(PolicyResolver $policy): JsonResponse
    {
        return new JsonResponse(['data' => [
            'status' => 'ok',
            'api_version' => '1',
            'min_app_version' => $policy->minAppVersion(),
            'time' => Iso::format(now()),
        ]]);
    }
}
