<?php

namespace App\Modules\Play\Support;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Support\FamilyClock;
use App\Modules\Play\Models\SecondChance;
use App\Modules\Policy\Contracts\SecondChanceUsage;

/**
 * Free second chances a member used since midnight in the family's timezone.
 */
class FreeSecondChanceCounter implements SecondChanceUsage
{
    public function freeUsedToday(FamilyMember $member): int
    {
        [$start] = FamilyClock::window($member->family, 'today');

        return SecondChance::query()
            ->where('member_id', $member->id)
            ->where('method', SecondChance::METHOD_FREE)
            ->where('granted_at', '>=', $start)
            ->count();
    }
}
