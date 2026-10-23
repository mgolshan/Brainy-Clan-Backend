<?php

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Support\Website;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On the website routes (routes/web.php): when host routing is on (`WEBSITE_HOST` set) and the
 * page was requested on another host (the API host), redirect to the same path on the website
 * host. Old links such as `https://api.brainyclan.com/account/delete` keep working.
 */
class RedirectToWebsiteHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $websiteHost = Website::host();

        if ($websiteHost !== null && strtolower($request->getHost()) !== $websiteHost) {
            return RouteByHost::redirect($request, Website::url($request->getRequestUri()));
        }

        return $next($request);
    }
}
