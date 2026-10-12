<?php

namespace App\Modules\Core\Docs;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;
use ReflectionMethod;

/**
 * Scramble operation extension: replaces the default error responses with the contract's
 * `{ "message", "code", "errors"? }` envelope (docs/API.md "Errors") and lists every error
 * code an endpoint can return, grouped by status. Codes come from:
 * - the route's middleware (`auth:sanctum` → 401 `unauthenticated`, `family` → 409 `no_family`,
 *   `family:admin` → also 403 `forbidden`, `throttle:*` → 429 `rate_limited`, every API route →
 *   426 `app_update_required` except the health check);
 * - path ids (`{member}`, `{session}`, …) → 404 `not_found`;
 * - a Form Request / a 422 Scramble inferred → 422 `validation_failed`;
 * - the controller method's `#[ApiErrors(...)]` attribute (domain errors thrown by Actions).
 */
class ErrorResponsesExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $codes = [];
        $kept = [];

        foreach ($operation->responses ?? [] as $response) {
            $status = (int) ($response instanceof Reference ? $response->resolve()->code : $response->code);

            if ($status >= 400) {
                if ($status === 422) {
                    $codes[] = 'validation_failed';
                }

                continue;
            }

            $kept[] = $response;
        }

        $middleware = $routeInfo->route->gatherMiddleware();
        $uri = $routeInfo->route->uri();

        if (in_array('auth:sanctum', $middleware, true)) {
            $codes[] = 'unauthenticated';
        }

        if (in_array('family', $middleware, true) || in_array('family:admin', $middleware, true)) {
            $codes[] = 'no_family';
        }

        if (in_array('family:admin', $middleware, true)) {
            $codes[] = 'forbidden';
        }

        if (preg_match('/\{(?!provider)[a-z_]+\}/', $uri) === 1) {
            $codes[] = 'not_found';
        }

        if (collect($middleware)->contains(fn ($name) => is_string($name) && Str::startsWith($name, 'throttle'))) {
            $codes[] = 'rate_limited';
        }

        if (! str_ends_with($uri, '/health')) {
            $codes[] = 'app_update_required';
        }

        $method = $routeInfo->reflectionAction();

        if ($method instanceof ReflectionMethod) {
            foreach ($method->getAttributes(ApiErrors::class) as $attribute) {
                array_push($codes, ...$attribute->newInstance()->codes);
            }
        }

        $byStatus = collect(array_unique($codes))
            ->groupBy(fn (string $code) => ErrorCodes::status($code))
            ->sortKeys();

        foreach ($byStatus as $status => $statusCodes) {
            $kept[] = $this->response((int) $status, $statusCodes->values()->all());
        }

        $operation->responses = $kept;
    }

    /**
     * @param  list<string>  $codes
     */
    private function response(int $status, array $codes): Response
    {
        $body = new ObjectType;
        $body->addProperty('message', (new StringType)->setDescription('Human readable, safe to show.'));
        $body->addProperty('code', (new StringType)->enum($codes)->setDescription('Machine-readable error code.'));

        if ($status === 422) {
            $messages = (new ArrayType)->setItems(new StringType);
            $body->addProperty('errors', (new ObjectType)->additionalProperties($messages)->setDescription('Field name → messages.'));
        }

        $body->setRequired(['message', 'code']);

        $description = collect($codes)
            ->map(fn (string $code) => '`'.$code.'`: '.(ErrorCodes::ALL[$code]['meaning'] ?? ''))
            ->implode("\n\n");

        return Response::make($status)
            ->setDescription($description)
            ->setContent('application/json', Schema::fromType($body));
    }
}
