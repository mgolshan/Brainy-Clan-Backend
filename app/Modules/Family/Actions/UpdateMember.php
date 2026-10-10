<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Support\Accessibility;
use Illuminate\Support\Facades\DB;

/**
 * Admin edits a member: nickname, avatar, difficulty override, birth data, role, accessibility.
 * The family owner can never be demoted.
 */
class UpdateMember
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(FamilyMember $member, array $data): FamilyMember
    {
        if (array_key_exists('role', $data) && $data['role'] !== FamilyMember::ROLE_ADMIN && $member->isOwner()) {
            throw ApiException::forbidden('The family owner is always an admin.');
        }

        return DB::transaction(function () use ($member, $data): FamilyMember {
            $memberFields = array_intersect_key($data, array_flip(['nickname', 'difficulty_override', 'role']));

            if (array_key_exists('accessibility', $data) && is_array($data['accessibility'])) {
                $memberFields['accessibility'] = Accessibility::merge($member->accessibility, $data['accessibility']);
            }

            $member->fill($memberFields)->save();

            $userFields = array_intersect_key($data, array_flip(['avatar', 'birth_year', 'birth_month']));

            if ($userFields !== []) {
                $member->user->fill($userFields)->save();
            }

            return $member->refresh();
        });
    }
}
