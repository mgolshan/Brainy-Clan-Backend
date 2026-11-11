<?php

namespace App\Modules\Chat\Actions;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Models\ChatMessageReaction;
use App\Modules\Chat\Support\ChatReactions;
use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * `PUT` / `DELETE /family/chat/messages/{id}/reaction` (docs/API.md "Family chat" → "Reactions"):
 * a member sets their single reaction on a message of their family (replacing an earlier one) or
 * removes it. Another family's id or a message past retention → 404; reacting to a deleted message
 * → 422 (removing is a no-op there: a tombstone has no reactions). Reacting to your own message is
 * fine. Every real change bumps the message's change marker for `GET /family/chat/reactions`;
 * setting the same key again or removing a missing reaction changes nothing. No push, unread is
 * unaffected. Both return the message, loaded like every other Message ({@see ChatMessage::viewedBy()}).
 */
class ReactToChatMessage
{
    public function __construct(private readonly CountUnreadChatMessages $unread) {}

    public function set(FamilyMember $member, int $messageId, string $key): ChatMessage
    {
        if (! in_array($key, ChatReactions::KEYS, true)) {
            throw ApiException::validation('key', 'Unknown reaction.');
        }

        $message = $this->find($member, $messageId);

        if ($message->isDeleted()) {
            throw ApiException::validation('message', 'You can not react to a deleted message.');
        }

        DB::transaction(function () use ($member, $message, $key): void {
            $existing = ChatMessageReaction::query()
                ->where('chat_message_id', $message->id)
                ->where('member_id', $member->id)
                ->lockForUpdate()
                ->first();

            if ($existing?->key === $key) {
                return;
            }

            // A new row on replace: the id order is the reaction order (`member_ids`).
            $existing?->delete();

            try {
                ChatMessageReaction::query()->create(['chat_message_id' => $message->id, 'member_id' => $member->id, 'key' => $key]);
            } catch (UniqueConstraintViolationException) {
                // A racing request of the same member inserted first: the last write wins.
                ChatMessageReaction::query()
                    ->where('chat_message_id', $message->id)
                    ->where('member_id', $member->id)
                    ->update(['key' => $key, 'updated_at' => now()]);
            }

            ChatReactions::markChanged([$message->id]);
        });

        return $this->reload($member, $message->id);
    }

    public function remove(FamilyMember $member, int $messageId): ChatMessage
    {
        $message = $this->find($member, $messageId);

        $removed = ChatMessageReaction::query()
            ->where('chat_message_id', $message->id)
            ->where('member_id', $member->id)
            ->delete();

        if ($removed > 0) {
            ChatReactions::markChanged([$message->id]);
        }

        return $this->reload($member, $message->id);
    }

    private function find(FamilyMember $member, int $messageId): ChatMessage
    {
        $message = ChatMessage::query()
            ->where('family_id', $member->family_id)
            ->whereKey($messageId)
            ->where('created_at', '>=', $this->unread->retentionCutoff($member))
            ->first(['id', 'family_id', 'deleted_at']);

        if ($message === null) {
            throw ApiException::notFound();
        }

        return $message;
    }

    private function reload(FamilyMember $member, int $messageId): ChatMessage
    {
        /** @var ChatMessage $message */
        $message = ChatMessage::viewedBy($member)->whereKey($messageId)->firstOrFail();

        return $message;
    }
}
