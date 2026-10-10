<?php

namespace App\Modules\Policy\Contracts;

use App\Modules\Family\Models\FamilyMember;

/**
 * Tells the PolicyResolver how many free second chances a member used today
 * (implemented by the Play module, so Policy does not reach into Play's tables).
 */
interface SecondChanceUsage
{
    public function freeUsedToday(FamilyMember $member): int;
}
