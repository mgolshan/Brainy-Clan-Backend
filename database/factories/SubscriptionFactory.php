<?php

namespace Database\Factories;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Family\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory()->familyTier(),
            'provider' => 'revenuecat',
            'app_user_id' => fn (array $attributes) => 'family-'.$attributes['family_id'],
            'entitlement' => 'family',
            'status' => Subscription::STATUS_ACTIVE,
            'store' => 'app_store',
            'product_id' => 'brainy_family_monthly',
            'period_type' => 'normal',
            'environment' => 'PRODUCTION',
            'will_renew' => true,
            'purchased_at' => now()->subDays(3),
            'expires_at' => now()->addDays(27),
            'last_event_ms' => now()->subDays(3)->getTimestampMs(),
            'last_event_type' => 'INITIAL_PURCHASE',
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => Subscription::STATUS_EXPIRED,
            'will_renew' => false,
            'expires_at' => now()->subDay(),
            'last_event_type' => 'EXPIRATION',
        ]);
    }

    /**
     * A web checkout (Paddle) subscription.
     */
    public function paddle(): static
    {
        return $this->state(fn () => [
            'provider' => Subscription::PROVIDER_PADDLE,
            'store' => Subscription::STORE_PADDLE,
            'external_id' => 'sub_'.fake()->unique()->bothify('01h##########'),
            'customer_id' => 'ctm_'.fake()->bothify('01h##########'),
            'product_id' => 'pri_annual',
            'environment' => 'SANDBOX',
            'last_event_type' => 'subscription.activated',
        ]);
    }

    public function grace(): static
    {
        return $this->state(fn () => [
            'status' => Subscription::STATUS_GRACE,
            'expires_at' => now()->subHour(),
            'grace_expires_at' => now()->addDays(6),
            'last_event_type' => 'BILLING_ISSUE',
        ]);
    }
}
