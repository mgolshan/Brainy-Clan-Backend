<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a family on the free tier; the caller becomes its owner and an admin.
 */
class CreateFamily
{
    /**
     * @param  array{name: string, timezone: string, country_code: string}  $data
     */
    public function handle(User $user, array $data): Family
    {
        if ($user->activeMembership() !== null) {
            throw ApiException::alreadyInFamily();
        }

        return DB::transaction(function () use ($user, $data): Family {
            $family = Family::query()->create([
                'owner_user_id' => $user->id,
                'name' => $data['name'],
                'timezone' => $data['timezone'],
                'country_code' => strtoupper($data['country_code']),
                'tier' => Family::TIER_FREE,
            ]);

            FamilyMember::query()->create([
                'family_id' => $family->id,
                'user_id' => $user->id,
                'role' => FamilyMember::ROLE_ADMIN,
                'nickname' => $user->display_name,
                'status' => FamilyMember::STATUS_ACTIVE,
                'joined_at' => now(),
            ]);

            $user->unsetRelation('activeMember');

            return $family;
        });
    }
}
