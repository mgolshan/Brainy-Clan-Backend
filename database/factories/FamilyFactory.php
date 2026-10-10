<?php

namespace Database\Factories;

use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Family>
 */
class FamilyFactory extends Factory
{
    protected $model = Family::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_user_id' => User::factory(),
            'name' => 'The '.fake()->lastName().'s',
            'timezone' => 'America/Toronto',
            'country_code' => 'CA',
            'tier' => Family::TIER_FREE,
        ];
    }

    public function familyTier(): static
    {
        return $this->state(fn () => ['tier' => Family::TIER_FAMILY]);
    }

    /**
     * Also create the owner's admin membership.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Family $family): void {
            if (! $family->allMembers()->where('user_id', $family->owner_user_id)->exists()) {
                FamilyMember::query()->create([
                    'family_id' => $family->id,
                    'user_id' => $family->owner_user_id,
                    'role' => FamilyMember::ROLE_ADMIN,
                    'nickname' => $family->owner->display_name,
                    'status' => FamilyMember::STATUS_ACTIVE,
                    'joined_at' => now(),
                ]);
            }
        });
    }
}
