<?php

namespace App\Modules\Chat\Actions;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;

/**
 * `DELETE /family/chat/messages/{id}`: the author or a family admin deletes a message of their own
 * family (another family's id → 404, someone else's message as a non-admin → 403). The text is
 * erased (body null) and the row stays as a tombstone ("Message deleted"). Deleting twice is a no-op.
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

        $message->forceFill(['body' => null, 'deleted_at' => now()])->save();
    }
}
