<?php

namespace App\Modules\Identity\Social;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Identity\Models\SocialAccount;
use Illuminate\Support\Facades\Log;

/**
 * Verifies a Sign in with Apple **identity token** (a JWT signed by Apple): RS256 signature
 * against Apple's keys (JWKS, cached), `iss` is `https://appleid.apple.com`, `aud` is one of
 * `services.apple.client_ids` (the iOS bundle ID and the web Services ID), `exp` not passed.
 * When the app sends the raw `nonce` it gave Apple as SHA-256 hex, the token's `nonce` claim
 * must equal `sha256(raw nonce)` (replay protection).
 *
 * Apple only puts verified addresses in the token (`email_verified` may be the string "true");
 * a private relay address (`…@privaterelay.appleid.com`, `is_private_email`) still counts as
 * verified. The token never carries the user's name: the app sends it as `display_name` on the
 * first authorization, so the identity's `name` is always null.
 */
class AppleIdTokenVerifier extends JwksIdTokenVerifier
{
    public const string JWKS_URL = 'https://appleid.apple.com/auth/keys';

    public const string CACHE_KEY = 'social:apple:jwks';

    public const string ISSUER = 'https://appleid.apple.com';

    public function verify(string $token, ?string $nonce = null): SocialIdentity
    {
        $audiences = $this->audiences();

        if ($audiences === []) {
            Log::warning('Apple sign-in attempted but APPLE_CLIENT_IDS is not configured.');

            throw ApiException::socialTokenInvalid('Sign in with Apple is not configured on this server.');
        }

        $claims = $this->decode($token);
        $audience = $claims['aud'] ?? null;

        if (($claims['iss'] ?? null) !== self::ISSUER
            || ! is_string($audience) || ! in_array($audience, $audiences, true)) {
            throw ApiException::socialTokenInvalid();
        }

        if ($nonce !== null && $nonce !== '') {
            $claimed = $claims['nonce'] ?? null;

            if (! is_string($claimed) || ! hash_equals(hash('sha256', $nonce), strtolower($claimed))) {
                throw ApiException::socialTokenInvalid();
            }
        }

        return new SocialIdentity(SocialAccount::APPLE, (string) $claims['sub'], $this->verifiedEmail($claims), null);
    }

    public static function isPrivateRelay(?string $email): bool
    {
        return $email !== null && str_ends_with($email, '@privaterelay.appleid.com');
    }

    protected function jwksUrl(): string
    {
        return self::JWKS_URL;
    }

    protected function cacheKey(): string
    {
        return self::CACHE_KEY;
    }

    /**
     * @return list<string>
     */
    private function audiences(): array
    {
        $ids = config('services.apple.client_ids', []);

        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        return array_values(array_filter(array_map(fn ($id) => is_string($id) ? trim($id) : '', (array) $ids), fn (string $id) => $id !== ''));
    }
}
