<?php

namespace Database\Factories;

use App\Modules\Billing\Models\AdReward;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Pass `member_id` and `play_session_id` (an ad reward always belongs to a real session).
 *
 * @extends Factory<AdReward>
 */
class AdRewardFactory extends Factory
{
    protected $model = AdReward::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => (string) fake()->unique()->numerify('################'),
            'reward_ref' => (string) Str::uuid(),
            'ad_unit' => 'ca-app-pub-3940256099942544/5224354917',
            'reward_item' => 'second_chance',
            'reward_amount' => 1,
            'payload' => [],
        ];
    }

    public function used(): static
    {
        return $this->state(fn () => ['used_at' => now()->subMinute()]);
    }
}
