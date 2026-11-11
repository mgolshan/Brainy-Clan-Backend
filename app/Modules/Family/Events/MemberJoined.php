<?php

namespace App\Modules\Family\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Someone became an active member of a family: they created it (owner) or joined it with an invite
 * (a child, a teen or an adult; a re-join after a removal is a new membership too). Dispatched
 * inside the joining transaction, so synchronous listeners set up atomically (Goals adds the member
 * to the family's `everyone` plans and their open periods).
 */
class MemberJoined
{
    use Dispatchable;

    public function __construct(
        public readonly int $memberId,
        public readonly int $familyId,
    ) {}
}
