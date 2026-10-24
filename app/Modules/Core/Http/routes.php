<?php

use App\Modules\Core\Http\Controllers\DeepHealthController;
use App\Modules\Core\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Loaded under /api/v1 by CoreServiceProvider.

Route::get('health', HealthController::class)
    ->middleware('throttle:api')
    ->name('api.v1.health');

// Monitoring only (B11): token in `X-Health-Token`, 404 when HEALTH_DEEP_TOKEN is not set.
Route::get('health/deep', DeepHealthController::class)
    ->middleware('throttle:health-deep')
    ->name('api.v1.health.deep');
