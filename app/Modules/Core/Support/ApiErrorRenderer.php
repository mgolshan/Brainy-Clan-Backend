<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Exceptions\ApiException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renders every exception thrown under `api/*` in the contract's error format:
 * `{ "message": "...", "code": "snake_case", "errors": {...} }` (`errors` only on 422).
 */
class ApiErrorRenderer
{
    public static function render(Throwable $e): ?JsonResponse
    {
        return match (true) {
            $e instanceof HttpResponseException => null,
            $e instanceof ApiException => $e->render(),
            $e instanceof ValidationException => self::json(422, 'validation_failed', $e->getMessage(), $e->errors()),
            $e instanceof AuthenticationException => self::json(401, 'unauthenticated', 'Unauthenticated.'),
            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException => self::json(403, 'forbidden', $e->getMessage() !== '' && $e->getMessage() !== 'This action is unauthorized.' ? $e->getMessage() : 'You are not allowed to do this.'),
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => self::json(404, 'not_found', 'Not found.'),
            $e instanceof ThrottleRequestsException => self::json(429, 'rate_limited', 'Too many requests. Please slow down.', null, $e->getHeaders()),
            $e instanceof MethodNotAllowedHttpException => self::json(405, 'method_not_allowed', 'Method not allowed.', null, $e->getHeaders()),
            $e instanceof HttpExceptionInterface => self::json($e->getStatusCode(), 'http_error', $e->getMessage() !== '' ? $e->getMessage() : 'Request failed.', null, $e->getHeaders()),
            default => config('app.debug') ? null : self::json(500, 'server_error', 'Something went wrong.'),
        };
    }

    /**
     * @param  array<string, array<int, string>>|null  $errors
     * @param  array<string, string>  $headers
     */
    private static function json(int $status, string $code, string $message, ?array $errors = null, array $headers = []): JsonResponse
    {
        $body = ['message' => $message, 'code' => $code];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return new JsonResponse($body, $status, $headers);
    }
}
