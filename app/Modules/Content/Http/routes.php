<?php

use App\Modules\Content\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

// Loaded under /api/v1 by ContentServiceProvider.

Route::middleware(['auth:sanctum', 'throttle:api', 'family'])->group(function () {
    Route::get('categories', [CategoryController::class, 'index'])->name('api.v1.categories.index');
});
