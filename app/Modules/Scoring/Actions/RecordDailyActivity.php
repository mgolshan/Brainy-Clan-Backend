<?php

namespace App\Modules\Scoring\Actions;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Support\FamilyClock;
use App\Modules\Scoring\Models\Streak;
use Carbon\CarbonImmutable;

/**
 * Updates a member's streak for activity on a day (family timezone): same day = no change,
 * the day after the last active day = +1, a gap = restart at 1.
 */
class RecordDailyActivity
{
    public function handle(FamilyMember $member, ?CarbonImmutable $at = null): Streak
    {
        $today = ($at ?? CarbonImmutable::now())->setTimezone($member->family->timezone)->startOfDay();
        $streak = Streak::query()->firstOrNew(['member_id' => $member->id], ['current' => 0, 'longest' => 0]);
        $last = $streak->last_active_date !== null
            ? CarbonImmutable::parse($streak->last_active_date->format('Y-m-d'), $member->family->timezone)
            : null;

        if ($last !== null && $last->equalTo($today)) {
            return $streak;
        }

        $streak->current = $last !== null && $last->addDay()->equalTo($today) ? $streak->current + 1 : 1;
        $streak->longest = max($streak->longest, $streak->current);
        $streak->last_active_date = $today->toDateString();
        $streak->save();

        return $streak;
    }

    /**
     * The streak as it stands now: 0 when the last active day was before yesterday.
     *
     * @return array{current: int, longest: int}
     */
    public function current(FamilyMember $member): array
    {
        $streak = Streak::query()->where('member_id', $member->id)->first();

        if ($streak === null || $streak->last_active_date === null) {
            return ['current' => 0, 'longest' => $streak !== null ? $streak->longest : 0];
        }

        $today = FamilyClock::today($member->family);
        $last = CarbonImmutable::parse($streak->last_active_date->format('Y-m-d'), $member->family->timezone);
        $alive = $last->greaterThanOrEqualTo($today->subDay());

        return ['current' => $alive ? $streak->current : 0, 'longest' => $streak->longest];
    }
}
