<?php

use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\MeController;
use App\Modules\Identity\Http\Controllers\SocialAccountController;
use App\Modules\Identity\Http\Controllers\SocialAuthController;
use App\Modules\Identity\Models\SocialAccount;
use Illuminate\Support\Facades\Route;

// Loaded under /api/v1 by IdentityServiceProvider.

Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->name('api.v1.auth.register');
    Route::post('login', [AuthController::class, 'login'])->name('api.v1.auth.login');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('api.v1.auth.logout');
    Route::post('social/{provider}', [SocialAuthController::class, 'signIn'])->whereIn('provider', SocialAccount::PROVIDERS)->name('api.v1.auth.social');
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('me', [MeController::class, 'show'])->name('api.v1.me.show');
    Route::patch('me', [MeController::class, 'update'])->name('api.v1.me.update');
    Route::put('me/password', [MeController::class, 'password'])->name('api.v1.me.password');

    Route::get('me/social-accounts', [SocialAccountController::class, 'index'])->name('api.v1.me.social-accounts.index');
    Route::post('me/social-accounts/{provider}', [SocialAccountController::class, 'store'])->whereIn('provider', SocialAccount::PROVIDERS)->name('api.v1.me.social-accounts.store');
    Route::delete('me/social-accounts/{provider}', [SocialAccountController::class, 'destroy'])->whereIn('provider', SocialAccount::PROVIDERS)->name('api.v1.me.social-accounts.destroy');
});
