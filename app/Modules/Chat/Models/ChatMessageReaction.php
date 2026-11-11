<?php

namespace App\Modules\Chat\Models;

use App\Modules\Chat\Support\ChatReactions;
use App\Modules\Family\Models\FamilyMember;
use Database\Factories\ChatMessageReactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A member's reaction to a family chat message (docs/API.md "Family chat" → "Reactions"): one per
 * (message, member), `key` from the fixed set {@see ChatReactions::KEYS}. Replacing a reaction
 * writes a new row, so the id order is the reaction order (`member_ids`). Deleted with the message
 * (FK cascade), when the message is deleted (tombstone) and when the member leaves the family.
 *
 * @property int $id
 * @property int $chat_message_id
 * @property int $member_id
 * @property string $key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ChatMessage $message
 * @property-read FamilyMember $member
 */
#[Fillable(['chat_message_id', 'member_id', 'key'])]
#[UseFactory(ChatMessageReactionFactory::class)]
class ChatMessageReaction extends Model
{
    /** @use HasFactory<ChatMessageReactionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ChatMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }

    /**
     * @return BelongsTo<FamilyMember, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'member_id');
    }
}
