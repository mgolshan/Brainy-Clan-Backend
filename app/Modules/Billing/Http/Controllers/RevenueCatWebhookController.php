<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\ApplyRevenueCatEvent;
use App\Modules\Billing\Http\Requests\RevenueCatWebhookRequest;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Billing', 'Subscriptions: the RevenueCat webhook and the family subscription.', 7)]
class RevenueCatWebhookController
{
    /**
     * RevenueCat webhook.
     *
     * Public; authenticated by `Authorization: Bearer <REVENUECAT_WEBHOOK_SECRET>` (401 otherwise).
     * Idempotent by `event.id`: 200 also for duplicates, unknown families and ignored event types.
     *
     * @unauthenticated
     */
    public function __invoke(RevenueCatWebhookRequest $request, ApplyRevenueCatEvent $apply): JsonResponse
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
