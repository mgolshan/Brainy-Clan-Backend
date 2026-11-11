<?php

namespace Database\Factories;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Models\ChatMessageReaction;
use App\Modules\Family\Models\FamilyMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Only the row: it does not bump the message's `reactions_changed_at` (the actions do).
 *
 * @extends Factory<ChatMessageReaction>
 */
class ChatMessageReactionFactory extends Factory
{
    protected $model = ChatMessageReaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_message_id' => ChatMessage::factory(),
            'member_id' => FamilyMember::factory(),
            'key' => 'heart',
        ];
    }

    /**
     * This member reacts to this message with this key.
     */
    public function of(ChatMessage $message, FamilyMember $member, string $key = 'heart'): static
    {
        return $this->state(fn () => ['chat_message_id' => $message->id, 'member_id' => $member->id, 'key' => $key]);
    }
}
