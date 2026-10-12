<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\User;

/**
 * Creates a Sanctum token for one device and the `devices` row bound to it.
 * Returns the plain-text token (shown to the app once).
 */
class IssueDeviceToken
{
    public function handle(User $user, string $deviceName, string $platform, ?string $appVersion = null): string
    {
        return $this->issue($user, $deviceName, $platform, $appVersion)['token'];
    }

    /**
     * @return array{token: string, device: Device}
     */
    public function issue(User $user, string $deviceName, string $platform, ?string $appVersion = null): array
    {
        $token = $user->createToken($deviceName);

        $device = Device::query()->create([
            'user_id' => $user->id,
            'personal_access_token_id' => $token->accessToken->getKey(),
            'name' => $deviceName,
            'platform' => $platform,
            'app_version' => $appVersion,
            'last_seen_at' => now(),
        ]);

        return ['token' => $token->plainTextToken, 'device' => $device];
    }
}
