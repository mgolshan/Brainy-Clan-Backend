<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\PolicyResolver;

/**
 * Social sign-in follows the self sign-up age: the user's age band allows email (`collect_email`,
 * adults) or the user is old enough to sign up alone (`PolicyResolver::selfSignUpAllowed()`,
 * `family.owner_min_age`, 16), so a 16- or 17-year-old who signed up with Google or Apple can
 * sign in again. Children never use Google/Facebook/Apple.
 */
class EnsureSocialSignInAllowed
{
    public function __construct(private readonly PolicyResolver $policy) {}

    public function handle(User $user): void
    {
        $member = $user->activeMembership();
        $band = $member !== null
            ? $this->policy->bandForMember($member)
            : $this->policy->bandForBirth($user->birth_year, $user->birth_month);

        if (! $this->policy->collectsEmail($band) && ! $this->policy->selfSignUpAllowed($user->birth_year, $user->birth_month)) {
            throw ApiException::forbidden('Google, Apple and Facebook sign-in are only available from age '.$this->policy->familyOwnerMinAge().'.');
        }
    }
}
