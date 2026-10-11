<?php

namespace App\Modules\Goals\Http\Controllers;

use App\Modules\Core\Http\CurrentMember;
use App\Modules\Goals\Actions\ComputeGoalProgress;
use App\Modules\Goals\Actions\LoadGoalPlans;
use App\Modules\Goals\Actions\SaveGoalPlan;
use App\Modules\Goals\Http\Requests\StoreGoalRequest;
use App\Modules\Goals\Http\Requests\UpdateGoalRequest;
use App\Modules\Goals\Http\Resources\GoalPlanResource;
use App\Modules\Goals\Models\GoalPeriod;
use App\Modules\Goals\Models\GoalPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class GoalController
{
    use CurrentMember;

    public function index(Request $request, LoadGoalPlans $load): AnonymousResourceCollection
    {
        return GoalPlanResource::collection($load->forFamily($this->currentMember($request)->family));
    }

    public function show(Request $request, int $plan, LoadGoalPlans $load): GoalPlanResource
    {
        return new GoalPlanResource($load->prepare($this->familyPlan($request, $plan)));
    }

    public function store(StoreGoalRequest $request, SaveGoalPlan $save, LoadGoalPlans $load): JsonResponse
    {
        /** @var array{name: string, mode: string, period_type: string, target_points: int, reward_text?: string|null, starts_on: string, ends_on?: string|null, category_ids: list<int>, members?: list<array{member_id: int, target_override?: int|null}>|null} $data */
        $data = $request->validated();
        $member = $this->currentMember($request);
        $plan = $save->create($member, $data);

        return (new GoalPlanResource($load->prepare($plan->fresh() ?? $plan, $member->family)))->response()->setStatusCode(201);
    }

    public function update(UpdateGoalRequest $request, int $plan, SaveGoalPlan $save, LoadGoalPlans $load): GoalPlanResource
    {
        $member = $this->currentMember($request);
        $saved = $save->update($this->familyPlan($request, $plan), $member, $request->validated());

        return new GoalPlanResource($load->prepare($saved->fresh() ?? $saved, $member->family));
    }

    public function destroy(Request $request, int $plan, SaveGoalPlan $save): Response
    {
        $save->deactivate($this->familyPlan($request, $plan));

        return response()->noContent();
    }

    public function history(Request $request, int $plan, ComputeGoalProgress $progress): JsonResponse
    {
        $goal = $this->familyPlan($request, $plan);
        $page = $goal->periods()
            ->where('status', GoalPeriod::STATUS_CLOSED)
            ->orderByDesc('starts_at')
            ->paginate(20);

        return new JsonResponse([
            'data' => collect($page->items())->map(function (GoalPeriod $period) use ($goal, $progress): array {
                $period->setRelation('plan', $goal);
                $result = $progress->handle($period);

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
