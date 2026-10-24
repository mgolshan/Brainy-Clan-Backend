<?php

namespace App\Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strict headers for the public HTML pages (legal pages, account deletion page): no scripts, no
 * framing, no referrer, inline styles only. `:forms` allows forms posting to this site.
 * `:script` (the help center) also allows GET forms to this site and same-origin scripts that
 * carry this request's nonce (`<script src="/js/…" nonce="{{ SecurityHeaders::nonce() }}">`);
 * nothing inline runs, nothing loads from another host.
 */
class SecurityHeaders
{
    /** Request attribute holding the `:script` mode's CSP nonce. */
    public const string NONCE_ATTRIBUTE = 'csp_nonce';

    public function handle(Request $request, Closure $next, string $mode = 'static'): Response
    {
        $scriptSrc = '';

        if ($mode === 'script') {
            $nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
            $request->attributes->set(self::NONCE_ATTRIBUTE, $nonce);
            $scriptSrc = " script-src 'nonce-{$nonce}';";
        }

        /** @var Response $response */
        $response = $next($request);

        $formAction = $mode === 'static' ? "'none'" : "'self'";
        $response->headers->set('Content-Security-Policy', "default-src 'none';{$scriptSrc} style-src 'unsafe-inline'; img-src 'self' data:; base-uri 'none'; form-action {$formAction}; frame-ancestors 'none'");
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * This request's script nonce (`:script` mode), or null on pages without scripts.
     */
    public static function nonce(?Request $request = null): ?string
    {
        $nonce = ($request ?? request())->attributes->get(self::NONCE_ATTRIBUTE);

        return is_string($nonce) ? $nonce : null;
    }
}
