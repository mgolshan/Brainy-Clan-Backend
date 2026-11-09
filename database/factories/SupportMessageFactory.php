<?php

namespace Database\Factories;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Support\Models\SupportMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupportMessage>
 */
class SupportMessageFactory extends Factory
{
    protected $model = SupportMessage::class;

    /**
     * A website message (name + email, no account).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => SupportMessage::SOURCE_WEB,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'locale' => 'en',
            'topic' => fake()->randomElement(SupportMessage::TOPICS),
            'message' => fake()->paragraph(),
            'status' => SupportMessage::STATUS_NEW,
            'last_activity_at' => now(),
        ];
    }

    /**
     * Sent in the app by this (admin) member.
     */
    public function fromMember(FamilyMember $member): static
    {
        return $this->state(fn () => [
            'source' => SupportMessage::SOURCE_APP,
            'user_id' => $member->user_id,
            'family_id' => $member->family_id,
            'name' => $member->user->display_name,
            'email' => $member->user->email,
            'locale' => $member->user->locale,
            'client_id' => (string) Str::uuid(),
            'app_version' => '1.0.0',
            'platform' => 'ios',
            'device' => 'iPhone 15 (iOS 18.1)',
        ]);
    }

    public function status(string $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'answered_at' => in_array($status, [SupportMessage::STATUS_ANSWERED, SupportMessage::STATUS_CLOSED], true) ? now() : null,
            'closed_at' => $status === SupportMessage::STATUS_CLOSED ? now() : null,
        ]);
    }
}
