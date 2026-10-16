<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Events\MemberRemoved;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\SignInCode;
use App\Modules\Identity\Actions\EraseUser;
use Illuminate\Support\Facades\DB;

/**
 * Removes a member (soft: status `removed`, so their ledger history stays intact).
 * Their unused sign-in codes are voided; child accounts cannot sign in any other way, so
 * their account is erased (`EraseUser`: tokens, devices, personal data; docs/API.md "Account
 * deletion"). Other modules react to `MemberRemoved` (Goals drops the
 * member from goal plans and open periods).
 */
class RemoveMember
{
    public function __construct(private readonly EraseUser $erase) {}

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

            MemberRemoved::dispatch($member->id, $member->family_id);

            if ($user->isChildAccount()) {
                $this->erase->handle($user, 'child_removed');
            }
        });
    }
}
