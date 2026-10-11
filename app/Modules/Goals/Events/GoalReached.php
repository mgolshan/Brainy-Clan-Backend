<?php

namespace App\Modules\Goals\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A closed goal period reached its target (cooperation: team total; competition: a member's target).
 */
class GoalReached
{
    use Dispatchable;

    /**
     * @param  list<int>  $winnerMemberIds
     */
    public function __construct(
        public readonly int $periodId,
        public readonly int $planId,
        public readonly int $familyId,
        public readonly array $winnerMemberIds,
    ) {}
}
