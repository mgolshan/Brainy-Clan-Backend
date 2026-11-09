<?php

namespace App\Modules\Support\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;

/**
 * Who may use `/support/messages` (docs/API.md "Support messages"): an active **family admin**
 * with an account email (replies go to that address). Everyone else — children, teens, adult
 * members who are not admins, people without a family, staff — gets 403 `forbidden`. A
 * social-only admin without an email also gets 403 (with a message saying why).
 */
class ResolveSupportSender
{
    public function handle(User $user): FamilyMember
    {
        $member = $user->activeMembership();

        if ($member === null || ! $member->isAdmin() || $member->status !== FamilyMember::STATUS_ACTIVE || $user->erased_at !== null) {
            throw ApiException::forbidden('Only a family admin can contact support from the app.');
        }

        if ($user->email === null || $user->email === '') {
            throw ApiException::forbidden('Add an email address to your account so support can answer you.');
        }

        return $member;
    }
}
