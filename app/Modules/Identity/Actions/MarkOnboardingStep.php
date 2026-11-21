<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\OnboardingSteps;

/**
 * `POST /me/onboarding/{step}` (docs/API.md "Onboarding (FTUE)"): marks a **manual** step done
 * (idempotent: a done step keeps its first `done_at`) and returns the whole Onboarding object.
 * Unknown and derived keys are 422 on `step` (checked by the Form Request; again here for callers
 * outside HTTP).
 */
class MarkOnboardingStep
{
    public function __construct(
        private readonly RecordOnboardingSteps $record,
        private readonly GetOnboarding $get,
    ) {}

    /**
     * @return array{steps: array<string, string|null>, dismissed_at: string|null, flow: string, required_steps: list<string>, optional_steps: list<string>}
     */
    public function handle(User $user, string $step): array
    {
        if (! OnboardingSteps::isManual($step)) {
            throw ApiException::validation('step', OnboardingSteps::isDerived($step)
                ? 'This step is done automatically.'
                : 'Unknown onboarding step.');
        }

        $this->record->handle($user, [$step]);

        return $this->get->handle($user);
    }
}
