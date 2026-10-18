<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Identity\Models\Device;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Expo push receipts: POST {services.expo.receipts_url} with at most {@see self::BATCH_SIZE} ticket ids per
 * request. A receipt error `DeviceNotRegistered` clears that push token from every device (the app was
 * uninstalled or the token expired); other receipt errors are logged as counts per error code. Receipts
 * are best effort: a failed request is logged and skipped. Logs never contain tokens, ticket ids or bodies.
 */
class CheckPushReceipts
{
    public const int BATCH_SIZE = 1000;

    /**
     * @param  array<string, string>  $tickets  ticket id => push token it was sent to
     * @return array{checked: int, cleared: int}
     */
    public function handle(array $tickets): array
    {
        $checked = 0;
        $dead = [];
        $errors = [];

        foreach (array_chunk(array_keys($tickets), self::BATCH_SIZE) as $ids) {
            $request = Http::acceptJson()->asJson()->timeout(15)->retry(2, 500, throw: false);
            $accessToken = config('services.expo.access_token');

            if (is_string($accessToken) && $accessToken !== '') {
                $request = $request->withToken($accessToken);
            }

            try {
                $response = $request->post((string) config('services.expo.receipts_url'), ['ids' => array_map('strval', $ids)]);
            } catch (ConnectionException $exception) {
                Log::warning('Expo receipts service unreachable', ['tickets' => count($ids), 'error' => $exception->getMessage()]);

                continue;
            }

            if ($response->failed()) {
                Log::warning('Expo receipts request failed', ['status' => $response->status(), 'code' => $response->json('errors.0.code'), 'tickets' => count($ids)]);

                continue;
            }

            foreach ((array) $response->json('data', []) as $id => $receipt) {
                if (! is_array($receipt) || ! isset($tickets[$id])) {
                    continue;
                }

                $checked++;

                if (($receipt['status'] ?? null) !== 'error') {
                    continue;
                }

                $error = (string) ($receipt['details']['error'] ?? 'unknown');

                if ($error === 'DeviceNotRegistered') {
                    $dead[] = $tickets[$id];
                } else {
                    $errors[$error] = ($errors[$error] ?? 0) + 1;
                }
            }
        }

        $cleared = $dead === [] ? 0 : Device::query()->whereIn('push_token', array_values(array_unique($dead)))->update(['push_token' => null]);

        if ($errors !== []) {
            Log::warning('Expo push receipts reported errors', ['errors' => $errors]);
        }

        return ['checked' => $checked, 'cleared' => $cleared];
    }
}
