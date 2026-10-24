<?php

namespace Database\Factories;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Models\ChatMessageReport;
use App\Modules\Family\Models\FamilyMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessageReport>
 */
class ChatMessageReportFactory extends Factory
{
    protected $model = ChatMessageReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_message_id' => ChatMessage::factory(),
            'reporter_member_id' => FamilyMember::factory(),
            'created_at' => now(),
        ];
    }

    /**
     * This member reports this message.
     */
    public function of(ChatMessage $message, FamilyMember $reporter): static
    {
        return $this->state(fn () => ['chat_message_id' => $message->id, 'reporter_member_id' => $reporter->id]);
    }
}
