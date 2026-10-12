<?php

namespace Database\Factories;

use App\Modules\Identity\Models\SocialAccount;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    protected $model = SocialAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => SocialAccount::GOOGLE,
            'provider_user_id' => (string) fake()->unique()->numerify('1###############'),
            'email' => fake()->unique()->safeEmail(),
            'linked_at' => now(),
        ];
    }

    public function facebook(): static
    {
        return $this->state(fn () => ['provider' => SocialAccount::FACEBOOK]);
    }
}
