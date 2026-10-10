<?php

use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\MeController;
use Illuminate\Support\Facades\Route;

// Loaded under /api/v1 by IdentityServiceProvider.

Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->name('api.v1.auth.register');
    Route::post('login', [AuthController::class, 'login'])->name('api.v1.auth.login');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('api.v1.auth.logout');
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('me', [MeController::class, 'show'])->name('api.v1.me.show');
    Route::patch('me', [MeController::class, 'update'])->name('api.v1.me.update');
});
