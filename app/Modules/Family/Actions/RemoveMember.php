<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Events\MemberRemoved;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\SignInCode;
use Illuminate\Support\Facades\DB;

/**
 * Removes a member (soft: status `removed`, so their ledger history stays intact).
 * Their unused sign-in codes are voided; child accounts cannot sign in any other way, so
 * their device tokens are revoked. Other modules react to `MemberRemoved` (Goals drops the
 * member from goal plans and open periods).
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

            SignInCode::query()
                ->where('member_id', $member->id)
                ->whereNull('used_at')
                ->whereNull('voided_at')
                ->update(['voided_at' => now()]);

            $user = $member->user;

            if ($user->isChildAccount()) {
                $user->devices()->delete();
                $user->tokens()->delete();
            }

            MemberRemoved::dispatch($member->id, $member->family_id);
        });
    }
}
