<?php

namespace App\Modules\Chat\Actions;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Models\ChatMessageReaction;
use App\Modules\Chat\Models\ChatMessageReport;
use App\Modules\Chat\Support\ChatReactions;
use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use Illuminate\Support\Facades\DB;

/**
 * `DELETE /family/chat/messages/{id}`: the author or a family admin deletes a message of their own
 * family (another family's id → 404, someone else's message as a non-admin → 403). The text is
 * erased (body null) and the row stays as a tombstone ("Message deleted"); its reports and reactions
 * are deleted (no longer `reported`, `reactions: []`, reaction marker bumped for pollers). Deleting
 * twice is a no-op.
 */
class DeleteChatMessage
{
    public function handle(FamilyMember $member, int $messageId): void
    {
        $message = ChatMessage::query()
            ->where('family_id', $member->family_id)
            ->whereKey($messageId)
            ->first();

        if ($message === null) {
            throw ApiException::notFound();
        }

        if ($message->family_member_id !== $member->id && ! $member->isAdmin()) {
            throw ApiException::forbidden('Only the author or a family admin can delete this message.');
        }

        if ($message->isDeleted()) {
            return;
        }

        DB::transaction(function () use ($message): void {
            $message->forceFill(['body' => null, 'deleted_at' => now()])->save();

            // The reports are handled: nothing is left to report on a tombstone.
            ChatMessageReport::query()->where('chat_message_id', $message->id)->delete();

            // A tombstone has no reactions; bump the marker so pollers see `reactions: []`.
            ChatMessageReaction::query()->where('chat_message_id', $message->id)->delete();
            ChatReactions::markChanged([$message->id]);
        });
    }
}
