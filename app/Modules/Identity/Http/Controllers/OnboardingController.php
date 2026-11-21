<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Core\Http\CurrentMember;
use App\Modules\Identity\Actions\DismissOnboarding;
use App\Modules\Identity\Actions\GetOnboarding;
use App\Modules\Identity\Actions\MarkOnboardingStep;
use App\Modules\Identity\Http\Requests\MarkOnboardingStepRequest;
use App\Modules\Identity\Http\Resources\OnboardingResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

/**
 * Onboarding / FTUE (docs/API.md "Onboarding (FTUE)", B12 step 4): progress stored per user, so a
 * second device never repeats it. No family needed.
 */
#[Group('Identity')]
class OnboardingController
{
    use CurrentMember;

    /**
     * My onboarding progress.
     *
     * Every step key with its `done_at` (or `null`), `dismissed_at`, and the flow for my plan and
     * role with its steps. Derived steps (family created, member added, first game, first goal,
     * first chat message, Classroom consent, roster, code cards…) are checked against real data and
     * stay done once done.
     */
    public function show(Request $request, GetOnboarding $get): OnboardingResource
    {
        return new OnboardingResource($get->handle($this->currentUser($request)));
    }

    /**
     * Mark an onboarding step done.
     *
     * Manual steps only (welcome sheets, tips, "what's new" sheets…); a derived or unknown key is
     * 422 on `step`. Idempotent: a done step keeps its first `done_at`. Returns the whole object.
     */
    public function store(MarkOnboardingStepRequest $request, MarkOnboardingStep $mark): OnboardingResource
    {
        return new OnboardingResource($mark->handle($this->currentUser($request), $request->step()));
    }

    /**
     * Hide the getting-started checklist.
     *
     * Hides the checklist and the welcome sheet; tips and the upgrade sheet keep working.
     * Idempotent: keeps the first `dismissed_at`. Returns the whole object.
     */
    public function dismiss(Request $request, DismissOnboarding $dismiss): OnboardingResource
    {
        return new OnboardingResource($dismiss->handle($this->currentUser($request)));
    }
}
