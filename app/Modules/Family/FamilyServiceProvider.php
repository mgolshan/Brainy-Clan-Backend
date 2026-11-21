<?php

namespace App\Modules\Family;

use App\Modules\Core\ModuleServiceProvider;
use App\Modules\Identity\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class FamilyServiceProvider extends ModuleServiceProvider
{
    /** Classroom code cards (docs/API.md "Classroom tools" → "Code cards"): calls per hour per family. */
    public const int CODE_CARDS_PER_HOUR = 10;

    public function boot(): void
    {
        RateLimiter::for('classroom-codes', function (Request $request) {
            $user = $request->user();
            $familyId = $user instanceof User ? $user->activeMembership()?->family_id : null;

            return Limit::perHour(self::CODE_CARDS_PER_HOUR)->by($familyId !== null ? 'classroom-codes|'.$familyId : 'classroom-codes-ip|'.$request->ip());
        });

        parent::boot();
    }
}
