<?php

namespace App\Modules\Chat\Actions;

use App\Modules\Chat\Models\ChatMessageReaction;
use App\Modules\Chat\Support\ChatReactions;

/**
 * A member left the family (removed, deleted their account, erased): their chat reactions go, and
 * the messages they had reacted to get their change marker bumped so pollers drop them. Two queries
 * for any number of members and reactions.
 */
class RemoveMemberChatReactions
{
    /**
     * @param  list<int>  $memberIds
     */
    public function handle(array $memberIds): void
    {
        if ($memberIds === []) {
            return;
        }

        ChatReactions::markChanged(ChatMessageReaction::query()->select('chat_message_id')->whereIn('member_id', $memberIds));
        ChatMessageReaction::query()->whereIn('member_id', $memberIds)->delete();
    }
}
