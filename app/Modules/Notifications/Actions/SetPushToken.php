<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Stores (or clears) the Expo push token on the device making this request. A token that moves
 * to another device or account is removed from its previous device, so one phone never gets
 * another user's notifications. Logout deletes the device row and so its token.
 */
class SetPushToken
{
    public function handle(User $user, ?string $pushToken, ?string $platform = null): void
    {
        $device = $this->currentDevice($user);

        if ($pushToken !== null) {
            Device::query()->where('push_token', $pushToken)->whereKeyNot($device->id)->update(['push_token' => null]);
        }

        $device->push_token = $pushToken;

        if ($platform !== null) {
            $device->platform = $platform;
        }

        $device->save();
    }

    private function currentDevice(User $user): Device
    {
        $token = $user->currentAccessToken();
        $device = $token instanceof PersonalAccessToken
            ? Device::query()->where('personal_access_token_id', $token->getKey())->first()
            : null;

        if ($device === null) {
            throw ApiException::validation('token', 'This sign-in is not bound to a device.');
        }

        return $device;
    }
}
