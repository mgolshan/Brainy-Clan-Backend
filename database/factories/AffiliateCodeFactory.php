<?php

namespace Database\Factories;

use App\Modules\Billing\Models\Affiliate;
use App\Modules\Billing\Models\AffiliateCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliateCode>
 */
class AffiliateCodeFactory extends Factory
{
    protected $model = AffiliateCode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'affiliate_id' => Affiliate::factory(),
            'code' => strtoupper(fake()->unique()->bothify('CODE-####')),
            'store_offer_codes' => null,
            'paddle_discount_id' => null,
            'active' => true,
        ];
    }
}
