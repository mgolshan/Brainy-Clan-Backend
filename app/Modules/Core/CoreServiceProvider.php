<?php

namespace App\Modules\Core;

use App\Modules\Identity\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Shared API plumbing: health endpoint and rate limiters
 * (`auth/*` 10/min per IP, everything else 120/min per token).
 */
class CoreServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by('auth|'.$request->ip()));

        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();

            return Limit::perMinute(120)->by($user instanceof User ? 'user|'.$user->id : 'ip|'.$request->ip());
        });

        parent::boot();
    }
}
