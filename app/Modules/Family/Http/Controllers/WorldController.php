<?php

namespace App\Modules\Family\Http\Controllers;

use App\Modules\Core\Http\CurrentMember;
use App\Modules\Policy\Actions\EvaluateWorldUnlocks;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Family')]
class WorldController
{
    use CurrentMember;

    /**
     * My theme worlds.
     *
     * The world catalog in display order with how each world unlocks and whether it is unlocked
     * for me: `free` (always), `points` (lifetime points ≥ `required`), `streak` (longest streak
     * in days ≥ `required`) or `family_plan` (the family is on the paid plan: active, trial or
     * grace). `required` and `progress` only for `points` / `streak`. Points and streak worlds
     * stay unlocked once reached; family plan worlds lock again when the plan ends.
     */
    public function me(Request $request, EvaluateWorldUnlocks $worlds): JsonResponse
    {
        return new JsonResponse(['data' => $worlds->handle($this->currentMember($request))]);
    }

    /**
     * A member's theme worlds (admin).
     *
     * The same catalog as `GET /me/worlds`, for a member of my family: an admin can only pick
     * worlds unlocked for that member. 404 for members of other families.
     */
    public function forMember(Request $request, int $member, EvaluateWorldUnlocks $worlds): JsonResponse
    {
        $target = $this->currentMember($request)->family->members()->with('family')->findOrFail($member);

        return new JsonResponse(['data' => $worlds->handle($target)]);
    }
}
