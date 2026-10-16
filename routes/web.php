<?php

use App\Modules\Core\Http\Middleware\SecurityHeaders;
use App\Modules\Family\Http\Controllers\AccountDeletionPageController;
use App\Modules\Identity\Http\Controllers\LegalPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Terms of service and privacy policy (docs/API.md "Legal, consent and privacy (B9)"); ?lang=fr.
// Stateless: no session cookie for anonymous readers.
Route::withoutMiddleware(['web'])->middleware(SecurityHeaders::class)->group(function () {
    Route::get('legal/terms', [LegalPageController::class, 'terms'])->name('legal.terms');
    Route::get('legal/privacy', [LegalPageController::class, 'privacy'])->name('legal.privacy');
});

// Account deletion web page (Google Play requirement); ?lang=fr. Uses the session for CSRF.
Route::middleware(SecurityHeaders::class.':forms')->prefix('account/delete')->group(function () {
    Route::get('/', [AccountDeletionPageController::class, 'show'])->name('account.delete');
    Route::post('/', [AccountDeletionPageController::class, 'store'])->middleware('throttle:account-deletion')->name('account.delete.request');
    Route::get('confirm/{token}', [AccountDeletionPageController::class, 'confirmForm'])->where('token', '[A-Za-z0-9]{64}')->name('account.delete.confirm');
    Route::post('confirm/{token}', [AccountDeletionPageController::class, 'confirm'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:account-deletion')->name('account.delete.confirm.submit');
});
