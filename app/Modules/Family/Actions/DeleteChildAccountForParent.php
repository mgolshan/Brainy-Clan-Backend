<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;

/**
 * Staff action "Delete child account" (admin only): on a verified parent request (sent from the
 * family owner's own email address) removes a child account from the family — the same
 * `RemoveMember` flow the family admin uses in the app, which erases the account (`EraseUser`).
 * Staff must type the child's nickname to confirm. The activity log gets ids only.
 */
class DeleteChildAccountForParent
{
    public function __construct(private readonly RemoveMember $removeMember) {}

    public function handle(FamilyMember $member, User $staff, string $typedNickname): void
    {
        $member->loadMissing(['family', 'user']);

        if ($member->status !== FamilyMember::STATUS_ACTIVE || $member->isOwner() || ! $member->user->isChildAccount()) {
            throw ApiException::forbidden('Only active child accounts (no own login) can be deleted for a parent.');
        }

        if (trim($typedNickname) !== $member->nickname) {
            throw ApiException::validation('confirm_nickname', 'Type the nickname exactly to confirm.');
        }

        $memberId = $member->id;
        $userId = $member->user_id;

        $this->removeMember->handle($member);

        activity('admin')
            ->causedBy($staff)
            ->performedOn($member->family)
            ->event('child_account_deleted')
            ->withProperties(['member_id' => $memberId, 'user_id' => $userId, 'reason' => 'parent_request'])
            ->log('Child account deleted on a parent request');
    }
}
