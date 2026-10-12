<?php

namespace App\Modules\Goals\Actions;

use App\Modules\Content\Actions\ResolveCategoryAccess;
use App\Modules\Content\Support\CategoryTree;
use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Support\FamilyClock;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a goal plan for the admin's family. Enforces the tier's
 * `limits.max_active_goals`, refuses locked categories, defaults members to the whole family.
 * The open period keeps its snapshotted targets; edits apply from the next period.
 */
class SaveGoalPlan
{
    public function __construct(
        private readonly PolicyResolver $policy,
        private readonly ResolveCategoryAccess $access,
        private readonly CategoryTree $tree,
        private readonly OpenGoalPeriod $open,
    ) {}

    /**
     * @param  array{name: string, mode: string, period_type: string, target_points: int, reward_text?: string|null, starts_on: string, ends_on?: string|null, category_ids: list<int>, members?: list<array{member_id: int, target_override?: int|null}>|null}  $data
     */
    public function create(FamilyMember $admin, array $data): GoalPlan
    {
        $family = $admin->family;

        $this->ensureUnderLimit($family);
        $this->ensureStartsOnNotPast($family, $data['starts_on']);
        $this->ensureCategoriesAllowed($admin, $data['category_ids']);
        $members = $this->resolveMembers($family, $data['members'] ?? null);

        return DB::transaction(function () use ($family, $data, $members): GoalPlan {
            $plan = GoalPlan::query()->create([
                'family_id' => $family->id,
                'name' => $data['name'],
                'mode' => $data['mode'],
                'period_type' => $data['period_type'],
                'target_points' => $data['target_points'],
                'reward_text' => $data['reward_text'] ?? null,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
                'is_active' => true,
            ]);
            $plan->setRelation('family', $family);
            $plan->categories()->sync($data['category_ids']);
            $plan->planMembers()->createMany($members);

            $this->open->handle($plan);

            return $plan;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated subset of the create fields plus `is_active`
     */
    public function update(GoalPlan $plan, FamilyMember $admin, array $data): GoalPlan
    {
        $family = $admin->family;

        if (($data['is_active'] ?? null) === true && ! $plan->is_active) {
            $this->ensureUnderLimit($family);
        }

        if (isset($data['starts_on']) && $data['starts_on'] !== $plan->starts_on->toDateString()) {
            $this->ensureStartsOnNotPast($family, (string) $data['starts_on']);
        }

        if (isset($data['category_ids'])) {
            /** @var list<int> $categoryIds */
            $categoryIds = $data['category_ids'];
            $this->ensureCategoriesAllowed($admin, $categoryIds);
        }

        $members = array_key_exists('members', $data) ? $this->resolveMembers($family, $data['members']) : null;

        return DB::transaction(function () use ($plan, $family, $data, $members): GoalPlan {
            $plan->fill(array_intersect_key($data, array_flip(['name', 'mode', 'period_type', 'target_points', 'reward_text', 'starts_on', 'ends_on', 'is_active'])))->save();
            $plan->setRelation('family', $family);

            if (isset($data['category_ids'])) {
                $plan->categories()->sync($data['category_ids']);
            }

            if ($members !== null) {
                $plan->planMembers()->delete();
                $plan->planMembers()->createMany($members);
                $plan->unsetRelation('planMembers');
            }

            $this->open->handle($plan);

            return $plan;
        });
    }

    /**
     * Pause: the plan stays listed with `is_active: false` (admin panel support action).
     */
    public function deactivate(GoalPlan $plan): void
    {
        $plan->forceFill(['is_active' => false])->save();
    }

    /**
     * `DELETE /goals/{plan}`: soft delete. The plan disappears from the API (404 afterwards) and no
     * longer counts toward the tier limit; its periods and history stay in the database.
     */
    public function delete(GoalPlan $plan): void
    {
        $plan->delete();
    }

    /**
     * Active = switched on and not past its end date (family timezone).
     */
    public static function activeCount(Family $family): int
    {
        $today = FamilyClock::today($family)->toDateString();

        return GoalPlan::query()
            ->where('family_id', $family->id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $today))
            ->count();
    }

    private function ensureUnderLimit(Family $family): void
    {
        $limit = $this->policy->maxActiveGoalsFor($family->tier);

        if ($limit !== null && self::activeCount($family) >= $limit) {
            throw ApiException::goalLimitReached();
        }
    }

    private function ensureStartsOnNotPast(Family $family, string $startsOn): void
    {
        if ($startsOn < FamilyClock::today($family)->toDateString()) {
            throw ApiException::validation('starts_on', 'The start date cannot be in the past.');
        }
    }

    /**
     * @param  list<int>  $categoryIds
     */
    private function ensureCategoriesAllowed(FamilyMember $admin, array $categoryIds): void
    {
        $locked = $this->access->lockedRootIds($admin);

        foreach ($categoryIds as $categoryId) {
            $root = $this->tree->rootOf((int) $categoryId);

            if ($root === null || in_array($root->id, $locked, true)) {
                throw ApiException::categoryNotAllowed();
            }
        }
    }

    /**
     * @param  list<array{member_id: int, target_override?: int|null}>|null  $members
     * @return list<array{member_id: int, target_override: int|null}>
     */
    private function resolveMembers(Family $family, ?array $members): array
    {
        $familyMemberIds = $family->members()->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($members === null || $members === []) {
            return array_map(fn (int $id) => ['member_id' => $id, 'target_override' => null], $familyMemberIds);
        }

        $resolved = [];

        foreach ($members as $member) {
            $memberId = (int) $member['member_id'];

            if (! in_array($memberId, $familyMemberIds, true)) {
                throw ApiException::validation('members', 'Every member must belong to your family.');
            }

            $resolved[$memberId] = ['member_id' => $memberId, 'target_override' => isset($member['target_override']) ? (int) $member['target_override'] : null];
        }

        return array_values($resolved);
    }
}
