<?php

use App\Modules\Core\Http\Middleware\EnsureAppVersion;
use App\Modules\Core\Http\Middleware\EnsureHasFamily;
use App\Modules\Core\Http\Middleware\TouchDevice;
use App\Modules\Core\Support\ApiErrorRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;

// Module routes (/api/v1/...) are registered by each module's service provider
// (see app/Modules/*/Http/routes.php and bootstrap/providers.php).

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global so it answers 426 before auth; it only acts on api/* requests.
        $middleware->append(EnsureAppVersion::class);

        // Production runs behind Caddy on the private Docker network: trust its X-Forwarded-*
        // headers so request()->ip() and https URLs are right. Never a public range.
        $middleware->trustProxies(at: ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);

        $middleware->api(append: [
            TouchDevice::class,
        ]);

        $middleware->alias([
            'family' => EnsureHasFamily::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Error monitoring (B9); a no-op while SENTRY_LARAVEL_DSN is empty. Events are scrubbed
        // by App\Modules\Core\Support\SentryScrubber (config/sentry.php).
        Integration::handles($exceptions);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Contract error format for everything under /api: { message, code, errors? }.
        $exceptions->render(function (Throwable $e, Request $request) {
            return $request->is('api/*') ? ApiErrorRenderer::render($e) : null;
        });
    })->create();
