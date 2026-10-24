<?php

use App\Modules\Chat\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

// Loaded under /api/v1 by ChatServiceProvider. No `family` middleware on purpose: every caller who
// is not an active member of a family gets 404 from ResolveChatMember (docs/API.md "Family chat").

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('family/chat/messages', [ChatController::class, 'index'])->name('api.v1.family.chat.messages.index');
    Route::post('family/chat/messages', [ChatController::class, 'store'])->middleware('throttle:chat-post')->name('api.v1.family.chat.messages.store');
    Route::delete('family/chat/messages/{message}', [ChatController::class, 'destroy'])->whereNumber('message')->name('api.v1.family.chat.messages.destroy');
    Route::post('family/chat/read', [ChatController::class, 'read'])->name('api.v1.family.chat.read');
    Route::get('family/chat/unread', [ChatController::class, 'unread'])->name('api.v1.family.chat.unread');
});
