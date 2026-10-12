<?php

namespace App\Modules\Goals\Http\Controllers;

use App\Modules\Core\Docs\ApiErrors;
use App\Modules\Core\Http\CurrentMember;
use App\Modules\Goals\Actions\ComputeGoalProgress;
use App\Modules\Goals\Actions\LoadGoalPlans;
use App\Modules\Goals\Actions\SaveGoalPlan;
use App\Modules\Goals\Http\Requests\StoreGoalRequest;
use App\Modules\Goals\Http\Requests\UpdateGoalRequest;
use App\Modules\Goals\Http\Resources\GoalPlanResource;
use App\Modules\Goals\Models\GoalPeriod;
use App\Modules\Goals\Models\GoalPlan;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Goals', 'Goal plans, current progress and closed-period history.', 6)]
class GoalController
{
    use CurrentMember;

    /**
     * Goal plans.
     *
     * Active plans first, each with the current period's progress.
     */
    public function index(Request $request, LoadGoalPlans $load): AnonymousResourceCollection
    {
        return GoalPlanResource::collection($load->forFamily($this->currentMember($request)->family));
    }

    /**
     * A goal plan.
     */
    public function show(Request $request, int $plan, LoadGoalPlans $load): GoalPlanResource
    {
        return new GoalPlanResource($load->prepare($this->familyPlan($request, $plan)));
    }

    /**
     * Create a goal plan (admin).
     *
     * Competition or cooperation over daily/weekly/monthly periods in the family timezone. 403
     * `goal_limit_reached` (tier), 403 `category_not_allowed` (locked category).
     */
    #[ApiErrors('goal_limit_reached', 'category_not_allowed')]
    public function store(StoreGoalRequest $request, SaveGoalPlan $save, LoadGoalPlans $load): JsonResponse
    {
        /** @var array{name: string, mode: string, period_type: string, target_points: int, reward_text?: string|null, starts_on: string, ends_on?: string|null, category_ids: list<int>, members?: list<array{member_id: int, target_override?: int|null}>|null} $data */
        $data = $request->validated();
        $member = $this->currentMember($request);
        $plan = $save->create($member, $data);

        return (new GoalPlanResource($load->prepare($plan->fresh() ?? $plan, $member->family)))->response()->setStatusCode(201);
    }

    /**
     * Update a goal plan (admin).
     *
     * Changes apply from the next period; the open period keeps its targets. `is_active: false`
     * pauses the plan.
     */
    #[ApiErrors('goal_limit_reached', 'category_not_allowed')]
    public function update(UpdateGoalRequest $request, int $plan, SaveGoalPlan $save, LoadGoalPlans $load): GoalPlanResource
    {
        $member = $this->currentMember($request);
        $saved = $save->update($this->familyPlan($request, $plan), $member, $request->validated());

        return new GoalPlanResource($load->prepare($saved->fresh() ?? $saved, $member->family));
    }

    /**
     * Delete a goal plan (admin).
     *
     * Soft delete: the plan disappears from the API (404 afterwards); its history is kept.
     */
    public function destroy(Request $request, int $plan, SaveGoalPlan $save): Response
    {
        $save->delete($this->familyPlan($request, $plan));

        return response()->noContent();
    }

    /**
     * Goal plan history.
     *
     * Closed periods, newest first, 20 per page, with winners.
     */
    public function history(Request $request, int $plan, ComputeGoalProgress $progress): JsonResponse
    {
        $goal = $this->familyPlan($request, $plan);
        $page = $goal->periods()
            ->where('status', GoalPeriod::STATUS_CLOSED)
            ->orderByDesc('starts_at')
            ->paginate(20);

        $periods = collect($page->items())->each(fn (GoalPeriod $period) => $period->setRelation('plan', $goal));
        $results = $progress->many($periods);

        return new JsonResponse([
            'data' => $periods->map(function (GoalPeriod $period) use ($goal, $progress, $results): array {
                $result = $results[$period->id];

                return [...$result, 'winner_member_ids' => $result['winner_member_ids'] ?? $progress->winners($goal, $result)];
            })->values()->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    private function familyPlan(Request $request, int $planId): GoalPlan
    {
        $member = $this->currentMember($request);

        /** @var GoalPlan $plan */
        $plan = GoalPlan::query()->where('family_id', $member->family_id)->findOrFail($planId);
        $plan->setRelation('family', $member->family);

        return $plan;
    }
}
