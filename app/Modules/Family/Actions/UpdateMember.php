<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Support\Accessibility;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Support\Facades\DB;

/**
 * Admin edits a member: nickname, avatar, difficulty override, birth data, role, accessibility
 * and the member's email login. The family owner can never be demoted, and only the owner can
 * change the owner's email login.
 *
 * `email`: adds, changes or removes (null) the email login. Only when the member's age band has
 * `email_login_allowed` (checked after any birth-data change in the same request) and the
 * address is free. Removing it also clears the password; the member sets a new password on
 * their own device (`PUT /me/password`).
 */
class UpdateMember
{
    public function __construct(private readonly PolicyResolver $policy) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(FamilyMember $member, array $data, ?FamilyMember $actor = null): FamilyMember
    {
        if (array_key_exists('role', $data) && $data['role'] !== FamilyMember::ROLE_ADMIN && $member->isOwner()) {
            throw ApiException::forbidden('The family owner is always an admin.');
        }

        if (array_key_exists('email', $data) && $member->isOwner() && $actor !== null && $actor->id !== $member->id) {
            throw ApiException::forbidden('Only the family owner can change their own email login.');
        }

        return DB::transaction(function () use ($member, $data): FamilyMember {
            $memberFields = array_intersect_key($data, array_flip(['nickname', 'difficulty_override', 'role']));

            if (array_key_exists('accessibility', $data) && is_array($data['accessibility'])) {
                $memberFields['accessibility'] = Accessibility::merge($member->accessibility, $data['accessibility']);
            }

            $member->fill($memberFields)->save();

            $user = $member->user;
            $userFields = array_intersect_key($data, array_flip(['avatar', 'birth_year', 'birth_month']));

            if ($userFields !== []) {
                $user->fill($userFields)->save();
            }

            if (array_key_exists('email', $data)) {
                $this->updateEmailLogin($member, $user, is_string($data['email']) ? $data['email'] : null);
            }

            return $member->refresh();
        });
    }

    private function updateEmailLogin(FamilyMember $member, User $user, ?string $email): void
    {
        if ($email === null) {
            $user->forceFill(['email' => null, 'password' => null])->save();

            return;
        }

        $email = strtolower(trim($email));

        if (! $this->policy->emailLoginAllowedFor($member)) {
            throw ApiException::validation('email', 'An email login is not allowed for this member\'s age.');
        }

        if (User::query()->where('email', $email)->whereKeyNot($user->id)->exists()) {
            throw ApiException::validation('email', 'The email has already been taken.');
        }

        $user->forceFill(['email' => $email])->save();
    }
}
