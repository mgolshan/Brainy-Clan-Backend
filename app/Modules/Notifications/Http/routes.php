<?php

use App\Modules\Notifications\Http\Controllers\NotificationPreferenceController;
use App\Modules\Notifications\Http\Controllers\PushTokenController;
use Illuminate\Support\Facades\Route;

// Loaded under /api/v1 by NotificationsServiceProvider.

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::put('me/push-token', [PushTokenController::class, 'update'])->name('api.v1.me.push-token.update');
    Route::delete('me/push-token', [PushTokenController::class, 'destroy'])->name('api.v1.me.push-token.destroy');

    Route::middleware('family')->group(function () {
        Route::get('me/notification-preferences', [NotificationPreferenceController::class, 'show'])->name('api.v1.me.notification-preferences.show');
        Route::patch('me/notification-preferences', [NotificationPreferenceController::class, 'update'])->name('api.v1.me.notification-preferences.update');
    });

    Route::middleware('family:admin')->group(function () {
        Route::get('family/members/{member}/notification-preferences', [NotificationPreferenceController::class, 'showMember'])->whereNumber('member')->name('api.v1.family.members.notification-preferences.show');
        Route::patch('family/members/{member}/notification-preferences', [NotificationPreferenceController::class, 'updateMember'])->whereNumber('member')->name('api.v1.family.members.notification-preferences.update');
    });
});
