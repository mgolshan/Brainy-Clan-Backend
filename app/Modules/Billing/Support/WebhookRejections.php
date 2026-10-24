<?php

namespace App\Modules\Billing\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Counts rejected provider callbacks per provider, reason and UTC day (`webhook_rejections`), for
 * the admin dashboard. One upsert per rejection, no payload stored. Counting never breaks the
 * callback: a failed write is only reported.
 */
class WebhookRejections
{
    public const string PROVIDER_ADMOB = 'admob';

    /** Paddle webhook (`POST /webhooks/paddle`); only `signature_invalid` (answered 401). */
    public const string PROVIDER_PADDLE = 'paddle';

    /** Missing or invalid signature: AdMob SSV ECDSA (answered 400 `ssv_signature_invalid`) or `Paddle-Signature` (401). */
    public const string REASON_SIGNATURE_INVALID = 'signature_invalid';

    /** AdMob SSV: signed, but custom data, member or play session unusable (answered 200 `ignored`). */
    public const string REASON_INVALID_PAYLOAD = 'invalid_payload';

    public function record(string $provider, string $reason): void
    {
        $now = now();

        try {
            DB::table('webhook_rejections')->upsert(
                [['provider' => $provider, 'reason' => $reason, 'day' => $now->utc()->toDateString(), 'hits' => 1, 'created_at' => $now, 'updated_at' => $now]],
                ['provider', 'reason', 'day'],
                ['hits' => DB::raw('hits + 1'), 'updated_at' => $now],
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
