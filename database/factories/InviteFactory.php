<?php

namespace Database\Factories;

use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\Invite;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invite>
 */
class InviteFactory extends Factory
{
    protected $model = Invite::class;

    /**
     * A pending invite for a 9-year-old.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'code' => fake()->unique()->regexify('[A-HJ-NP-Z2-9]{8}'),
            'created_by' => User::factory(),
            'nickname' => fake()->firstName(),
            'avatar' => fake()->randomElement(User::AVATARS),
            'birth_year' => now()->year - 9,
            'birth_month' => 1,
            'expires_at' => now()->addDays(7),
        ];
    }

    public function adult(): static
    {
        return $this->state(fn () => ['birth_year' => now()->year - 40]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function used(): static
    {
        return $this->state(fn () => ['used_at' => now()->subHour()]);
    }
}
