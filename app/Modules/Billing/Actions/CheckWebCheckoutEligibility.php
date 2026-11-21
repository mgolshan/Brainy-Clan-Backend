<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\PolicyResolver;

/**
 * Who may buy a paid plan (Family, Big Clan or Classroom) on the web (docs/API.md "Web checkout"): an adult admin of a family
 * (`purchases_allowed`) without an active plan — neither a store plan (RevenueCat) nor a web plan
 * (Paddle). A family holds at most one active plan from one source. A Classroom also needs the
 * family owner (the teacher) to be at least `classroom.owner_min_age` (18): `owner_too_young`.
 */
class CheckWebCheckoutEligibility
{
    public const string NO_ACCOUNT = 'no_account';

    public const string NO_FAMILY = 'no_family';

    public const string NOT_ADMIN = 'not_admin';

    public const string PURCHASES_NOT_ALLOWED = 'purchases_not_allowed';

    /** Classroom only: the family owner is younger than `classroom.owner_min_age`. */
    public const string OWNER_TOO_YOUNG = 'owner_too_young';

    /** The family has a store plan (App Store / Google Play) — manage it there. */
    public const string STORE_PLAN_ACTIVE = 'store_plan_active';

    /** The family already has a web plan (Paddle). */
    public const string WEB_PLAN_ACTIVE = 'web_plan_active';

    public function __construct(private readonly PolicyResolver $policy) {}

    /**
     * Null when the user may buy now, else the reason (one of the constants).
     */
    public function forUser(?User $user, string $tier = Family::TIER_FAMILY): ?string
    {
        if ($user === null || $user->erased_at !== null) {
            return self::NO_ACCOUNT;
        }

        $member = $user->activeMembership();

        return $member === null ? self::NO_FAMILY : $this->forMember($member, $tier);
    }

    public function forMember(FamilyMember $member, string $tier = Family::TIER_FAMILY): ?string
    {
        if (! $member->isAdmin()) {
            return self::NOT_ADMIN;
        }

        if ($this->ownerTooYoungFor($member, $tier) !== null) {
            return self::OWNER_TOO_YOUNG;
        }

        if (! $this->policy->purchasesAllowedFor($member)) {
            return self::PURCHASES_NOT_ALLOWED;
        }

        $active = $this->activePlan($member->family_id);

        if ($active === null) {
            return null;
        }

        return $active->isPaddle() ? self::WEB_PLAN_ACTIVE : self::STORE_PLAN_ACTIVE;
    }

    /**
     * The minimum owner age the tier needs when the family owner is younger (Classroom: 18), else
     * null. The family owner is checked, not the buyer: the owner becomes the teacher; the buyer is
     * an adult admin anyway (`purchases_allowed`).
     */
    public function ownerTooYoungFor(FamilyMember $member, string $tier): ?int
    {
        $minAge = $this->policy->minOwnerAgeFor($tier);

        if ($minAge === null) {
            return null;
        }

        $member->loadMissing('family.owner');
        $owner = $member->family->owner;

        return $owner !== null && ! $this->policy->userIsAtLeast($owner, $minAge) ? $minAge : null;
    }

    /**
     * The family's entitled subscription row (store or web), if any.
     */
    public function activePlan(int $familyId): ?Subscription
    {
        return Subscription::query()->where('family_id', $familyId)->get()
            ->first(fn (Subscription $subscription): bool => $subscription->isEntitled());
    }
}
