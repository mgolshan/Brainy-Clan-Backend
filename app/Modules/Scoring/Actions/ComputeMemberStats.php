<?php

namespace App\Modules\Scoring\Actions;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Support\FamilyClock;
use App\Modules\Scoring\Models\PointEntry;

/**
 * Points today / this week / all time (family timezone) and the streak, from the ledger.
 */
class ComputeMemberStats
{
    public function __construct(private readonly RecordDailyActivity $streaks) {}

    /**
     * @return array{points_today: int, points_week: int, points_total: int, streak: array{current: int, longest: int}}
     */
    public function handle(FamilyMember $member): array
    {
        [$todayStart, $todayEnd] = FamilyClock::window($member->family, 'today');
        [$weekStart, $weekEnd] = FamilyClock::window($member->family, 'week');
        $base = fn () => PointEntry::query()->where('member_id', $member->id);

        return [
            'points_today' => (int) $base()->where('created_at', '>=', $todayStart)->where('created_at', '<', $todayEnd)->sum('amount'),
            'points_week' => (int) $base()->where('created_at', '>=', $weekStart)->where('created_at', '<', $weekEnd)->sum('amount'),
            'points_total' => (int) $base()->sum('amount'),
            'streak' => $this->streaks->current($member),
        ];
    }
}
