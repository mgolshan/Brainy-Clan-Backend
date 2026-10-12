<?php

namespace App\Modules\Family\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A member left the family (status `removed`). Dispatched inside the removal transaction, so
 * synchronous listeners clean up atomically.
 */
class MemberRemoved
{
    use Dispatchable;

    public function __construct(
        public readonly int $memberId,
        public readonly int $familyId,
    ) {}
}
