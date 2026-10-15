<?php

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Contracts\ExpoPushSender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Expo push over HTTP: POST {services.expo.push_url} in batches of at most 100 messages. Tickets are
 * returned in message order; a ticket error `DeviceNotRegistered` marks that token as invalid.
 */
class HttpExpoPushSender implements ExpoPushSender
{
    public const int BATCH_SIZE = 100;

    public function send(array $messages): array
    {
        $invalid = [];

        foreach (array_chunk($messages, self::BATCH_SIZE) as $batch) {
            $request = Http::acceptJson()->asJson()->timeout(15)->retry(2, 500, throw: false);
            $token = config('services.expo.access_token');

            if (is_string($token) && $token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->post((string) config('services.expo.push_url'), array_map(
                fn (array $message) => [...$message, 'sound' => 'default'],
                $batch,
            ));

            if ($response->failed()) {
                Log::warning('Expo push request failed', ['status' => $response->status(), 'body' => $response->body()]);

                continue;
            }

            foreach ((array) $response->json('data', []) as $index => $ticket) {
                if (is_array($ticket) && ($ticket['status'] ?? null) === 'error'
                    && ($ticket['details']['error'] ?? null) === 'DeviceNotRegistered'
                    && isset($batch[$index])) {
                    $invalid[] = $batch[$index]['to'];
                }
            }
        }

        return array_values(array_unique($invalid));
    }
}
