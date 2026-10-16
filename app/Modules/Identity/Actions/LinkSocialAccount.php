<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Identity\Models\SocialAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Social\SocialTokenVerifiers;
use Illuminate\Support\Facades\DB;

/**
 * `POST /me/social-accounts/{provider}`: links a Google/Facebook/Apple account to the signed-in adult
 * (e.g. after joining by invite). 409 `social_account_taken` when that provider account belongs
 * to another user. Linking another account of the same provider replaces the old link.
 */
class LinkSocialAccount
{
    public function __construct(
        private readonly SocialTokenVerifiers $verifiers,
        private readonly EnsureSocialSignInAllowed $ensureAllowed,
        private readonly StoreAppleRefreshToken $storeAppleToken,
    ) {}

    public function handle(User $user, string $provider, string $token, ?string $nonce = null, ?string $authorizationCode = null): SocialAccount
    {
        $this->ensureAllowed->handle($user);

        $identity = $this->verifiers->for($provider)->verify($token, $nonce);

        $account = DB::transaction(function () use ($user, $identity): SocialAccount {
            $owner = SocialAccount::query()
                ->where('provider', $identity->provider)
                ->where('provider_user_id', $identity->providerUserId)
                ->value('user_id');

            if ($owner !== null && (int) $owner !== $user->id) {
                throw ApiException::socialAccountTaken();
            }

            return SocialAccount::query()->updateOrCreate(
                ['user_id' => $user->id, 'provider' => $identity->provider],
                ['provider_user_id' => $identity->providerUserId, 'email' => $identity->email, 'linked_at' => now()],
            );
        });

        if ($authorizationCode !== null) {
            $this->storeAppleToken->handle($account, $authorizationCode, $identity->audience);
        }

        return $account;
    }
}
