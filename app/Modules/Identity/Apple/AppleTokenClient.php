<?php

namespace App\Modules\Identity\Apple;

/**
 * Sign in with Apple token endpoints (account deletion, App Store guideline 5.1.1(v)):
 * exchange the app's one-time `authorization_code` for a refresh token, and revoke it.
 * Implementations never throw: without the key configured or on Apple errors they log and
 * return null / do nothing. Faked in tests.
 */
interface AppleTokenClient
{
    /**
     * Exchanges an authorization code (issued for `$clientId`) for a refresh token.
     */
    public function exchangeCode(string $authorizationCode, string $clientId): ?string;

    /**
     * Revokes a refresh token issued for `$clientId`.
     */
    public function revoke(string $refreshToken, string $clientId): void;
}
