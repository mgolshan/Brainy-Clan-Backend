<?php

namespace App\Modules\Goals\Http\Resources;

use App\Modules\Content\Models\Category;
use App\Modules\Goals\Actions\ComputeGoalProgress;
use App\Modules\Goals\Models\GoalPeriod;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Goals\Models\GoalPlanMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * GoalPlan (docs/API.md). Expects LoadGoalPlans::prepare() to have set `currentPeriod`.
 *
 * @property GoalPlan $resource
 */
class GoalPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $plan = $this->resource;
        $locale = $request->user()->locale ?? 'en';
        /** @var GoalPeriod|null $period */
        $period = $plan->relationLoaded('currentPeriod') ? $plan->getRelation('currentPeriod') : null;

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'mode' => $plan->mode,
            'period_type' => $plan->period_type,
            'target_points' => $plan->target_points,
            'reward_text' => $plan->reward_text,
            'starts_on' => $plan->starts_on->toDateString(),
            'ends_on' => $plan->ends_on?->toDateString(),
            'is_active' => $plan->is_active,
            'scopes' => $plan->categories->map(fn (Category $category) => [
                'category_id' => $category->id,
                'name' => $category->localizedName((string) $locale),
            ])->values()->all(),
            'members' => $plan->planMembers->map(fn (GoalPlanMember $m) => [
                'member_id' => $m->member_id,
                'nickname' => $m->member->nickname,
                'avatar' => $m->member->user->avatar,
                'target_override' => $m->target_override,
            ])->values()->all(),
            'current_period' => $period !== null ? ($period->progress ?? app(ComputeGoalProgress::class)->handle($period)) : null,
        ];
    }
}
