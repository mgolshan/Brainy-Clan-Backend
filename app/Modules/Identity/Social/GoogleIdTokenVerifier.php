<?php

namespace App\Modules\Identity\Social;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Identity\Models\SocialAccount;
use Illuminate\Support\Facades\Log;

/**
 * Verifies a Google **ID token** (a JWT signed by Google): RS256 signature against Google's
 * published keys (JWKS, cached), `iss` is Google, `aud` is one of our OAuth client IDs
 * (`services.google.client_ids`: web, Android, iOS), `exp` not passed. The email is used only
 * when `email_verified` is true.
 *
 * Socialite's Google driver is not used for this: it accepts a single client ID and ignores
 * `email_verified`. The JWT library (`firebase/php-jwt`) is the one Socialite itself uses.
 */
class GoogleIdTokenVerifier extends JwksIdTokenVerifier
{
    public const string JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    public const string CACHE_KEY = 'social:google:jwks';

    private const array ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    public function verify(string $token, ?string $nonce = null): SocialIdentity
    {
        $audiences = $this->audiences();

        if ($audiences === []) {
            Log::warning('Google sign-in attempted but no GOOGLE_CLIENT_ID_* is configured.');

            throw ApiException::socialTokenInvalid('Google sign-in is not configured on this server.');
        }

        $claims = $this->decode($token);
        $audience = $claims['aud'] ?? null;

        if (! in_array($claims['iss'] ?? null, self::ISSUERS, true)
            || ! is_string($audience) || ! in_array($audience, $audiences, true)) {
            throw ApiException::socialTokenInvalid();
        }

        $name = collect([$claims['given_name'] ?? null, $claims['name'] ?? null])
            ->first(fn ($value) => is_string($value) && trim($value) !== '');

        return new SocialIdentity(SocialAccount::GOOGLE, (string) $claims['sub'], $this->verifiedEmail($claims), $name);
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
        $ids = (array) config('services.google.client_ids', []);

        return array_values(array_filter($ids, fn ($id) => is_string($id) && $id !== ''));
    }
}
