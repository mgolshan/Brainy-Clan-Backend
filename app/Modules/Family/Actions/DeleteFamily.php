<?php

namespace App\Modules\Family\Actions;

use App\Modules\Family\Events\FamilyDeleted;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\Invite;
use App\Modules\Family\Models\SignInCode;
use App\Modules\Identity\Actions\EraseUser;
use App\Modules\Identity\Models\SocialAccount;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * `DELETE /family` (owner): every membership becomes `removed`, pending invites and unused sign-in
 * codes go, every child account (no email login and no social account, so it can not exist outside
 * the family) is erased, the other adults keep their accounts without a family, and the owner is
 * erased too when asked (`delete_my_account`, or `DELETE /me` by the only member). The family row
 * stays as "Deleted family" with `deleted_at` (the append-only ledger and billing records reference
 * it); `FamilyDeleted` lets Goals drop the family's plans. A fixed number of queries for any family size.
 */
class DeleteFamily
{
    public const string DELETED_NAME = 'Deleted family';

    public function __construct(private readonly EraseUser $erase) {}

    public function handle(Family $family, bool $deleteOwnerAccount): void
    {
        DB::transaction(function () use ($family, $deleteOwnerAccount): void {
            $members = FamilyMember::query()
                ->where('family_id', $family->id)
                ->where('status', FamilyMember::STATUS_ACTIVE)
                ->lockForUpdate()
                ->get(['id', 'user_id']);

            $memberIds = $members->pluck('id')->all();
            $userIds = $members->pluck('user_id')->all();
            $socialUserIds = SocialAccount::query()->whereIn('user_id', $userIds)->distinct()->pluck('user_id')->all();

            $toErase = User::query()
                ->whereIn('id', $userIds)
                ->where(fn ($query) => $query
                    ->where(fn ($query) => $query->whereNull('email')->whereNotIn('id', $socialUserIds))
                    ->when($deleteOwnerAccount, fn ($query) => $query->orWhere('id', $family->owner_user_id)))
                ->get(['id']);

            FamilyMember::query()->whereIn('id', $memberIds)->update([
                'status' => FamilyMember::STATUS_REMOVED,
                'updated_at' => now(),
            ]);
            SignInCode::query()
                ->whereIn('member_id', $memberIds)
                ->whereNull('used_at')
                ->whereNull('voided_at')
                ->update(['voided_at' => now()]);
            Invite::query()->where('family_id', $family->id)->whereNull('used_at')->delete();

            // A query update, not a model save: the family's activity log must not keep the old name.
            Family::query()->whereKey($family->id)->update([
                'name' => self::DELETED_NAME,
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

            // Earlier audit entries (created / renamed) carry the old name in their changes; keep the
            // entries (who did what, when) but drop the values.
            Activity::query()
                ->where('subject_type', $family->getMorphClass())
                ->where('subject_id', $family->id)
                ->update(['attribute_changes' => null, 'properties' => json_encode(['erased' => true])]);

            FamilyDeleted::dispatch($family->id);

            $this->erase->handleMany($toErase, 'family_deleted');
        });
    }
}
