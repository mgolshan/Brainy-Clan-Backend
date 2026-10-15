<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Http\Resources\FamilySubscriptionResource;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Core\Http\CurrentMember;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group('Billing')]
class SubscriptionController
{
    use CurrentMember;

    /**
     * The family subscription (admin).
     *
     * `status` is `none` when the family never subscribed. The app passes `revenuecat_app_user_id`
     * to `Purchases.logIn()`.
     */
    public function show(Request $request): FamilySubscriptionResource
    {
        $family = $this->currentMember($request)->family;

        return new FamilySubscriptionResource([
            'family' => $family,
            'subscription' => Subscription::query()->where('family_id', $family->id)->first(),
        ]);
    }
}
