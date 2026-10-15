<?php

use App\Modules\Billing\Http\Controllers\RevenueCatWebhookController;
use App\Modules\Billing\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

// Loaded under /api/v1 by BillingServiceProvider.

// Public: authenticated by the shared Bearer secret configured in RevenueCat.
Route::post('webhooks/revenuecat', RevenueCatWebhookController::class)->name('api.v1.webhooks.revenuecat');

Route::middleware(['auth:sanctum', 'throttle:api', 'family:admin'])->group(function () {
    Route::get('family/subscription', [SubscriptionController::class, 'show'])->name('api.v1.family.subscription');
});
