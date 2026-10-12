<?php

namespace App\Filament\Resources\GoalPlans\Pages;

use App\Filament\Resources\GoalPlans\GoalPlanResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewGoalPlan extends ViewRecord
{
    protected static string $resource = GoalPlanResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load(['family', 'categories', 'planMembers.member']);
    }
}
