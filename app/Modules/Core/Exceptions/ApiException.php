<?php

namespace App\Modules\Core\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A domain error with a machine-readable `code`, rendered as
 * `{ "message": "...", "code": "..." }` with the given HTTP status (see docs/API.md).
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>|null  $errors
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly ?array $errors = null,
    ) {
        parent::__construct($message);
    }

    public static function forbidden(string $message = 'You are not allowed to do this.'): self
    {
        return new self('forbidden', $message, 403);
    }

    public static function noFamily(): self
    {
        return new self('no_family', 'You need to belong to a family for this.', 409);
    }

    public static function alreadyInFamily(): self
    {
        return new self('already_in_family', 'You already belong to a family.', 409);
    }

    public static function memberLimitReached(): self
    {
        return new self('member_limit_reached', 'Your plan does not allow more family members.', 403);
    }

    public static function inviteInvalid(): self
    {
        return new self('invite_invalid', 'This invite code is unknown, already used or expired.', 422);
    }

    public static function invalidCredentials(): self
    {
        return new self('invalid_credentials', 'The email or password is incorrect.', 401);
    }

    public static function categoryNotAllowed(): self
    {
        return new self('category_not_allowed', 'This category is not available for you.', 403);
    }

    public static function sessionNotActive(string $message = 'This play session does not allow this action right now.'): self
    {
        return new self('session_not_active', $message, 409);
    }

    public static function itemAlreadyAnswered(): self
    {
        return new self('item_already_answered', 'This item has already been answered.', 409);
    }

    public static function secondChanceUnavailable(string $message = 'No second chance of this kind is available.'): self
    {
        return new self('second_chance_unavailable', $message, 403);
    }

    /**
     * A 422 with field errors, for domain validation that happens inside an Action.
     */
    public static function validation(string $field, string $message): self
    {
        return new self('validation_failed', $message, 422, [$field => [$message]]);
    }

    public function render(): JsonResponse
    {
        $body = ['message' => $this->getMessage(), 'code' => $this->errorCode];

        if ($this->errors !== null) {
            $body['errors'] = $this->errors;
        }

        return new JsonResponse($body, $this->status);
    }
}
