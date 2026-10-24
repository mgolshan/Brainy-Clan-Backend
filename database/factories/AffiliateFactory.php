<?php

namespace Database\Factories;

use App\Modules\Billing\Models\Affiliate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Affiliate>
 */
class AffiliateFactory extends Factory
{
    protected $model = Affiliate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'contact_email' => fake()->unique()->safeEmail(),
            'commission_rate' => '0.2500',
            'commission_months' => 12,
            'status' => Affiliate::STATUS_ACTIVE,
        ];
    }
}
