<?php

namespace App\Modules\Identity\Social;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Identity\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\FacebookProvider;
use Throwable;

/**
 * Verifies a Facebook **access token** from the app's Facebook Login:
 * 1. Graph API `debug_token` (with our app access token): valid, not expired and issued for
 *    **our** app id — without this check a token from any other Facebook app would pass.
 * 2. `/me?fields=id,name,email` via Socialite (it sends `appsecret_proof`); the id must match.
 * Facebook only returns confirmed emails, so a returned email counts as verified.
 */
class FacebookTokenVerifier implements SocialTokenVerifier
{
    public function verify(string $token, ?string $nonce = null): SocialIdentity
    {
        $appId = (string) config('services.facebook.client_id');
        $secret = (string) config('services.facebook.client_secret');

        if ($appId === '' || $secret === '') {
            Log::warning('Facebook sign-in attempted but FACEBOOK_CLIENT_ID / FACEBOOK_CLIENT_SECRET are not configured.');

            throw ApiException::socialTokenInvalid('Facebook sign-in is not configured on this server.');
        }

        if ($this->looksLikeLimitedLoginToken($token)) {
            throw ApiException::socialTokenInvalid('Facebook Limited Login tokens are not supported. Please sign in with a standard Facebook login.');
        }

        $version = (string) config('services.facebook.graph_version', 'v23.0');

        try {
            $debug = Http::acceptJson()->timeout(10)->get("https://graph.facebook.com/{$version}/debug_token", [
                'input_token' => $token,
                'access_token' => $appId.'|'.$secret,
            ]);
        } catch (Throwable $e) {
            report($e);

            throw ApiException::socialTokenInvalid();
        }

        $data = $debug->json('data');

        if (! $debug->successful()
            || ! is_array($data)
            || ($data['is_valid'] ?? false) !== true
            || (string) ($data['app_id'] ?? '') !== $appId
            || (string) ($data['user_id'] ?? '') === '') {
            throw ApiException::socialTokenInvalid();
        }

        try {
            /** @var FacebookProvider $provider */
            $provider = Socialite::driver('facebook');
            $user = $provider->usingGraphVersion($version)->fields(['first_name', 'name', 'email'])->userFromToken($token);
        } catch (Throwable) {
            throw ApiException::socialTokenInvalid();
        }

        if ((string) $user->getId() !== (string) $data['user_id']) {
            throw ApiException::socialTokenInvalid();
        }

        $email = $user->getEmail();
        $firstName = $user->getRaw()['first_name'] ?? null;

        return new SocialIdentity(
            SocialAccount::FACEBOOK,
            (string) $user->getId(),
            is_string($email) && $email !== '' ? strtolower($email) : null,
            is_string($firstName) && trim($firstName) !== '' ? $firstName : $user->getName(),
        );
    }

    /**
     * iOS "Limited Login" returns an OIDC JWT (`header.payload.signature`, JSON header) instead
     * of an opaque access token; it can't be checked with `debug_token`, so it is refused.
     */
    private function looksLikeLimitedLoginToken(string $token): bool
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return false;
        }

        $header = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/'), true), true);

        return is_array($header) && isset($header['alg']);
    }
}
