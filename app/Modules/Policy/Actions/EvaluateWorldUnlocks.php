<?php

namespace App\Modules\Policy\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Policy\Models\ThemeWorld;
use App\Modules\Policy\Support\ThemeWorldCatalog;
use App\Modules\Policy\Support\WorldProgress;
use App\Modules\Scoring\Models\PointEntry;
use App\Modules\Scoring\Models\Streak;
use Illuminate\Support\Collection;

/**
 * Which theme worlds a member has unlocked (docs/API.md "Worlds and unlocks"). Never cached:
 * points and streaks change during play. The catalog itself comes from ThemeWorldCatalog
 * (ReferenceCache), so evaluating one member is a fixed two queries whatever the catalog size
 * (lifetime points + longest streak; the family tier is read from the loaded family), and a
 * list of members is also two queries in total ({@see progressForMembers()}).
 *
 * Sources:
 * - **Lifetime points** = the sum of the member's **positive** ledger entries (points earned).
 *   Compensating entries (negative corrections) are ignored on purpose, so a corrected ledger
 *   never re-locks a world: "once unlocked stays unlocked" (docs/API.md). This equals
 *   `/me/stats` `points_total` (all entries) as long as the ledger has no corrections.
 * - **Longest streak** = `streaks.longest`, the same value as `/me/stats` `streak.longest`; it
 *   never decreases.
 * - **Family plan** = the family's tier is `family`, which Billing sets while the subscription is
 *   active, in a trial or in the billing grace period (ApplyFamilyTier). It drops back to `free`
 *   when the plan ends, so `family_plan` worlds lock again; the stored theme is kept and the
 *   member resource reports `theme_unlocked: false`.
 */
class EvaluateWorldUnlocks
{
    public function __construct(private readonly ThemeWorldCatalog $catalog) {}

    /**
     * The catalog for this member, in display order.
     *
     * @return list<array{key: string, unlock: array{type: string, required?: int, progress?: int}, unlocked: bool}>
     */
    public function handle(FamilyMember $member): array
    {
        $progress = $this->progress($member);
        $rows = [];

        foreach ($this->catalog->all() as $world) {
            $unlock = ['type' => $world['unlock_type']];
            $value = $progress->progressFor($world);

            if ($value !== null) {
                $unlock['required'] = (int) $world['required'];
                $unlock['progress'] = $value;
            }

            $rows[] = ['key' => $world['key'], 'unlock' => $unlock, 'unlocked' => $progress->unlocks($world)];
        }

        return $rows;
    }

    /**
     * Lifetime points, longest streak and the family plan of one member (two queries).
     */
    public function progress(FamilyMember $member): WorldProgress
    {
        $points = (int) PointEntry::query()->where('member_id', $member->id)->where('amount', '>', 0)->sum('amount');
        $longest = (int) Streak::query()->where('member_id', $member->id)->value('longest');

        return new WorldProgress($points, $longest, $this->hasFamilyPlan($member));
    }

    /**
     * Progress for the members of a list whose stored theme needs points or a streak, in two
     * queries for the whole list (none when no member needs them). Members without an entry
     * need no progress: their theme is null, free, a family plan world or not in the catalog.
     *
     * @param  Collection<int, FamilyMember>  $members  with `family` loaded
     * @return array<int, WorldProgress> keyed by member id
     */
    public function progressForMembers(Collection $members): array
    {
        $needing = $members->filter(fn (FamilyMember $member): bool => $this->needsProgress($member))->values();

        if ($needing->isEmpty()) {
            return [];
        }

        $ids = $needing->map(fn (FamilyMember $member): int => $member->id)->all();

        /** @var array<int, int> $points */
        $points = PointEntry::query()->whereIn('member_id', $ids)->where('amount', '>', 0)
            ->groupBy('member_id')->selectRaw('member_id, SUM(amount) as points')
            ->pluck('points', 'member_id')->map(fn ($value): int => (int) $value)->all();

        /** @var array<int, int> $longest */
        $longest = Streak::query()->whereIn('member_id', $ids)->pluck('longest', 'member_id')
            ->map(fn ($value): int => (int) $value)->all();

        return $needing->mapWithKeys(fn (FamilyMember $member): array => [$member->id => new WorldProgress(
            $points[$member->id] ?? 0,
            $longest[$member->id] ?? 0,
            $this->hasFamilyPlan($member),
        )])->all();
    }

    /**
     * Whether the member may use this theme key now. Unknown or inactive worlds are locked.
     * Free and family plan worlds need no query; `$progress` avoids the two queries for
     * points/streak worlds when the caller already has it.
     */
    public function isUnlocked(FamilyMember $member, string $key, ?WorldProgress $progress = null): bool
    {
        $world = $this->catalog->find($key);

        return match ($world['unlock_type'] ?? null) {
            null => false,
            ThemeWorld::UNLOCK_FREE => true,
            ThemeWorld::UNLOCK_FAMILY_PLAN => $this->hasFamilyPlan($member),
            default => ($progress ?? $this->progress($member))->unlocks($world),
        };
    }

    /**
     * Member resource `theme_unlocked`: true when the stored theme is null or unlocked.
     */
    public function themeUnlocked(FamilyMember $member, ?WorldProgress $progress = null): bool
    {
        $theme = $this->storedTheme($member);

        return $theme === null || $this->isUnlocked($member, $theme, $progress);
    }

    /**
     * Guard for `PATCH /me/accessibility` and the admin member PATCH: `null` is always allowed,
     * a locked world is a 403 `world_locked`.
     *
     * @throws ApiException
     */
    public function ensureCanPick(FamilyMember $member, mixed $theme): void
    {
        if (is_string($theme) && ! $this->isUnlocked($member, $theme)) {
            throw ApiException::worldLocked();
        }
    }

    private function needsProgress(FamilyMember $member): bool
    {
        $theme = $this->storedTheme($member);
        $world = $theme !== null ? $this->catalog->find($theme) : null;

        return $world !== null && in_array($world['unlock_type'], ThemeWorld::THRESHOLD_TYPES, true);
    }

    private function storedTheme(FamilyMember $member): ?string
    {
        $theme = ($member->accessibility ?? [])['theme'] ?? null;

        return is_string($theme) ? $theme : null;
    }

    private function hasFamilyPlan(FamilyMember $member): bool
    {
        return $member->family->tier === Family::TIER_FAMILY;
    }
}
