<?php

namespace App\Modules\Core\Docs;

/**
 * The contract's error codes and their HTTP status (mirrors the table in docs/API.md).
 */
final class ErrorCodes
{
    /**
     * @var array<string, array{status: int, meaning: string}>
     */
    public const array ALL = [
        'ssv_signature_invalid' => ['status' => 400, 'meaning' => 'AdMob server-side verification callback with a missing or bad signature'],
        'validation_failed' => ['status' => 422, 'meaning' => 'Field errors in `errors`'],
        'unauthenticated' => ['status' => 401, 'meaning' => 'Missing/invalid token'],
        'invalid_credentials' => ['status' => 401, 'meaning' => 'Wrong email/password'],
        'social_token_invalid' => ['status' => 401, 'meaning' => 'Google/Facebook token could not be verified'],
        'forbidden' => ['status' => 403, 'meaning' => 'Not allowed (e.g. non-admin calling an admin endpoint)'],
        'member_limit_reached' => ['status' => 403, 'meaning' => 'Tier member limit reached'],
        'category_not_allowed' => ['status' => 403, 'meaning' => 'Category outside tier/age policy'],
        'second_chance_unavailable' => ['status' => 403, 'meaning' => 'No second chance of that method left / allowed'],
        'goal_limit_reached' => ['status' => 403, 'meaning' => 'Tier limit on active goal plans reached'],
        'world_locked' => ['status' => 403, 'meaning' => 'That theme world isn\'t unlocked for this member (see `GET /me/worlds`)'],
        'not_found' => ['status' => 404, 'meaning' => 'Not found'],
        'no_family' => ['status' => 409, 'meaning' => 'Endpoint needs a family membership and the user has none'],
        'already_in_family' => ['status' => 409, 'meaning' => 'User already belongs to a family'],
        'social_account_taken' => ['status' => 409, 'meaning' => 'That Google/Facebook account is linked to another user'],
        'last_sign_in_method' => ['status' => 409, 'meaning' => 'Can\'t unlink the only way this user can sign in'],
        'family_owner_cannot_leave' => ['status' => 409, 'meaning' => 'The family owner must delete the family (or be its only member) to delete their account'],
        'session_not_active' => ['status' => 409, 'meaning' => 'Play session is not in a state that allows this action'],
        'item_already_answered' => ['status' => 409, 'meaning' => 'Session item was already answered'],
        'ad_reward_pending' => ['status' => 409, 'meaning' => 'Rewarded-ad callback not received yet — retry shortly'],
        'invite_invalid' => ['status' => 422, 'meaning' => 'Invite code unknown, used or expired'],
        'sign_in_code_invalid' => ['status' => 422, 'meaning' => 'Sign-in code unknown, used or expired'],
        'social_signup_incomplete' => ['status' => 422, 'meaning' => 'New social account: birth year/month needed (adult check)'],
        'app_update_required' => ['status' => 426, 'meaning' => 'App older than `min_app_version`'],
        'rate_limited' => ['status' => 429, 'meaning' => 'Too many requests'],
        'login_locked' => ['status' => 429, 'meaning' => 'Too many failed sign-in attempts; body has `retry_after` (seconds), also sent as the `Retry-After` header'],
    ];

    public static function status(string $code): int
    {
        return self::ALL[$code]['status'] ?? 500;
    }
}
