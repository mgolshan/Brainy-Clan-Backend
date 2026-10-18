<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Actions\CheckPushReceipts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued by {@see DeliverPushNotification} {@see self::DELAY_MINUTES} minutes after a send (Expo has
 * receipts ready by then and keeps them for about a day): checks the tickets' receipts.
 */
class CheckPushReceiptsJob implements ShouldQueue
{
    use Queueable;

    public const int DELAY_MINUTES = 15;

    /**
     * @param  array<string, string>  $tickets  ticket id => push token
     */
    public function __construct(public readonly array $tickets) {}

    public function handle(CheckPushReceipts $check): void
    {
        if ($this->tickets !== []) {
            $check->handle($this->tickets);
        }
    }
}
