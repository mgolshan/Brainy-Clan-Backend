<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\Device;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Signs one device out: deletes its Sanctum token and the device row.
 */
class RevokeDevice
{
    public function handle(Device $device): void
    {
        if ($device->personal_access_token_id !== null) {
            PersonalAccessToken::query()->whereKey($device->personal_access_token_id)->delete();
        }

        $device->delete();
    }
}
