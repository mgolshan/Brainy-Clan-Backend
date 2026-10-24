<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Contracts\PaddleClient;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\PaddleSubscriptionState;
use App\Modules\Family\Models\Family;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Re-reads a family's Paddle subscription from the Paddle API (`GET /subscriptions/{id}`) and
 * applies it like a webhook (scheduled reconciliation, for a missed webhook). The API reflects the
 * current state, so it counts as the newest event. Nothing changes when Paddle can't be reached.
 */
class SyncPaddleSubscription
{
    public const string EVENT_TYPE = 'paddle.sync';

    public function __construct(
        private readonly PaddleClient $paddle,
        private readonly ApplyFamilyTier $applyTier,
    ) {}

    /**
     * @return bool whether the Paddle state was fetched and applied
     */
    public function handle(Subscription $subscription, string $source = ApplyFamilyTier::SOURCE_RECONCILE): bool
    {
        if (! $this->paddle->isConfigured() || $subscription->external_id === null) {
            return false;
        }

        try {
            $data = $this->paddle->subscription($subscription->external_id);
        } catch (RuntimeException $exception) {
            Log::warning('Paddle subscription sync failed', ['family_id' => $subscription->family_id, 'error' => $exception->getMessage()]);

            return false;
        }

        $fetchedMs = now()->getTimestampMs();

        DB::transaction(function () use ($subscription, $data, $fetchedMs, $source): void {
            /** @var Family $family */
            $family = Family::query()->lockForUpdate()->findOrFail($subscription->family_id);
            /** @var Subscription $locked */
            $locked = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            PaddleSubscriptionState::fill($locked, $data, $family->id, $fetchedMs, self::EVENT_TYPE);
            $this->applyTier->handle($family, $locked, $source);
        });

        return true;
    }
}
