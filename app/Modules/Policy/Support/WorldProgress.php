<?php

namespace App\Modules\Policy\Support;

use App\Modules\Policy\Models\ThemeWorld;

/**
 * What a member has achieved towards theme worlds (see EvaluateWorldUnlocks for the sources).
 */
final readonly class WorldProgress
{
    public function __construct(
        public int $lifetimePoints,
        public int $longestStreak,
        public bool $familyPlan,
    ) {}

    /**
     * @param  array{unlock_type: string, required: int|null}  $world
     */
    public function unlocks(array $world): bool
    {
        return match ($world['unlock_type']) {
            ThemeWorld::UNLOCK_FREE => true,
            ThemeWorld::UNLOCK_POINTS => $this->lifetimePoints >= (int) $world['required'],
            ThemeWorld::UNLOCK_STREAK => $this->longestStreak >= (int) $world['required'],
            ThemeWorld::UNLOCK_FAMILY_PLAN => $this->familyPlan,
            default => false,
        };
    }

    /**
     * Progress towards a `points` / `streak` world, null for the other types.
     *
     * @param  array{unlock_type: string}  $world
     */
    public function progressFor(array $world): ?int
    {
        return match ($world['unlock_type']) {
            ThemeWorld::UNLOCK_POINTS => $this->lifetimePoints,
            ThemeWorld::UNLOCK_STREAK => $this->longestStreak,
            default => null,
        };
    }
}
