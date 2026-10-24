<?php

namespace App\Modules\Chat\Listeners;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Family\Events\FamilyDeleted;

/**
 * A deleted family keeps no chat: every message is deleted for good, inside the deletion
 * transaction (synchronous listener), in one query.
 */
class DeleteFamilyChat
{
    public function handle(FamilyDeleted $event): void
    {
        ChatMessage::query()->where('family_id', $event->familyId)->delete();
    }
}
