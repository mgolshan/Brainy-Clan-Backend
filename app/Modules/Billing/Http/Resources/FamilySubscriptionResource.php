<?php

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Family\Models\Family;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{family: Family, subscription: Subscription|null} $resource
 */
class FamilySubscriptionResource extends JsonResource
{
    /**
     * @return array{tier: string, status: string, store: string|null, product_id: string|null, expires_at: string|null, will_renew: bool, revenuecat_app_user_id: string}
     */
    public function toArray(Request $request): array
    {
        $family = $this->resource['family'];
        $subscription = $this->resource['subscription'];

        return [
            'tier' => $family->tier,
            'status' => $subscription !== null ? $subscription->status : Subscription::STATUS_NONE,
            'store' => $subscription?->apiStore(),
            'product_id' => $subscription?->product_id,
            'expires_at' => $subscription?->expires_at?->toIso8601String(),
            'will_renew' => $subscription !== null && $subscription->will_renew,
            'revenuecat_app_user_id' => 'family-'.$family->id,
        ];
    }
}
