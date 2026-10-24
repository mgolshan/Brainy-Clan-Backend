<?php

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Security pass (B9): every /api/v1 route is authenticated with Sanctum unless it is on the explicit
 * public allowlist, and every route is rate-limited. A new public endpoint must be added here on purpose.
 */
const PUBLIC_API_ROUTES = [
    'GET api/v1/health',
    // Monitoring only: protected by the X-Health-Token header (404 without HEALTH_DEEP_TOKEN).
    'GET api/v1/health/deep',
    'GET api/v1/legal',
    'POST api/v1/auth/register',
    'POST api/v1/auth/login',
    'POST api/v1/auth/password/forgot',
    'POST api/v1/auth/social/{provider}',
    'POST api/v1/auth/join',
    'POST api/v1/auth/code',
    'POST api/v1/webhooks/revenuecat',
    'POST api/v1/webhooks/paddle',
    'GET api/v1/pricing',
    'GET api/v1/webhooks/admob-ssv',
];

/**
 * @return Collection<string, list<string>>
 */
function apiRouteMiddleware(): Collection
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route) => Str::startsWith($route->uri(), 'api/v1/'))
        ->mapWithKeys(fn (LaravelRoute $route) => [
            collect($route->methods())->reject(fn ($method) => $method === 'HEAD')->implode('|').' '.$route->uri() => $route->gatherMiddleware(),
        ]);
}

it('requires a Sanctum token on every /api/v1 route except the public allowlist', function () {
    $routes = apiRouteMiddleware();

    $unauthenticated = $routes
        ->reject(fn (array $middleware) => in_array('auth:sanctum', $middleware, true))
        ->keys()
        ->sort()
        ->values()
        ->all();

    expect($unauthenticated)->toBe(collect(PUBLIC_API_ROUTES)->sort()->values()->all());
});

it('rate-limits every /api/v1 route', function () {
    $unthrottled = apiRouteMiddleware()
        ->reject(fn (array $middleware) => collect($middleware)->contains(fn ($name) => is_string($name) && Str::startsWith($name, 'throttle:')))
        ->keys()
        ->all();

    expect($unthrottled)->toBe([]);
});

it('keeps admin-only family routes behind the family:admin middleware', function () {
    $adminRoutes = apiRouteMiddleware()
        ->filter(fn (array $middleware, string $route) => Str::contains($route, ['family/invites', 'sign-in-codes', 'family/subscription', 'family/members/{member}/worlds', 'family/web-checkout', 'family/referral-code']) || $route === 'PATCH api/v1/family')
        ->reject(fn (array $middleware) => in_array('family:admin', $middleware, true))
        ->keys()
        ->all();

    expect($adminRoutes)->toBe([]);
});

it('keeps the family chat routes authenticated, rate-limited and the post route on its own limiter', function () {
    $chat = apiRouteMiddleware()->filter(fn (array $middleware, string $route) => Str::contains($route, 'family/chat'));

    expect($chat->keys()->sort()->values()->all())->toBe([
        'DELETE api/v1/family/chat/messages/{message}',
        'GET api/v1/family/chat/messages',
        'GET api/v1/family/chat/unread',
        'POST api/v1/family/chat/messages',
        'POST api/v1/family/chat/read',
    ]);

    foreach ($chat as $middleware) {
        expect($middleware)->toContain('auth:sanctum')->toContain('throttle:api');
    }

    expect($chat['POST api/v1/family/chat/messages'])->toContain('throttle:chat-post');
});

it('never runs production with APP_DEBUG on', function () {
    $entrypoint = (string) file_get_contents(base_path('docker/prod/entrypoint.sh'));

    expect($entrypoint)->toContain('APP_DEBUG')->toContain('exit 1')
        ->and((string) file_get_contents(base_path('.env.prod.example')))->toContain("APP_DEBUG=false\n");
});
