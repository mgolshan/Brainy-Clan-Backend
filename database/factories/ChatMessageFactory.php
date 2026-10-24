<?php

namespace Database\Factories;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Family\Models\FamilyMember;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_member_id' => FamilyMember::factory(),
            'family_id' => fn (array $attributes) => FamilyMember::query()->whereKey($attributes['family_member_id'])->value('family_id'),
            'body' => fake()->sentence(),
            'client_id' => (string) Str::uuid(),
            'created_at' => now(),
        ];
    }

    /**
     * Written by this member (family taken from the membership).
     */
    public function by(FamilyMember $member): static
    {
        return $this->state(fn () => ['family_member_id' => $member->id, 'family_id' => $member->family_id]);
    }

    public function deleted(): static
    {
        return $this->state(fn () => ['body' => null, 'deleted_at' => now()]);
    }
}
