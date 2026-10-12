<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\PolicyResolver;

/**
 * Social sign-in is for adults only: the user's age band must allow self-registration
 * (`collect_email`, like `/auth/register`). Children never use Google/Facebook.
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

        if (! $this->policy->collectsEmail($band)) {
            throw ApiException::forbidden('Google and Facebook sign-in are only available to adults.');
        }
    }
}
