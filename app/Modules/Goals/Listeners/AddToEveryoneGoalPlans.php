<?php

namespace App\Modules\Goals\Listeners;

use App\Modules\Family\Events\MemberJoined;
use App\Modules\Goals\Actions\JoinEveryoneGoalPlans;

/**
 * Synchronous (inside the joining transaction): the newcomer joins every `everyone` plan.
 */
class AddToEveryoneGoalPlans
{
    public function __construct(private readonly JoinEveryoneGoalPlans $join) {}

    public function handle(MemberJoined $event): void
    {
        $this->join->newcomer($event->memberId, $event->familyId);
    }
}
