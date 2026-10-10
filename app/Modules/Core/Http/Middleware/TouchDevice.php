<?php

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps `devices.last_seen_at` / `app_version` fresh for the token in use
 * (written at most every 5 minutes to keep requests cheap).
 */
class TouchDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->bearerToken() === null) {
            return $response;
        }

        $user = Auth::guard('sanctum')->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;

        if ($token instanceof PersonalAccessToken) {
            $device = Device::query()->where('personal_access_token_id', $token->getKey())->first();
            $version = $request->header('X-App-Version');

            if ($device !== null && ($device->last_seen_at === null || $device->last_seen_at->lt(now()->subMinutes(5)))) {
                $device->forceFill([
                    'last_seen_at' => now(),
                    'app_version' => is_string($version) && $version !== '' ? $version : $device->app_version,
                ])->save();
            }
        }

        return $response;
    }
}
