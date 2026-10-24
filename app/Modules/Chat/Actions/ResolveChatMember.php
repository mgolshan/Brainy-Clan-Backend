<?php

namespace App\Modules\Chat\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;

/**
 * Who may use the family chat: only an active member of the family. Everyone else — a user
 * without a family, staff, a removed member — gets 404 (the chat does not exist for them; staff
 * never see message text). A member whose chat an admin turned off gets 403 `chat_disabled`.
 * Two queries (membership + family), with `family` loaded.
 */
class ResolveChatMember
{
    public function handle(User $user): FamilyMember
    {
        $member = $user->activeMembership();

        if ($member === null) {
            throw ApiException::notFound();
        }

        if (! $member->chat_enabled) {
            throw ApiException::chatDisabled();
        }

        return $member->loadMissing('family');
    }
}
