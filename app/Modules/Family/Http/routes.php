<?php

use App\Modules\Family\Http\Controllers\FamilyController;
use App\Modules\Family\Http\Controllers\InviteController;
use App\Modules\Family\Http\Controllers\MemberController;
use Illuminate\Support\Facades\Route;

// Loaded under /api/v1 by FamilyServiceProvider.

Route::post('auth/join', [InviteController::class, 'join'])
    ->middleware('throttle:auth')
    ->name('api.v1.auth.join');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('families', [FamilyController::class, 'store'])->name('api.v1.families.store');

    Route::middleware('family')->group(function () {
        Route::get('family', [FamilyController::class, 'show'])->name('api.v1.family.show');
        Route::patch('me/accessibility', [MemberController::class, 'updateAccessibility'])->name('api.v1.me.accessibility');
    });

    Route::middleware('family:admin')->group(function () {
        Route::patch('family', [FamilyController::class, 'update'])->name('api.v1.family.update');
        Route::patch('family/members/{member}', [MemberController::class, 'update'])->whereNumber('member')->name('api.v1.family.members.update');
        Route::delete('family/members/{member}', [MemberController::class, 'destroy'])->whereNumber('member')->name('api.v1.family.members.destroy');
        Route::get('family/invites', [InviteController::class, 'index'])->name('api.v1.family.invites.index');
        Route::post('family/invites', [InviteController::class, 'store'])->name('api.v1.family.invites.store');
        Route::delete('family/invites/{invite}', [InviteController::class, 'destroy'])->whereNumber('invite')->name('api.v1.family.invites.destroy');
    });
});
