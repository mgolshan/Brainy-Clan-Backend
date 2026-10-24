<?php

namespace App\Modules\Chat\Actions;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Family\Models\FamilyMember;

/**
 * `POST /family/chat/read`: moves the member's read marker forward to `last_read_id` (never
 * back), capped at the family's newest message id so a made-up id can not hide future messages.
 * Two queries: the newest id and one conditional update.
 */
class MarkChatRead
{
    public function handle(FamilyMember $member, int $lastReadId): void
    {
        $newest = (int) ChatMessage::query()->where('family_id', $member->family_id)->max('id');
        $lastReadId = min($lastReadId, $newest);

        if ($lastReadId <= 0 || $lastReadId <= ($member->chat_last_read_id ?? 0)) {
            return;
        }

        FamilyMember::query()
            ->whereKey($member->id)
            ->where(fn ($query) => $query->whereNull('chat_last_read_id')->orWhere('chat_last_read_id', '<', $lastReadId))
            ->toBase()
            ->update(['chat_last_read_id' => $lastReadId]);

        $member->chat_last_read_id = $lastReadId;
        $member->syncOriginalAttribute('chat_last_read_id');
    }
}
