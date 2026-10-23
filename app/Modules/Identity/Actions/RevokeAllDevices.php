<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\User;

/**
 * Signs a user out everywhere: deletes all their Sanctum tokens and device rows (like
 * `RevokeDevice` for every device, in two queries).
 */
class RevokeAllDevices
{
    public function handle(User $user): void
    {
        Device::query()->where('user_id', $user->id)->delete();
        $user->tokens()->delete();
    }
}
