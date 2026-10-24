<?php

use App\Modules\Billing\Http\Controllers\SubscribePageController;
use App\Modules\Core\Http\Controllers\WebsiteController;
use App\Modules\Core\Http\Middleware\RedirectToWebsiteHost;
use App\Modules\Core\Http\Middleware\SecurityHeaders;
use App\Modules\Family\Http\Controllers\AccountDeletionPageController;
use App\Modules\Identity\Http\Controllers\LegalPageController;
use App\Modules\Identity\Http\Controllers\PasswordResetPageController;
use Illuminate\Support\Facades\Route;

/*
 * The public website (docs/DEPLOY.md "Website and hosts"). In production (WEBSITE_HOST set) it
 * lives on https://brainyclan.com: RedirectToWebsiteHost sends these paths requested on the API
 * host there. In local dev (WEBSITE_HOST empty) every page answers on any host, e.g.
 * http://localhost:8080/support. The API (/api/v1), /admin, /horizon and /docs/api stay on the
 * API host (RouteByHost, global middleware).
 */

// robots.txt answers on every host (the API host disallows everything) and never redirects.
Route::withoutMiddleware(['web'])->get('robots.txt', [WebsiteController::class, 'robots'])->name('website.robots');

Route::middleware(RedirectToWebsiteHost::class)->group(function () {
    // Landing page, support, terms and privacy: stateless (no session cookie), no scripts.
    Route::withoutMiddleware(['web'])->middleware(SecurityHeaders::class)->group(function () {
        Route::get('/', [WebsiteController::class, 'home'])->name('website.home');
        Route::get('support', [WebsiteController::class, 'support'])->name('website.support');
        Route::get('sitemap.xml', [WebsiteController::class, 'sitemap'])->name('website.sitemap');

        // Terms of service and privacy policy (docs/API.md "Legal, consent and privacy (B9)"); ?lang=fr.
        Route::get('terms', [LegalPageController::class, 'terms'])->name('legal.terms');
        Route::get('privacy', [LegalPageController::class, 'privacy'])->name('legal.privacy');
        // Old URLs (before 2026-10-22) → 301 to the ones above, query string kept.
        Route::get('legal/{document}', [WebsiteController::class, 'legacyLegal'])->whereIn('document', ['terms', 'privacy'])->name('legal.legacy');

        // Affiliate link (B10): the landing page with a code banner; unknown codes show the normal page.
        Route::get('r/{code}', [WebsiteController::class, 'referral'])->where('code', '[A-Za-z0-9-]{1,40}')->name('website.referral');

        // Web checkout (B10): the emailed link (→ Paddle) and Paddle's return page.
        Route::get('subscribe/checkout', [SubscribePageController::class, 'checkout'])->middleware('throttle:web-checkout')->name('website.subscribe.checkout');
        Route::get('subscribe/thanks', [SubscribePageController::class, 'thanks'])->name('website.subscribe.thanks');
    });

    // Paddle's default payment link page: the only page with a script (Paddle.js); own CSP in the controller.
    Route::withoutMiddleware(['web'])->get('subscribe/pay', [SubscribePageController::class, 'pay'])->name('website.subscribe.pay');

    // Web checkout subscribe form (docs/API.md "Web checkout (Paddle)"); ?lang=fr. Session for CSRF.
    Route::middleware(SecurityHeaders::class.':forms')->group(function () {
        Route::get('subscribe', [SubscribePageController::class, 'show'])->name('website.subscribe');
        Route::post('web/subscribe', [SubscribePageController::class, 'store'])->middleware('throttle:web-subscribe')->name('website.subscribe.request');
    });

    // Account deletion web page (Google Play requirement); ?lang=fr. Uses the session for CSRF.
    Route::middleware(SecurityHeaders::class.':forms')->prefix('account/delete')->group(function () {
        Route::get('/', [AccountDeletionPageController::class, 'show'])->name('account.delete');
        Route::post('/', [AccountDeletionPageController::class, 'store'])->middleware('throttle:account-deletion')->name('account.delete.request');
        Route::get('confirm/{token}', [AccountDeletionPageController::class, 'confirmForm'])->where('token', '[A-Za-z0-9]{64}')->name('account.delete.confirm');
        Route::post('confirm/{token}', [AccountDeletionPageController::class, 'confirm'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:account-deletion')->name('account.delete.confirm.submit');
    });

    // Password reset web page (docs/API.md Auth, POST /auth/password/forgot); ?token=…&lang=fr. Session for CSRF.
    Route::middleware(SecurityHeaders::class.':forms')->prefix('password/reset')->group(function () {
        Route::get('/', [PasswordResetPageController::class, 'show'])->name('password.reset');
        Route::post('/', [PasswordResetPageController::class, 'store'])->middleware('throttle:password-reset')->name('password.reset.submit');
    });
});
