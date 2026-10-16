<?php

namespace App\Modules\Identity\Apple;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Talks to `https://appleid.apple.com/auth/token` and `/auth/revoke`. The client secret is an
 * ES256 JWT signed with the Sign in with Apple key (`services.apple.private_key`, `key_id`,
 * `team_id`): `iss` = Team ID, `aud` = `https://appleid.apple.com`, `sub` = the client id.
 */
class HttpAppleTokenClient implements AppleTokenClient
{
    public const string TOKEN_URL = 'https://appleid.apple.com/auth/token';

    public const string REVOKE_URL = 'https://appleid.apple.com/auth/revoke';

    public function exchangeCode(string $authorizationCode, string $clientId): ?string
    {
        $secret = $this->clientSecret($clientId);

        if ($secret === null) {
            return null;
        }

        try {
            $response = Http::asForm()->timeout(10)->post(self::TOKEN_URL, [
                'client_id' => $clientId,
                'client_secret' => $secret,
                'code' => $authorizationCode,
                'grant_type' => 'authorization_code',
            ]);
        } catch (Throwable $e) {
            Log::warning('Apple authorization code exchange failed.', ['error' => $e->getMessage()]);

            return null;
        }

        $token = $response->json('refresh_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            Log::warning('Apple authorization code exchange rejected.', ['status' => $response->status(), 'error' => $response->json('error')]);

            return null;
        }

        return $token;
    }

    public function revoke(string $refreshToken, string $clientId): void
    {
        $secret = $this->clientSecret($clientId);

        if ($secret === null) {
            return;
        }

        try {
            $response = Http::asForm()->timeout(10)->post(self::REVOKE_URL, [
                'client_id' => $clientId,
                'client_secret' => $secret,
                'token' => $refreshToken,
                'token_type_hint' => 'refresh_token',
            ]);

            if (! $response->successful()) {
                Log::warning('Apple token revocation rejected.', ['status' => $response->status(), 'error' => $response->json('error')]);
            }
        } catch (Throwable $e) {
            Log::warning('Apple token revocation failed.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * The ES256 client secret, or null (logged) when the key is not configured.
     */
    private function clientSecret(string $clientId): ?string
    {
        $teamId = config('services.apple.team_id');
        $keyId = config('services.apple.key_id');
        $key = config('services.apple.private_key');

        if (! is_string($teamId) || $teamId === '' || ! is_string($keyId) || $keyId === '' || ! is_string($key) || $key === '') {
            Log::info('Apple token exchange/revocation skipped: APPLE_TEAM_ID, APPLE_KEY_ID or APPLE_PRIVATE_KEY is not configured.');

            return null;
        }

        try {
            return JWT::encode([
                'iss' => $teamId,
                'iat' => time(),
                'exp' => time() + 300,
                'aud' => 'https://appleid.apple.com',
                'sub' => $clientId,
            ], str_replace('\n', "\n", $key), 'ES256', $keyId);
        } catch (Throwable $e) {
            Log::warning('Apple client secret could not be signed (check APPLE_PRIVATE_KEY).', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
