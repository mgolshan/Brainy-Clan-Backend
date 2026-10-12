<?php

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Support\Iso;
use App\Modules\Policy\PolicyResolver;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Health', 'Health check and the minimum app version.', 0)]
class HealthController
{
    /**
     * Health check.
     *
     * Public, exempt from the app version check. `min_app_version` comes from the settings.
     */
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
