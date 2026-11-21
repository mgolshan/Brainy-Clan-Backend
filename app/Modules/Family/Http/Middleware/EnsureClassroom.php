<?php

namespace App\Modules\Family\Http\Middleware;

use App\Modules\Family\Actions\ClassroomSetupState;
use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `classroom` middleware (after `family:admin`): 403 `plan_required` unless the family is on the
 * Classroom tier. `classroom:consent` also needs the current consent statement (409
 * `classroom_consent_required`). Runs before the Form Request, so these answers come before 422s.
 */
class EnsureClassroom
{
    public function __construct(private readonly ClassroomSetupState $state) {}

    public function handle(Request $request, Closure $next, ?string $requirement = null): Response
    {
        $user = $request->user();
        $member = $user instanceof User ? $user->activeMembership() : null;

        if ($member !== null) {
            $this->state->ensure($member->family, $requirement === 'consent');
        }

        return $next($request);
    }
}
