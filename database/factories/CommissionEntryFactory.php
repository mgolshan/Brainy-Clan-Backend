<?php

namespace Database\Factories;

use App\Modules\Billing\Models\Affiliate;
use App\Modules\Billing\Models\CommissionEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionEntry>
 */
class CommissionEntryFactory extends Factory
{
    protected $model = CommissionEntry::class;

    /**
     * A US$49.99 annual sale through Paddle: 5% + 50¢ fee, no tax, 25% commission.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $occurredAt = now()->subDays(40);

        return [
            'affiliate_id' => Affiliate::factory(),
            'family_id' => null,
            'source' => CommissionEntry::SOURCE_PADDLE,
            'source_event_id' => 'paddle:'.fake()->unique()->bothify('evt_##########'),
            'source_reference' => fake()->bothify('txn_##########'),
            'kind' => CommissionEntry::KIND_SALE,
            'occurred_at' => $occurredAt,
            'currency' => 'USD',
            'gross_minor' => 4999,
            'tax_minor' => 0,
            'fee_minor' => 300,
            'net_minor' => 4699,
            'commission_rate' => '0.2500',
            'commission_minor' => 1175,
            'net_usd_minor' => 4699,
            'commission_usd_minor' => 1175,
            'status' => CommissionEntry::STATUS_PAYABLE,
            'payable_at' => $occurredAt->copy()->addDays(30),
        ];
    }
}
