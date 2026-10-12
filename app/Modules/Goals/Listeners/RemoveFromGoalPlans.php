<?php

namespace App\Modules\Goals\Listeners;

use App\Modules\Family\Events\MemberRemoved;
use App\Modules\Goals\Actions\RemoveMemberFromGoals;

/**
 * Synchronous (inside the removal transaction).
 */
class RemoveFromGoalPlans
{
    public function __construct(private readonly RemoveMemberFromGoals $remove) {}

    public function handle(MemberRemoved $event): void
    {
        $this->remove->handle($event->memberId, $event->familyId);
    }
}
