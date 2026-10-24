<?php

use App\Modules\Billing\Http\Controllers\AdMobSsvController;
use App\Modules\Billing\Http\Controllers\PaddleWebhookController;
use App\Modules\Billing\Http\Controllers\PricingController;
use App\Modules\Billing\Http\Controllers\RevenueCatWebhookController;
use App\Modules\Billing\Http\Controllers\SubscriptionController;
use App\Modules\Billing\Http\Controllers\WebCheckoutController;
use Illuminate\Support\Facades\Route;

// Loaded under /api/v1 by BillingServiceProvider.

// Public: authenticated by the shared Bearer secret configured in RevenueCat.
Route::post('webhooks/revenuecat', RevenueCatWebhookController::class)->middleware('throttle:webhooks')->name('api.v1.webhooks.revenuecat');

// Public: authenticated by the Paddle-Signature HMAC (PADDLE_WEBHOOK_SECRET).
Route::post('webhooks/paddle', PaddleWebhookController::class)->middleware('throttle:webhooks')->name('api.v1.webhooks.paddle');

// Public: authenticated by AdMob's ECDSA signature over the query string.
Route::get('webhooks/admob-ssv', AdMobSsvController::class)->middleware('throttle:webhooks')->name('api.v1.webhooks.admob-ssv');

// Public display prices for the website (B10).
Route::get('pricing', PricingController::class)->middleware('throttle:api')->name('api.v1.pricing');

Route::middleware(['auth:sanctum', 'throttle:api', 'family:admin'])->group(function () {
    Route::get('family/subscription', [SubscriptionController::class, 'show'])->name('api.v1.family.subscription');
    Route::post('family/subscription/sync', [SubscriptionController::class, 'sync'])->name('api.v1.family.subscription.sync');
    Route::post('family/subscription/manage-link', [WebCheckoutController::class, 'manageLink'])->name('api.v1.family.subscription.manage-link');
    Route::get('family/web-checkout/availability', [WebCheckoutController::class, 'availability'])->name('api.v1.family.web-checkout.availability');
    Route::post('family/referral-code', [WebCheckoutController::class, 'referralCode'])->name('api.v1.family.referral-code');
});
