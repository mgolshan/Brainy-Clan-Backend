<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\OnboardingProgress;
use App\Modules\Identity\Models\User;

/**
 * `POST /me/onboarding/restore` (docs/API.md "Onboarding (FTUE)"): shows the "Getting started"
 * checklist again after it was hidden. Step progress is kept. Idempotent. Returns the whole
 * Onboarding object.
 */
class RestoreOnboarding
{
    public function __construct(private readonly GetOnboarding $get) {}

    /**
     * @return array{steps: array<string, string|null>, dismissed_at: string|null, flow: string, required_steps: list<string>, optional_steps: list<string>}
     */
    public function handle(User $user): array
    {
        OnboardingProgress::query()
            ->where('user_id', $user->id)
            ->whereNotNull('dismissed_at')
            ->update(['dismissed_at' => null]);

        return $this->get->handle($user);
    }
}
