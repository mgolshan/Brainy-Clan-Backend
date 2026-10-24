<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\SyncSubscription;
use App\Modules\Billing\Http\Resources\FamilySubscriptionResource;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Core\Http\CurrentMember;
use App\Modules\Family\Models\Family;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group('Billing')]
class SubscriptionController
{
    use CurrentMember;

    /**
     * The family subscription (admin).
     *
     * `status` is `none` when the family never subscribed. `store` is `paddle` for a plan bought on
     * the web (the app then hides the paywall and offers "Manage" via `manage-link`). The app passes
     * `revenuecat_app_user_id` to `Purchases.logIn()`.
     */
    public function show(Request $request): FamilySubscriptionResource
    {
        return $this->resource($this->currentMember($request)->family);
    }

    /**
     * Sync the subscription from RevenueCat now (admin).
     *
     * For a faster tier switch after a purchase or restore: fetches the subscriber `family-{id}`
     * from the RevenueCat REST API (when `REVENUECAT_API_KEY` is configured) and applies the
     * `family` entitlement. Without a key, or when RevenueCat can't be reached, it returns the
     * current state. Same response as `GET /family/subscription`.
     */
    public function sync(Request $request, SyncSubscription $sync): FamilySubscriptionResource
    {
        $family = $this->currentMember($request)->family;
        $sync->handle($family);

        return $this->resource($family->refresh());
    }

    private function resource(Family $family): FamilySubscriptionResource
    {
        return new FamilySubscriptionResource([
            'family' => $family,
            'subscription' => Subscription::currentFor($family->id),
        ]);
    }
}
