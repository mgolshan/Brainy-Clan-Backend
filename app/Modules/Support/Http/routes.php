<?php

use App\Modules\Support\Http\Controllers\SupportMessageController;
use Illuminate\Support\Facades\Route;

// Loaded under /api/v1 by SupportServiceProvider. Family admins only (ResolveSupportSender → 403);
// the 5/hour + 20/day limits count created messages only (SubmitSupportMessage), so retries with
// the same client_id never hit them.

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('support/messages', [SupportMessageController::class, 'index'])->name('api.v1.support.messages.index');
    Route::post('support/messages', [SupportMessageController::class, 'store'])->name('api.v1.support.messages.store');
});
