<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;

/**
 * `POST /me/onboarding/dismiss` (docs/API.md "Onboarding (FTUE)"): hides the "Getting started"
 * checklist and the welcome sheet (tips and the upgrade sheet keep working). Idempotent: keeps the
 * first `dismissed_at`. Returns the whole Onboarding object.
 */
class DismissOnboarding
{
    public function __construct(
        private readonly RecordOnboardingSteps $record,
        private readonly GetOnboarding $get,
    ) {}

    /**
     * @return array{steps: array<string, string|null>, dismissed_at: string|null, flow: string, required_steps: list<string>, optional_steps: list<string>}
     */
    public function handle(User $user): array
    {
        $this->record->handle($user, [], dismiss: true);

        return $this->get->handle($user);
    }
}
