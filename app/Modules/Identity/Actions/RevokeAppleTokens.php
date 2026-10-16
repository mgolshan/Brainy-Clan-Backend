<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Apple\AppleTokenClient;
use App\Modules\Identity\Models\SocialAccount;
use Illuminate\Support\Collection;

/**
 * Revokes the stored Apple refresh tokens of these social accounts with Apple (unlink and
 * account deletion). Accounts without a stored token are skipped. Never throws: Apple errors and
 * a missing key are logged by the client. Call it after the database transaction.
 */
class RevokeAppleTokens
{
    public function __construct(private readonly AppleTokenClient $apple) {}

    /**
     * @param  Collection<int, SocialAccount>  $accounts
     */
    public function handle(Collection $accounts): void
    {
        foreach ($accounts as $account) {
            if ($account->provider !== SocialAccount::APPLE || $account->refresh_token === null) {
                continue;
            }

            $this->apple->revoke($account->refresh_token, $account->token_client_id ?? (string) collect(config('services.apple.client_ids', []))->first());
        }
    }
}
