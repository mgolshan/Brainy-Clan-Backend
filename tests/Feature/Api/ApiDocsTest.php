<?php

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The Scramble OpenAPI spec (docs/PLAN.md "Full API documentation with Scramble") builds and
 * documents every /api/v1 route with the contract error envelope.
 */
beforeEach(function () {
    Gate::define('viewApiDocs', fn ($user = null): bool => true);
});

/**
 * @return array<string, mixed>
 */
function apiSpec(): array
{
    return test()->getJson('/docs/api.json')->assertOk()->json();
}

it('documents every /api/v1 route', function () {
    $spec = apiSpec();

    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route) => Str::startsWith($route->uri(), 'api/v1/'))
        ->flatMap(fn (LaravelRoute $route) => collect($route->methods())
            ->reject(fn (string $method) => $method === 'HEAD')
            ->map(fn (string $method) => strtolower($method).' '.Str::after($route->uri(), 'api/v1')));

    expect($routes)->not->toBeEmpty();

    $documented = collect($spec['paths'])
        ->flatMap(fn (array $operations, string $path) => collect(array_keys($operations))->map(fn ($method) => "{$method} {$path}"));

    expect($routes->diff($documented)->values()->all())->toBe([]);
});

it('groups endpoints by module', function () {
    $spec = apiSpec();

    expect(collect($spec['tags'])->pluck('name')->all())
        ->toBe(['Health', 'Identity', 'Family', 'Content', 'Play', 'Scoring', 'Goals']);

    foreach ($spec['paths'] as $path => $operations) {
        foreach ($operations as $method => $operation) {
            expect($operation['tags'] ?? [])->toHaveCount(1, "{$method} {$path} needs exactly one module tag");
        }
    }
});

it('documents errors in the contract envelope with their codes', function () {
    $spec = apiSpec();

    $link = $spec['paths']['/me/social-accounts/{provider}']['post']['responses'];
    expect($link['409']['content']['application/json']['schema']['properties']['code']['enum'])->toBe(['social_account_taken'])
        ->and($link['401']['content']['application/json']['schema']['properties']['code']['enum'])->toContain('unauthenticated', 'social_token_invalid')
        ->and($link['422']['content']['application/json']['schema']['properties'])->toHaveKeys(['message', 'code', 'errors']);

    $answer = $spec['paths']['/play/sessions/{session}/answers']['post']['responses'];
    expect($answer['409']['content']['application/json']['schema']['properties']['code']['enum'])
        ->toContain('session_not_active', 'item_already_answered')
        ->and($answer['200']['content']['application/json']['schema']['properties']['data']['properties']['session'])
        ->toBe(['$ref' => '#/components/schemas/SessionResource']);

    $entries = $spec['paths']['/family/leaderboard']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['properties']['entries'];
    expect($entries['items']['required'])->toBe(['member_id', 'nickname', 'avatar', 'points', 'rank']);

    foreach ($spec['paths'] as $operations) {
        foreach ($operations as $operation) {
            foreach ($operation['responses'] as $status => $response) {
                if ((int) $status >= 400) {
                    expect($response['content']['application/json']['schema']['required'])->toBe(['message', 'code']);
                }
            }
        }
    }
});
