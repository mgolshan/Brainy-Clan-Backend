<?php

namespace App\Modules\Core\Http;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;

/**
 * Controller helper: the signed-in user and their active family membership.
 * Routes using it sit behind `auth:sanctum` + `family`, so a membership exists.
 */
trait CurrentMember
{
    protected function currentUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new ApiException('unauthenticated', 'Unauthenticated.', 401);
        }

        return $user;
    }

    protected function currentMember(Request $request): FamilyMember
    {
        $member = $this->currentUser($request)->activeMembership();

        if ($member === null) {
            throw ApiException::noFamily();
        }

        return $member;
    }
}
