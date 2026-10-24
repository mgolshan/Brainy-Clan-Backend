<?php

namespace App\Modules\Family\Actions;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\PolicyResolver;

/**
 * `GET /me/deletion-check`: may this user delete their account, and what goes with it.
 * Blockers: `family_owner` (owner of a family with other active members: delete the family
 * instead), `child_account` (not an adult: an admin removes children/teens, which erases them).
 */
class CheckAccountDeletion
{
    public const string BLOCKER_FAMILY_OWNER = 'family_owner';

    public const string BLOCKER_CHILD_ACCOUNT = 'child_account';

    public function __construct(private readonly PolicyResolver $policy) {}

    /**
     * @return array{can_delete: bool, blockers: list<string>, deletes_family: bool, active_subscription: bool, store: string|null}
     */
    public function handle(User $user): array
    {
        $member = $user->activeMembership();
        $blockers = [];
        $deletesFamily = false;
        $subscription = null;

        $band = $member !== null
            ? $this->policy->bandForMember($member)
            : $this->policy->bandForBirth($user->birth_year, $user->birth_month);

        if (! $this->policy->collectsEmail($band)) {
            $blockers[] = self::BLOCKER_CHILD_ACCOUNT;
        }

        if ($member !== null) {
            if ($member->isOwner()) {
                $others = FamilyMember::query()
                    ->where('family_id', $member->family_id)
                    ->where('status', FamilyMember::STATUS_ACTIVE)
                    ->whereKeyNot($member->id)
                    ->exists();

                if ($others) {
                    $blockers[] = self::BLOCKER_FAMILY_OWNER;
                } else {
                    $deletesFamily = true;
                }
            }

            $subscription = Subscription::currentFor($member->family_id);
        }

        $active = $subscription !== null && $subscription->isEntitled();

        return [
            'can_delete' => $blockers === [],
            'blockers' => $blockers,
            'deletes_family' => $deletesFamily,
            'active_subscription' => $active,
            'store' => $active ? $subscription->apiStore() : null,
        ];
    }
}
