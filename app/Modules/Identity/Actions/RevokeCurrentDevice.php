<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Logout: deletes the token used for this request and its device row.
 */
class RevokeCurrentDevice
{
    public function handle(User $user): void
    {
        $token = $user->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return;
        }

        Device::query()->where('personal_access_token_id', $token->getKey())->delete();
        $token->delete();
    }
}
