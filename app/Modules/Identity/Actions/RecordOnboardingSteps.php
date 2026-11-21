<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Core\Support\Iso;
use App\Modules\Identity\Models\OnboardingProgress;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Stores onboarding steps as done (docs/API.md "Onboarding (FTUE)"): a step already done keeps its
 * first `done_at`, and `dismissed_at` keeps its first time, so every write is idempotent. The row is
 * locked while it is merged, so two devices writing at once never lose a step.
 */
class RecordOnboardingSteps
{
    /**
     * @param  list<string>  $steps
     */
    public function handle(User $user, array $steps, bool $dismiss = false): OnboardingProgress
    {
        return DB::transaction(function () use ($user, $steps, $dismiss): OnboardingProgress {
            OnboardingProgress::query()->insertOrIgnore([
                'user_id' => $user->id,
                'steps' => '{}',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /** @var OnboardingProgress $progress */
            $progress = OnboardingProgress::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            $done = $progress->steps;
            $now = (string) Iso::format(now());

            foreach ($steps as $step) {
                $done[$step] ??= $now;
            }

            $progress->steps = $done;

            if ($dismiss && $progress->dismissed_at === null) {
                $progress->dismissed_at = now();
            }

            $progress->save();

            return $progress;
        });
    }
}
