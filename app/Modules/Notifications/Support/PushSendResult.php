<?php

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Contracts\ExpoPushSender;

/**
 * Outcome of one {@see ExpoPushSender::send()} call.
 */
final readonly class PushSendResult
{
    /**
     * @param  list<string>  $invalidTokens  tokens Expo reported as `DeviceNotRegistered` (the caller clears them)
     * @param  list<string>  $retryTokens  tokens whose message was not delivered for a temporary reason
     *                                     (connection error, HTTP 429/5xx, ticket `MessageRateExceeded`)
     */
    public function __construct(
        public array $invalidTokens = [],
        public array $retryTokens = [],
    ) {}
}
