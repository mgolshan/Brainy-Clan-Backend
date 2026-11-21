<?php

namespace App\Modules\Chat\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\PolicyResolver;

/**
 * Who may use the family chat: only an active member of the family. Everyone else — a user
 * without a family, staff, a removed member — gets 404 (the chat does not exist for them; staff
 * never see message text). When the family's effective chat mode is `off` (Classroom), everyone,
 * admins included, gets 403 `chat_off`; on top of the mode, a member whose chat an admin turned
 * off gets 403 `chat_disabled`. Two queries (membership + family), with `family` loaded; the chat
 * mode comes from the cached policy settings.
 */
class ResolveChatMember
{
    public function __construct(private readonly PolicyResolver $policy) {}

    public function handle(User $user): FamilyMember
    {
        $member = $this->member($user);

        if ($this->policy->effectiveChatMode($member->family) === Family::CHAT_MODE_OFF) {
            throw ApiException::chatOff();
        }

        return $this->enabled($member);
    }

    /**
     * Like {@see self::handle()}, but null instead of 403 `chat_off` (`GET /family/chat/unread`
     * answers `{ unread: 0 }` while the chat is off).
     */
    public function unlessOff(User $user): ?FamilyMember
    {
        $member = $this->member($user);

        if ($this->policy->effectiveChatMode($member->family) === Family::CHAT_MODE_OFF) {
            return null;
        }

        return $this->enabled($member);
    }

    private function member(User $user): FamilyMember
    {
        $member = $user->activeMembership();

        if ($member === null) {
            throw ApiException::notFound();
        }

        return $member->loadMissing('family');
    }

    private function enabled(FamilyMember $member): FamilyMember
    {
        if (! $member->chat_enabled) {
            throw ApiException::chatDisabled();
        }

        return $member;
    }
}
