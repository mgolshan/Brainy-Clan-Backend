<?php

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Actions\RunHealthChecks;
use App\Modules\Core\Docs\ApiErrors;
use App\Modules\Core\Exceptions\ApiException;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Health')]
class DeepHealthController
{
    /**
     * Deep health check (monitoring).
     *
     * For the uptime monitor, not the app. Needs the `X-Health-Token` header (env
     * `HEALTH_DEEP_TOKEN`); without a configured token it answers 404. Checks MySQL, Redis, the
     * scheduler heartbeat (younger than 3 minutes) and Horizon (critical: any failure → 503
     * with the same body) and counts failed jobs in the last hour (warning only:
     * `status: degraded`, still 200). Never returns error messages, hosts or versions.
     * Rate-limited to 30 requests per minute per IP.
     *
     * @unauthenticated
     *
     * @response array{data: array{status: 'ok'|'degraded'|'fail', checks: array{database: array{status: 'ok'|'fail'}, redis: array{status: 'ok'|'fail'}, scheduler: array{status: 'ok'|'fail', age_seconds: int|null}, horizon: array{status: 'ok'|'fail', state: 'running'|'paused'|'inactive'|'unknown'}, failed_jobs: array{status: 'ok'|'warn'|'fail', last_hour: int|null}}, time: string}}
     */
    #[ApiErrors('unauthenticated', 'not_found')]
    #[HeaderParameter('X-Health-Token', description: 'The value of HEALTH_DEEP_TOKEN', required: true, type: 'string')]
    public function __invoke(Request $request, RunHealthChecks $checks): JsonResponse
    {
        $token = config('ops.health.deep_token');

        if (! is_string($token) || $token === '') {
            throw ApiException::notFound();
        }

        $given = $request->header('X-Health-Token');

        if (! is_string($given) || ! hash_equals($token, $given)) {
            throw new AuthenticationException;
        }

        $result = $checks->handle();

        return new JsonResponse(['data' => $result], $result['status'] === 'fail' ? 503 : 200, ['Cache-Control' => 'no-store']);
    }
}
