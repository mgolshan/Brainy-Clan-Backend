<?php

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Support\Website;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global host routing (only when `WEBSITE_HOST` is set, i.e. in production):
 * `www.<website host>` → the website host (same path), and the API/admin/Horizon paths
 * (`Website::API_HOST_PATHS`) requested on the website host → the API host (`APP_URL`).
 * Website paths requested on the API host are redirected by `RedirectToWebsiteHost`.
 */
class RouteByHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $websiteHost = Website::host();

        if ($websiteHost === null) {
            return $next($request);
        }

        $host = strtolower($request->getHost());

        if ($host === 'www.'.$websiteHost) {
            return self::redirect($request, Website::url($request->getRequestUri()));
        }

        if ($host === $websiteHost && $request->is(Website::API_HOST_PATHS)) {
            return self::redirect($request, rtrim((string) config('app.url'), '/').$request->getRequestUri());
        }

        return $next($request);
    }

    /**
     * 301 for GET/HEAD; 308 keeps the method and body of anything else.
     */
    public static function redirect(Request $request, string $url): RedirectResponse
    {
        return new RedirectResponse($url, $request->isMethodCacheable() ? 301 : 308);
    }
}
