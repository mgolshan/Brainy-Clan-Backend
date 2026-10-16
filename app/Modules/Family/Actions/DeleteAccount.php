<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Events\MemberRemoved;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Actions\EraseUser;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * `DELETE /me`: an adult deletes their own account now. A plain member leaves the family
 * (status `removed`, `MemberRemoved` so Goals cleans up) and is erased; the owner of a family
 * without other members deletes the family too; the owner of a family with other members gets
 * 409 `family_owner_cannot_leave`; children/teens get 403 (an admin removes them).
 */
class DeleteAccount
{
    public function __construct(
        private readonly CheckAccountDeletion $check,
        private readonly DeleteFamily $deleteFamily,
        private readonly EraseUser $erase,
    ) {}

    public function handle(User $user): void
    {
        $check = $this->check->handle($user);

        if (in_array(CheckAccountDeletion::BLOCKER_FAMILY_OWNER, $check['blockers'], true)) {
            throw ApiException::familyOwnerCannotLeave();
        }

        if (in_array(CheckAccountDeletion::BLOCKER_CHILD_ACCOUNT, $check['blockers'], true)) {
            throw ApiException::forbidden('Ask a family admin to remove your account.');
        }

        $member = $user->activeMembership();

        if ($member !== null && $check['deletes_family']) {
            $this->deleteFamily->handle($member->family, deleteOwnerAccount: true);

            return;
        }

        DB::transaction(function () use ($user, $member): void {
            if ($member !== null) {
                $member->forceFill(['status' => FamilyMember::STATUS_REMOVED])->save();
                MemberRemoved::dispatch($member->id, $member->family_id);
            }

            $this->erase->handle($user, 'account_deleted');
        });
    }
}
