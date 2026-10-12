<?php

namespace App\Modules\Identity\Social;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Identity\Models\SocialAccount;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verifies a Google **ID token** (a JWT signed by Google): RS256 signature against Google's
 * published keys (JWKS, cached), `iss` is Google, `aud` is one of our OAuth client IDs
 * (`services.google.client_ids`: web, Android, iOS), `exp` not passed. The email is used only
 * when `email_verified` is true.
 *
 * Socialite's Google driver is not used for this: it accepts a single client ID and ignores
 * `email_verified`. The JWT library (`firebase/php-jwt`) is the one Socialite itself uses.
 */
class GoogleIdTokenVerifier implements SocialTokenVerifier
{
    public const string JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    public const string CACHE_KEY = 'social:google:jwks';

    private const array ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    /** Allowed clock skew in seconds for `exp` / `iat` / `nbf`. */
    private const int LEEWAY = 60;

    public function verify(string $token): SocialIdentity
    {
        $audiences = $this->audiences();

        if ($audiences === []) {
            Log::warning('Google sign-in attempted but no GOOGLE_CLIENT_ID_* is configured.');

            throw ApiException::socialTokenInvalid('Google sign-in is not configured on this server.');
        }

        $kid = $this->keyId($token);

        if ($kid === null) {
            throw ApiException::socialTokenInvalid();
        }

        try {
            $jwks = $this->jwks();

            if (! $this->hasKey($jwks, $kid)) {
                // Google rotates its keys; refetch once before giving up.
                $jwks = $this->jwks(refresh: true);
            }

            JWT::$leeway = self::LEEWAY;
            $claims = (array) JWT::decode($token, JWK::parseKeySet($jwks, 'RS256'));
        } catch (Throwable $e) {
            report_if(! str_contains($e::class, 'Firebase\\JWT'), $e);

            throw ApiException::socialTokenInvalid();
        }

        $issuer = $claims['iss'] ?? null;
        $audience = $claims['aud'] ?? null;
        $subject = $claims['sub'] ?? null;

        if (! in_array($issuer, self::ISSUERS, true)
            || ! is_string($audience) || ! in_array($audience, $audiences, true)
            || ! isset($claims['exp'])
            || ! is_string($subject) || $subject === '') {
            throw ApiException::socialTokenInvalid();
        }

        $emailVerified = ($claims['email_verified'] ?? false) === true || ($claims['email_verified'] ?? null) === 'true';
        $email = $emailVerified && is_string($claims['email'] ?? null) ? strtolower($claims['email']) : null;
        $name = collect([$claims['given_name'] ?? null, $claims['name'] ?? null])
            ->first(fn ($value) => is_string($value) && trim($value) !== '');

        return new SocialIdentity(SocialAccount::GOOGLE, $subject, $email, $name);
    }

    /**
     * @return list<string>
     */
    private function audiences(): array
    {
        $ids = (array) config('services.google.client_ids', []);

        return array_values(array_filter($ids, fn ($id) => is_string($id) && $id !== ''));
    }

    private function keyId(string $token): ?string
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        try {
            $header = json_decode(JWT::urlsafeB64Decode($parts[0]), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($header) && is_string($header['kid'] ?? null) ? $header['kid'] : null;
    }

    /**
     * @param  array<string, mixed>  $jwks
     */
    private function hasKey(array $jwks, string $kid): bool
    {
        foreach ((array) ($jwks['keys'] ?? []) as $key) {
            if (is_array($key) && ($key['kid'] ?? null) === $kid) {
                return true;
            }
        }

        return false;
    }

    /**
     * Google's public keys, cached for the `max-age` Google sends (usually several hours).
     *
     * @return array<string, mixed>
     */
    private function jwks(bool $refresh = false): array
    {
        if (! $refresh) {
            $cached = Cache::get(self::CACHE_KEY);

            if (is_array($cached)) {
                return $cached;
            }
        }

        $response = Http::acceptJson()->timeout(10)->get(self::JWKS_URL)->throw();

        /** @var array<string, mixed> $jwks */
        $jwks = (array) $response->json();

        preg_match('/max-age=(\d+)/', (string) $response->header('Cache-Control'), $matches);
        $ttl = isset($matches[1]) ? max(60, (int) $matches[1]) : 3600;

        Cache::put(self::CACHE_KEY, $jwks, $ttl);

        return $jwks;
    }
}
