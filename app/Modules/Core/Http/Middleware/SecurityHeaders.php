<?php

namespace App\Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strict headers for the public HTML pages (legal pages, account deletion page): no scripts, no
 * framing, no referrer, inline styles only. `:forms` allows forms posting to this site.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next, string $mode = 'static'): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $formAction = $mode === 'forms' ? "'self'" : "'none'";
        $response->headers->set('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; base-uri 'none'; form-action {$formAction}; frame-ancestors 'none'");
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
