<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use Illuminate\Support\Facades\DB;

/**
 * Removes a member (soft: status `removed`, so their ledger history stays intact).
 * Child accounts cannot sign in any other way, so their device tokens are revoked.
 */
class RemoveMember
{
    public function handle(FamilyMember $member): void
    {
        if ($member->isOwner()) {
            throw ApiException::forbidden('The family owner cannot be removed.');
        }

        DB::transaction(function () use ($member): void {
            $member->forceFill(['status' => FamilyMember::STATUS_REMOVED])->save();

            $user = $member->user;

            if ($user->isChildAccount()) {
                $user->devices()->delete();
                $user->tokens()->delete();
            }
        });
    }
}
