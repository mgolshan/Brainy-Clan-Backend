<?php

use App\Modules\Scoring\Http\Controllers\LeaderboardController;
use App\Modules\Scoring\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

// Loaded under /api/v1 by ScoringServiceProvider.

Route::middleware(['auth:sanctum', 'throttle:api', 'family'])->group(function () {
    Route::get('me/stats', StatsController::class)->name('api.v1.me.stats');
    Route::get('family/leaderboard', LeaderboardController::class)->name('api.v1.family.leaderboard');
});
