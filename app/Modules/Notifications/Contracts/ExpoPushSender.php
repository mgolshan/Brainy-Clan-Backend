<?php

namespace App\Modules\Notifications\Contracts;

use App\Modules\Notifications\Support\HttpExpoPushSender;
use App\Modules\Notifications\Support\PushSendResult;

/**
 * Sends push messages through the Expo push service. Bound to {@see HttpExpoPushSender};
 * tests swap in a fake via the container.
 */
interface ExpoPushSender
{
    /**
     * Send the messages. Never throws for transport problems: tokens Expo reported as `DeviceNotRegistered`
     * and tokens worth retrying later are returned in the result.
     *
     * @param  list<array{to: string, title: string, body: string, data: array<string, mixed>}>  $messages
     */
    public function send(array $messages): PushSendResult;
}
