<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Events\SubscriptionChanged;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Family\Models\Family;

/**
 * The one place a subscription switches the family tier (docs/PLAN.md "Tier switches only via the
 * server"): the highest-ranked tier (`family` < `big_clan` < `classroom`) of the family's entitled
 * subscription rows (RevenueCat or Paddle; active or in the billing grace period), else `free`.
 * Used by the RevenueCat and Paddle webhooks, `POST /family/subscription/sync` and the scheduled
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

        $entitledTiers = Subscription::query()
            ->where('family_id', $family->id)
            ->where('provider', '!=', $subscription->provider)
            ->get()
            ->filter(fn (Subscription $row): bool => $row->isEntitled())
            ->map(fn (Subscription $row): string => $row->tier());

        if ($subscription->isEntitled()) {
            $entitledTiers->push($subscription->tier());
        }

        $tier = Family::highestTier($entitledTiers);
        $changed = $family->tier !== $tier;

        if ($changed) {
            $family->update(['tier' => $tier]);
        }

        SubscriptionChanged::dispatch($family->id, $subscription->status, $tier, $changed, $source);
    }
}
