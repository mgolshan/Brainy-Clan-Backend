<?php

namespace Database\Factories;

use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FamilyMember>
 */
class FamilyMemberFactory extends Factory
{
    protected $model = FamilyMember::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'user_id' => User::factory(),
            'role' => FamilyMember::ROLE_MEMBER,
            'nickname' => fake()->firstName(),
            'difficulty_override' => null,
            'accessibility' => null,
            'status' => FamilyMember::STATUS_ACTIVE,
            'joined_at' => now(),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => FamilyMember::ROLE_ADMIN]);
    }

    public function child(int $age = 9): static
    {
        return $this->state(fn () => ['user_id' => User::factory()->child($age)]);
    }
}
