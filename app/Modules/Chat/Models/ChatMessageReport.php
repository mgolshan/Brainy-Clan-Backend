<?php

namespace App\Modules\Chat\Models;

use App\Modules\Family\Models\FamilyMember;
use Database\Factories\ChatMessageReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A member reported a family chat message to the family's admins (docs/API.md "Family chat",
 * Apple guideline 1.2). Ids only — never the text. One per (message, reporter). Visible only to
 * that family's admins as Message `reported`; never shown to staff (no Filament resource, no
 * activity log). Deleted with the message (FK cascade) and with the reporter's account erasure.
 *
 * @property int $id
 * @property int $chat_message_id
 * @property int $reporter_member_id
 * @property Carbon|null $created_at
 * @property-read ChatMessage $message
 * @property-read FamilyMember $reporter
 */
#[Fillable(['chat_message_id', 'reporter_member_id'])]
#[UseFactory(ChatMessageReportFactory::class)]
class ChatMessageReport extends Model
{
    /** @use HasFactory<ChatMessageReportFactory> */
    use HasFactory;

    public const ?string UPDATED_AT = null;

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
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'reporter_member_id');
    }
}
