<?php

namespace App\Modules\Play\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A play session reached its target.
 */
class SessionCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly int $sessionId,
        public readonly int $familyId,
        public readonly int $memberId,
        public readonly int $points,
    ) {}
}
