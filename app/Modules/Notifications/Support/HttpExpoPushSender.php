<?php

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Contracts\ExpoPushSender;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Expo push over HTTP: POST {services.expo.push_url} in batches of at most 100 messages. Tickets are
 * returned in message order; a ticket error `DeviceNotRegistered` marks that token as invalid, and `ok`
 * ticket ids are returned for the later receipts check (CheckPushReceiptsJob).
 *
 * Temporary failures (connection errors, HTTP 429 / 5xx after the short in-request retries, ticket error
 * `MessageRateExceeded`) put the batch's tokens into {@see PushSendResult::$retryTokens} so the job can
 * try those again later; other failures (bad request, bad credentials) are logged and dropped. Logs never
 * contain push tokens or the response body (it can echo tokens).
 */
class HttpExpoPushSender implements ExpoPushSender
{
    public const int BATCH_SIZE = 100;

    public function send(array $messages): PushSendResult
    {
        $invalid = [];
        $retry = [];
        $tickets = [];

        foreach (array_chunk($messages, self::BATCH_SIZE) as $batch) {
            $batchTokens = array_column($batch, 'to');
            $request = Http::acceptJson()->asJson()->timeout(15)->retry(2, 500, throw: false);
            $token = config('services.expo.access_token');

            if (is_string($token) && $token !== '') {
                $request = $request->withToken($token);
            }

            try {
                $response = $request->post((string) config('services.expo.push_url'), array_map(
                    fn (array $message) => [...$message, 'sound' => 'default'],
                    $batch,
                ));
            } catch (ConnectionException $exception) {
                Log::warning('Expo push service unreachable', ['messages' => count($batch), 'error' => $exception->getMessage()]);
                array_push($retry, ...$batchTokens);

                continue;
            }

            if ($response->failed()) {
                $retryable = $response->status() === 429 || $response->serverError();
                Log::warning('Expo push request failed', [
                    'status' => $response->status(),
                    'code' => $response->json('errors.0.code'),
                    'messages' => count($batch),
                    'will_retry' => $retryable,
                ]);

                if ($retryable) {
                    array_push($retry, ...$batchTokens);
                }

                continue;
            }

            foreach ((array) $response->json('data', []) as $index => $ticket) {
                if (! is_array($ticket) || ! isset($batch[$index])) {
                    continue;
                }

                if (($ticket['status'] ?? null) === 'ok' && is_string($ticket['id'] ?? null) && $ticket['id'] !== '') {
                    $tickets[$ticket['id']] = $batch[$index]['to'];

                    continue;
                }

                if (($ticket['status'] ?? null) !== 'error') {
                    continue;
                }

                $error = $ticket['details']['error'] ?? null;

                if ($error === 'DeviceNotRegistered') {
                    $invalid[] = $batch[$index]['to'];
                } elseif ($error === 'MessageRateExceeded') {
                    $retry[] = $batch[$index]['to'];
                }
            }
        }

        return new PushSendResult(array_values(array_unique($invalid)), array_values(array_unique($retry)), $tickets);
    }
}
