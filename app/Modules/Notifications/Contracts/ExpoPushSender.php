<?php

namespace App\Modules\Notifications\Contracts;

use App\Modules\Notifications\Support\HttpExpoPushSender;

/**
 * Sends push messages through the Expo push service. Bound to {@see HttpExpoPushSender};
 * tests swap in a fake via the container.
 */
interface ExpoPushSender
{
    /**
     * Send the messages and return the tokens Expo reported as `DeviceNotRegistered` (the caller clears them).
     *
     * @param  list<array{to: string, title: string, body: string, data: array<string, mixed>}>  $messages
     * @return list<string>
     */
    public function send(array $messages): array;
}
