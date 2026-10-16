<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Apple\AppleTokenClient;
use App\Modules\Identity\Models\SocialAccount;

/**
 * The app sends Apple's one-time `authorization_code` with an Apple sign-in / link: exchange it
 * for a refresh token and keep it (encrypted) on the Apple social account, so it can be revoked
 * on unlink and account deletion. A previously stored token for this account is revoked first.
 * Skipped silently when the server has no Apple key (see `HttpAppleTokenClient`).
 */
class StoreAppleRefreshToken
{
    public function __construct(
        private readonly AppleTokenClient $apple,
        private readonly RevokeAppleTokens $revoke,
    ) {}

    public function handle(SocialAccount $account, string $authorizationCode, ?string $clientId): void
    {
        $clientId ??= $this->defaultClientId();

        if ($account->provider !== SocialAccount::APPLE || $clientId === null) {
            return;
        }

        $token = $this->apple->exchangeCode($authorizationCode, $clientId);

        if ($token === null) {
            return;
        }

        if ($account->refresh_token !== null && $account->refresh_token !== $token) {
            $this->revoke->handle(collect([$account]));
        }

        $account->forceFill(['refresh_token' => $token, 'token_client_id' => $clientId])->save();
    }

    private function defaultClientId(): ?string
    {
        $ids = config('services.apple.client_ids', []);
        $first = is_array($ids) ? ($ids[0] ?? null) : null;

        return is_string($first) && $first !== '' ? $first : null;
    }
}
