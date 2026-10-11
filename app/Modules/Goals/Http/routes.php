<?php

use App\Modules\Goals\Http\Controllers\GoalController;
use Illuminate\Support\Facades\Route;

// Loaded under /api/v1 by GoalsServiceProvider.

Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('goals')->group(function () {
    Route::middleware('family')->group(function () {
        Route::get('/', [GoalController::class, 'index'])->name('api.v1.goals.index');
        Route::get('{plan}', [GoalController::class, 'show'])->whereNumber('plan')->name('api.v1.goals.show');
        Route::get('{plan}/history', [GoalController::class, 'history'])->whereNumber('plan')->name('api.v1.goals.history');
    });

    Route::middleware('family:admin')->group(function () {
        Route::post('/', [GoalController::class, 'store'])->name('api.v1.goals.store');
        Route::patch('{plan}', [GoalController::class, 'update'])->whereNumber('plan')->name('api.v1.goals.update');
        Route::delete('{plan}', [GoalController::class, 'destroy'])->whereNumber('plan')->name('api.v1.goals.destroy');
    });
});
