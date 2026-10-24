<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\ApplyPaddleEvent;
use App\Modules\Billing\Http\Requests\PaddleWebhookRequest;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Billing')]
class PaddleWebhookController
{
    /**
     * Paddle webhook (web checkout).
     *
     * Public; authenticated by the `Paddle-Signature` header (HMAC-SHA256 of `ts:rawBody` with
     * `PADDLE_WEBHOOK_SECRET`, 5-minute timestamp tolerance; 401 otherwise). Idempotent by
     * `event_id`: 200 also for duplicates, unknown families and ignored event types.
     * `outcome`: `applied` | `ignored` | `stale` | `duplicate`.
     *
     * @unauthenticated
     */
    public function __invoke(PaddleWebhookRequest $request, ApplyPaddleEvent $apply): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->all();
        $event = $apply->handle($payload);

        return response()->json(['data' => [
            'event_id' => $event->event_id,
            'outcome' => $event->wasRecentlyCreated ? $event->outcome : 'duplicate',
        ]]);
    }
}
