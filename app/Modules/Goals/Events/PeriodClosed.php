<?php

namespace App\Modules\Goals\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A goal period ended and its result was written.
 */
class PeriodClosed
{
    use Dispatchable;

    /**
     * @param  list<int>  $winnerMemberIds
     */
    public function __construct(
        public readonly int $periodId,
        public readonly int $planId,
        public readonly int $familyId,
        public readonly bool $reached,
        public readonly array $winnerMemberIds,
    ) {}
}
