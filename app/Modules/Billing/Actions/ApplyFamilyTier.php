<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Events\SubscriptionChanged;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Family\Models\Family;

/**
 * The one place a subscription switches the family tier (docs/PLAN.md "Tier switches only via the
 * server"): `family` while the entitlement is on (active or in the billing grace period), else
 * `free`. Used by the RevenueCat webhook, `POST /family/subscription/sync` and the scheduled
 * reconciliation. Saves the subscription and dispatches {@see SubscriptionChanged}.
 */
class ApplyFamilyTier
{
    public const string SOURCE_WEBHOOK = 'webhook';

    public const string SOURCE_SYNC = 'sync';

    public const string SOURCE_RECONCILE = 'reconcile';

    public function handle(Family $family, Subscription $subscription, string $source): void
    {
        $subscription->save();

        $tier = $subscription->isEntitled() ? Family::TIER_FAMILY : Family::TIER_FREE;
        $changed = $family->tier !== $tier;

        if ($changed) {
            $family->update(['tier' => $tier]);
        }

        SubscriptionChanged::dispatch($family->id, $subscription->status, $tier, $changed, $source);
    }
}
