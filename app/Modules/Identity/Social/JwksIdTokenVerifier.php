<?php

namespace App\Modules\Identity\Social;

use App\Modules\Core\Exceptions\ApiException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Shared plumbing for OpenID Connect ID tokens (Google, Apple): RS256 signature against the
 * provider's published keys (JWKS, cached; refetched once on an unknown `kid` because providers
 * rotate keys). Subclasses check the claims (`iss`, `aud`, `nonce`, email).
 */
abstract class JwksIdTokenVerifier implements SocialTokenVerifier
{
    /** Allowed clock skew in seconds for `exp` / `iat` / `nbf`. */
    protected const int LEEWAY = 60;

    abstract protected function jwksUrl(): string;

    abstract protected function cacheKey(): string;

    /**
     * Verifies the signature and `exp`/`nbf` and returns the claims.
     *
     * @return array<string, mixed>
     *
     * @throws ApiException `social_token_invalid`
     */
    protected function decode(string $token): array
    {
        $kid = $this->keyId($token);

        if ($kid === null) {
            throw ApiException::socialTokenInvalid();
        }

        try {
            $jwks = $this->jwks();

            if (! $this->hasKey($jwks, $kid)) {
                $jwks = $this->jwks(refresh: true);
            }

            JWT::$leeway = static::LEEWAY;

            /** @var array<string, mixed> $claims */
            $claims = (array) JWT::decode($token, JWK::parseKeySet($jwks, 'RS256'));
        } catch (Throwable $e) {
            report_if(! str_contains($e::class, 'Firebase\\JWT'), $e);

            throw ApiException::socialTokenInvalid();
        }

        if (! isset($claims['exp']) || ! is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
            throw ApiException::socialTokenInvalid();
        }

        return $claims;
    }

    /**
     * `email_verified` is a boolean for Google and may be the string "true" for Apple.
     *
     * @param  array<string, mixed>  $claims
     */
    protected function verifiedEmail(array $claims): ?string
    {
        $verified = ($claims['email_verified'] ?? false) === true || ($claims['email_verified'] ?? null) === 'true';
        $email = $claims['email'] ?? null;

        return $verified && is_string($email) && $email !== '' ? strtolower($email) : null;
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
     * The provider's public keys, cached for the `max-age` it sends (default 1 hour).
     *
     * @return array<string, mixed>
     */
    private function jwks(bool $refresh = false): array
    {
        if (! $refresh) {
            $cached = Cache::get($this->cacheKey());

            if (is_array($cached)) {
                return $cached;
            }
        }

        $response = Http::acceptJson()->timeout(10)->get($this->jwksUrl())->throw();

        /** @var array<string, mixed> $jwks */
        $jwks = (array) $response->json();

        preg_match('/max-age=(\d+)/', (string) $response->header('Cache-Control'), $matches);
        $ttl = isset($matches[1]) ? max(60, (int) $matches[1]) : 3600;

        Cache::put($this->cacheKey(), $jwks, $ttl);

        return $jwks;
    }
}
