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

    public static function notFound(string $message = 'Not found.'): self
    {
        return new self('not_found', $message, 404);
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

    public static function signInCodeInvalid(): self
    {
        return new self('sign_in_code_invalid', 'This sign-in code is unknown, already used or expired.', 422);
    }

    public static function invalidCredentials(): self
    {
        return new self('invalid_credentials', 'The email or password is incorrect.', 401);
    }

    public static function socialTokenInvalid(string $message = 'The sign-in token could not be verified. Please sign in again.'): self
    {
        return new self('social_token_invalid', $message, 401);
    }

    /**
     * A first-time social sign-in without birth data: the app asks for birth year/month and
     * retries with the same token.
     */
    public static function socialSignupIncomplete(): self
    {
        $message = 'Please tell us your birth year and month to finish creating your account.';

        return new self('social_signup_incomplete', $message, 422, [
            'birth_year' => ['The birth year is required for a new account.'],
            'birth_month' => ['The birth month is required for a new account.'],
        ]);
    }

    public static function socialAccountTaken(): self
    {
        return new self('social_account_taken', 'This account is already linked to another Brainy Clan user.', 409);
    }

    public static function lastSignInMethod(): self
    {
        return new self('last_sign_in_method', 'You can\'t remove your only way to sign in. Set a password or link another account first.', 409);
    }

    public static function familyOwnerCannotLeave(): self
    {
        return new self('family_owner_cannot_leave', 'You own a family with other members. Delete the family first to delete your account.', 409);
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

    public static function goalLimitReached(): self
    {
        return new self('goal_limit_reached', 'Your plan does not allow more active goals.', 403);
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
