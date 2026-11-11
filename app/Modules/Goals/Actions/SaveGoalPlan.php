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
 * `limits.max_active_goals`, refuses locked categories, defaults to `everyone` (the whole family;
 * newcomers join automatically, {@see JoinEveryoneGoalPlans}). `all_categories` plans have no scopes.
 * The open period keeps its snapshotted targets; edits apply from the next period.
 */
class SaveGoalPlan
{
    public function __construct(
        private readonly PolicyResolver $policy,
        private readonly ResolveCategoryAccess $access,
        private readonly CategoryTree $tree,
        private readonly OpenGoalPeriod $open,
        private readonly JoinEveryoneGoalPlans $join,
    ) {}

    /**
     * `everyone` defaults to true when `members` is absent (or empty); with `everyone` the team is
     * every active member and `members` only carries `target_override`s.
     *
     * @param  array{name: string, mode: string, period_type: string, target_points: int, reward_text?: string|null, starts_on: string, ends_on?: string|null, all_categories?: bool, category_ids?: list<int>|null, everyone?: bool, members?: list<array{member_id: int, target_override?: int|null}>|null}  $data
     */
    public function create(FamilyMember $admin, array $data): GoalPlan
    {
        $family = $admin->family;
        $allCategories = (bool) ($data['all_categories'] ?? false);
        $categoryIds = $allCategories ? [] : ($data['category_ids'] ?? []);
        $everyone = array_key_exists('everyone', $data) ? (bool) $data['everyone'] : empty($data['members']);

        if (! $allCategories && $categoryIds === []) {
            throw ApiException::validation('category_ids', 'Choose at least one category, or all categories.');
        }

        $this->ensureUnderLimit($family);
        $this->ensureStartsOnNotPast($family, $data['starts_on']);
        $this->ensureCategoriesAllowed($admin, $categoryIds);
        $members = $this->resolveMembers($family, $data['members'] ?? null, $everyone);

        return DB::transaction(function () use ($family, $data, $members, $allCategories, $categoryIds, $everyone): GoalPlan {
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
                'everyone' => $everyone,
                'all_categories' => $allCategories,
            ]);
            $plan->setRelation('family', $family);
            $plan->categories()->sync($categoryIds);
            $plan->planMembers()->createMany($members);

            $this->open->handle($plan);

            return $plan;
        });
    }

    /**
     * Changes apply from the next period, except switching `everyone` on: the missing active members
     * join the plan and its open period right away. With `everyone`, `members` (when sent) is the
     * full list of `target_override`s (members not listed get none). Sending `category_ids` alone
     * switches an all-categories plan back to scopes; turning `all_categories` off needs
     * `category_ids` when the plan has no scopes (422).
     *
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

        /** @var list<int> $categoryIds */
        $categoryIds = is_array($data['category_ids'] ?? null) ? array_values($data['category_ids']) : [];
        $allCategories = array_key_exists('all_categories', $data)
            ? (bool) $data['all_categories']
            : ($categoryIds !== [] ? false : $plan->all_categories);

        if ($allCategories) {
            $categoryIds = [];
        } elseif ($categoryIds !== []) {
            $this->ensureCategoriesAllowed($admin, $categoryIds);
        } elseif ($plan->all_categories) {
            throw ApiException::validation('category_ids', 'Choose at least one category, or all categories.');
        }

        $syncCategories = $allCategories !== $plan->all_categories || $categoryIds !== [];
        $everyone = array_key_exists('everyone', $data) ? (bool) $data['everyone'] : $plan->everyone;
        $joinsWholeFamily = $everyone && ! $plan->everyone;

        if (! $everyone && array_key_exists('members', $data) && empty($data['members'])) {
            throw ApiException::validation('members', 'Choose at least one member, or everyone.');
        }

        /** @var list<array{member_id: int, target_override?: int|null}>|null $requestedMembers */
        $requestedMembers = $data['members'] ?? null;
        $members = array_key_exists('members', $data) ? $this->resolveMembers($family, $requestedMembers, $everyone) : null;

        return DB::transaction(function () use ($plan, $family, $data, $members, $allCategories, $categoryIds, $syncCategories, $everyone, $joinsWholeFamily): GoalPlan {
            $plan->fill([
                ...array_intersect_key($data, array_flip(['name', 'mode', 'period_type', 'target_points', 'reward_text', 'starts_on', 'ends_on', 'is_active'])),
                'everyone' => $everyone,
                'all_categories' => $allCategories,
            ])->save();
            $plan->setRelation('family', $family);

            if ($syncCategories) {
                $plan->categories()->sync($categoryIds);
                $plan->unsetRelation('categories');
            }

            if ($members !== null) {
                $plan->planMembers()->delete();
                $plan->planMembers()->createMany($members);
                $plan->unsetRelation('planMembers');
            }

            if ($joinsWholeFamily) {
                $this->join->wholeFamily($plan);
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
        if ($categoryIds === []) {
            return;
        }

        $locked = $this->access->lockedRootIds($admin);

        foreach ($categoryIds as $categoryId) {
            $root = $this->tree->rootOf((int) $categoryId);

            if ($root === null || in_array($root->id, $locked, true)) {
                throw ApiException::categoryNotAllowed();
            }
        }
    }

    /**
     * The plan's member rows. With `everyone` (or no list) the team is every active member and
     * `$members` only sets overrides; otherwise it is the hand-picked list. Every listed member must
     * be an active member of the family (422).
     *
     * @param  list<array{member_id: int, target_override?: int|null}>|null  $members
     * @return list<array{member_id: int, target_override: int|null}>
     */
    private function resolveMembers(Family $family, ?array $members, bool $everyone): array
    {
        $familyMemberIds = $family->members()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $resolved = [];

        foreach ($members ?? [] as $member) {
            $memberId = (int) $member['member_id'];

            if (! in_array($memberId, $familyMemberIds, true)) {
                throw ApiException::validation('members', 'Every member must belong to your family.');
            }

            $resolved[$memberId] = ['member_id' => $memberId, 'target_override' => isset($member['target_override']) ? (int) $member['target_override'] : null];
        }

        if ($everyone || $resolved === []) {
            return array_map(fn (int $id) => $resolved[$id] ?? ['member_id' => $id, 'target_override' => null], $familyMemberIds);
        }

        return array_values($resolved);
    }
}
