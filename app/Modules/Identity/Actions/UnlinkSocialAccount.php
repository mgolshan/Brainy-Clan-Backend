<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Identity\Models\SocialAccount;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * `DELETE /me/social-accounts/{provider}`: 404 when that provider isn't linked; 409
 * `last_sign_in_method` when the user has no password and no other linked provider.
 */
class UnlinkSocialAccount
{
    public function __construct(private readonly RevokeAppleTokens $revokeAppleTokens) {}

    public function handle(User $user, string $provider): void
    {
        $removed = DB::transaction(function () use ($user, $provider): SocialAccount {
            $accounts = SocialAccount::query()->where('user_id', $user->id)->lockForUpdate()->get();
            $account = $accounts->firstWhere('provider', $provider);

            if ($account === null) {
                throw ApiException::notFound();
            }

            if ($user->password === null && $accounts->count() === 1) {
                throw ApiException::lastSignInMethod();
            }

            $account->delete();

            return $account;
        });

        $this->revokeAppleTokens->handle(collect([$removed]));
    }
}
