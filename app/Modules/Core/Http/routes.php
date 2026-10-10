<?php

use App\Modules\Core\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Loaded under /api/v1 by CoreServiceProvider.

Route::get('health', HealthController::class)
    ->middleware('throttle:api')
    ->name('api.v1.health');
