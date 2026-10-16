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

        // GET /me/export builds a full copy of a user's data: 1 per hour per user.
        RateLimiter::for('export', function (Request $request) {
            $user = $request->user();

            return Limit::perHour(1)->by($user instanceof User ? 'export|'.$user->id : 'export-ip|'.$request->ip());
        });

        // Provider webhooks (RevenueCat, AdMob SSV): generous, only a guard against floods.
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(600)->by('webhooks|'.$request->ip()));

        // Public account deletion page: 5 form posts per hour per IP.
        RateLimiter::for('account-deletion', fn (Request $request) => Limit::perHour(5)->by('account-deletion|'.$request->ip()));

        parent::boot();
    }
}
