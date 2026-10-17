<?php

use App\Modules\Play\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

// Loaded under /api/v1 by PlayServiceProvider.

Route::middleware(['auth:sanctum', 'throttle:api', 'family'])->prefix('play/sessions')->group(function () {
    Route::post('/', [SessionController::class, 'store'])->name('api.v1.play.sessions.store');
    Route::get('current', [SessionController::class, 'current'])->name('api.v1.play.sessions.current');
    Route::get('{session}', [SessionController::class, 'show'])->whereNumber('session')->name('api.v1.play.sessions.show');
    Route::post('{session}/answers', [SessionController::class, 'answer'])->whereNumber('session')->name('api.v1.play.sessions.answer');
    Route::post('{session}/second-chance', [SessionController::class, 'secondChance'])->whereNumber('session')->name('api.v1.play.sessions.second-chance');
    Route::post('{session}/redemption', [SessionController::class, 'redemption'])->whereNumber('session')->name('api.v1.play.sessions.redemption');
    Route::post('{session}/abandon', [SessionController::class, 'abandon'])->whereNumber('session')->name('api.v1.play.sessions.abandon');
});
