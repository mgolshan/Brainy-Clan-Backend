<?php

use App\Modules\Family\Http\Controllers\AccountDeletionController;
use App\Modules\Family\Http\Controllers\ClassroomController;
use App\Modules\Family\Http\Controllers\FamilyController;
use App\Modules\Family\Http\Controllers\InviteController;
use App\Modules\Family\Http\Controllers\MemberController;
use App\Modules\Family\Http\Controllers\MemberDeviceController;
use App\Modules\Family\Http\Controllers\SignInCodeController;
use App\Modules\Family\Http\Controllers\WorldController;
use Illuminate\Support\Facades\Route;

// Loaded under /api/v1 by FamilyServiceProvider.

Route::middleware('throttle:auth')->group(function () {
    Route::post('auth/join', [InviteController::class, 'join'])->name('api.v1.auth.join');
    Route::post('auth/code', [SignInCodeController::class, 'redeem'])->name('api.v1.auth.code');
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('families', [FamilyController::class, 'store'])->name('api.v1.families.store');

    // Account deletion (docs/API.md "Account deletion"); owner check for DELETE /family in the controller.
    Route::get('me/deletion-check', [AccountDeletionController::class, 'check'])->name('api.v1.me.deletion-check');
    Route::delete('me', [AccountDeletionController::class, 'destroyMe'])->name('api.v1.me.destroy');
    Route::delete('family', [AccountDeletionController::class, 'destroyFamily'])->middleware('family:admin')->name('api.v1.family.destroy');

    Route::middleware('family')->group(function () {
        Route::get('family', [FamilyController::class, 'show'])->name('api.v1.family.show');
        Route::patch('me/accessibility', [MemberController::class, 'updateAccessibility'])->name('api.v1.me.accessibility');
        Route::get('me/worlds', [WorldController::class, 'me'])->name('api.v1.me.worlds');

        // Admins, or the member themself (checked in the Action).
        Route::get('family/members/{member}/devices', [MemberDeviceController::class, 'index'])->whereNumber('member')->name('api.v1.family.members.devices.index');
        Route::delete('family/members/{member}/devices/{device}', [MemberDeviceController::class, 'destroy'])->whereNumber(['member', 'device'])->name('api.v1.family.members.devices.destroy');
    });

    Route::middleware('family:admin')->group(function () {
        Route::patch('family', [FamilyController::class, 'update'])->name('api.v1.family.update');
        Route::patch('family/members/{member}', [MemberController::class, 'update'])->whereNumber('member')->name('api.v1.family.members.update');
        Route::delete('family/members/{member}', [MemberController::class, 'destroy'])->whereNumber('member')->name('api.v1.family.members.destroy');
        Route::post('family/members/{member}/sign-in-codes', [SignInCodeController::class, 'store'])->whereNumber('member')->name('api.v1.family.members.sign-in-codes.store');
        Route::get('family/members/{member}/worlds', [WorldController::class, 'forMember'])->whereNumber('member')->name('api.v1.family.members.worlds');
        Route::get('family/invites', [InviteController::class, 'index'])->name('api.v1.family.invites.index');
        Route::get('family/invites/preview', [InviteController::class, 'preview'])->name('api.v1.family.invites.preview');
        Route::post('family/invites', [InviteController::class, 'store'])->name('api.v1.family.invites.store');
        Route::delete('family/invites/{invite}', [InviteController::class, 'destroy'])->whereNumber('invite')->name('api.v1.family.invites.destroy');

        // Classroom tools (docs/API.md "Classroom tools"): 403 `plan_required` on other tiers; all but
        // the consent itself need the current consent statement (409 `classroom_consent_required`).
        Route::prefix('family/classroom')->group(function () {
            Route::post('consent', [ClassroomController::class, 'consent'])->middleware('classroom')->name('api.v1.family.classroom.consent');

            Route::middleware('classroom:consent')->group(function () {
                Route::post('roster', [ClassroomController::class, 'roster'])->name('api.v1.family.classroom.roster');
                Route::post('sign-in-codes', [ClassroomController::class, 'codeCards'])->middleware('throttle:classroom-codes')->name('api.v1.family.classroom.sign-in-codes');
                Route::get('summary', [ClassroomController::class, 'summary'])->name('api.v1.family.classroom.summary');
                Route::get('summary.csv', [ClassroomController::class, 'summaryCsv'])->name('api.v1.family.classroom.summary-csv');
                Route::post('new-year', [ClassroomController::class, 'newYear'])->name('api.v1.family.classroom.new-year');
            });
        });
    });
});
