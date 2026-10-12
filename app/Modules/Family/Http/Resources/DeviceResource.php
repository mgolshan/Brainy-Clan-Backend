<?php

namespace App\Modules\Family\Http\Resources;

use App\Modules\Core\Support\Iso;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A signed-in device of a member. `is_current` marks the device making this request.
 *
 * @property Device $resource
 */
class DeviceResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, platform: string, app_version: string|null, last_seen_at: string|null, is_current: bool}
     */
    public function toArray(Request $request): array
    {
        $device = $this->resource;
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;
        $currentTokenId = $token instanceof PersonalAccessToken ? $token->getKey() : null;

        return [
            'id' => $device->id,
            'name' => $device->name,
            'platform' => $device->platform,
            'app_version' => $device->app_version,
            'last_seen_at' => Iso::format($device->last_seen_at),
            'is_current' => $currentTokenId !== null && $device->personal_access_token_id === $currentTokenId,
        ];
    }
}
