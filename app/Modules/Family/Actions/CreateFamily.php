<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Events\MemberJoined;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Support\Facades\DB;

/**
 * Creates a family on the free tier; the caller becomes its owner and an admin. The caller must be
 * at least `family.owner_min_age` (16): else 403 `owner_too_young` with `min_age`. A user without
 * birth data counts as an adult (like everywhere in the PolicyResolver).
 */
class CreateFamily
{
    public function __construct(private readonly PolicyResolver $policy) {}

    /**
     * @param  array{name: string, timezone: string, country_code: string}  $data
     */
    public function handle(User $user, array $data): Family
    {
        if ($user->activeMembership() !== null) {
            throw ApiException::alreadyInFamily();
        }

        $minAge = $this->policy->familyOwnerMinAge();

        if (! $this->policy->userIsAtLeast($user, $minAge)) {
            throw ApiException::ownerTooYoung($minAge, "You need to be at least {$minAge} to create a family. Ask a parent to invite you to theirs.");
        }

        return DB::transaction(function () use ($user, $data): Family {
            $family = Family::query()->create([
                'owner_user_id' => $user->id,
                'name' => $data['name'],
                'timezone' => $data['timezone'],
                'country_code' => strtoupper($data['country_code']),
                'tier' => Family::TIER_FREE,
            ]);

            $member = FamilyMember::query()->create([
                'family_id' => $family->id,
                'user_id' => $user->id,
                'role' => FamilyMember::ROLE_ADMIN,
                'nickname' => $user->display_name,
                'status' => FamilyMember::STATUS_ACTIVE,
                'joined_at' => now(),
            ]);

            MemberJoined::dispatch($member->id, $family->id);

            $user->unsetRelation('activeMember');

            return $family;
        });
    }
}
