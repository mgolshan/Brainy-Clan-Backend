<?php

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Policy\PolicyResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers 426 `app_update_required` when the `X-App-Version` header is lower than the
 * `min_app_version` setting. Requests without the header pass. The health route is exempt.
 */
class EnsureAppVersion
{
    public function __construct(private readonly PolicyResolver $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $version = $request->header('X-App-Version');

        // Runs as global middleware (before routing and auth), so it matches on the path.
        if (is_string($version) && $version !== '' && $request->is('api/*') && ! $request->is('api/v1/health')) {
            $minimum = $this->policy->minAppVersion();

            if (version_compare($version, $minimum, '<')) {
                throw new ApiException(
                    'app_update_required',
                    "Please update the app (minimum version {$minimum}).",
                    426,
                );
            }
        }

        return $next($request);
    }
}
