<?php

namespace App\Modules\Goals\Listeners;

use App\Modules\Family\Events\FamilyDeleted;
use App\Modules\Goals\Models\GoalPlan;

/**
 * A deleted family keeps no running goals: its plans are soft-deleted (periods stop closing).
 */
class DeleteFamilyGoalPlans
{
    public function handle(FamilyDeleted $event): void
    {
        GoalPlan::query()->where('family_id', $event->familyId)->update(['deleted_at' => now()]);
    }
}
