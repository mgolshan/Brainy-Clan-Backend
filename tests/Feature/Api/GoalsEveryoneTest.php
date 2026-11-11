<?php

use App\Modules\Content\Models\Category;
use App\Modules\Family\Actions\RemoveMember;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\Invite;
use App\Modules\Goals\Models\GoalPeriod;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Goals\Models\GoalPlanMember;
use App\Modules\Scoring\Actions\AppendPoints;
use App\Modules\Scoring\Models\PointEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Goals that explain themselves (docs/API.md "Goals", 2026-11-11): `everyone` plans (the whole
 * family; newcomers join the plan and its open period) and `all_categories` plans (any category
 * counts). "Now" is Wednesday 2026-10-14 12:00 in Toronto (16:00 UTC); the week runs Mon 12 to Mon 19.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-14 16:00:00');
});

afterEach(fn () => Carbon::setTestNow());

function everyonePoints(FamilyMember $member, Category $category, int $amount, string $at): void
{
    $now = Carbon::now();
    Carbon::setTestNow($at);
    app(AppendPoints::class)->handle($member->family_id, $member->id, $category->id, $amount, PointEntry::REASON_CORRECT_ANSWER);
    Carbon::setTestNow($now);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function everyoneBody(?Category $category, array $overrides = []): array
{
    return [
        'name' => 'Pizza Friday',
        'mode' => 'cooperation',
        'period_type' => 'weekly',
        'target_points' => 20,
        'starts_on' => '2026-10-14',
        ...($category !== null ? ['category_ids' => [$category->id]] : []),
        ...$overrides,
    ];
}

/**
 * Joins the family with an invite through the API (a 9-year-old) and returns the new member.
 */
function joinWithInvite(FamilyMember $owner, string $nickname = 'Jordan'): FamilyMember
{
    $invite = Invite::factory()->create(['family_id' => $owner->family_id, 'created_by' => $owner->user_id, 'nickname' => $nickname]);

    test()->postJson('/api/v1/auth/join', ['code' => $invite->code, 'device_name' => 'Tablet', 'platform' => 'ios'])->assertCreated();
    app('auth')->forgetGuards();

    return FamilyMember::query()->where('family_id', $owner->family_id)->where('nickname', $nickname)->sole();
}

/**
 * @return array<int, int> member id => target in the plan's open period
 */
function openTargets(int $planId): array
{
    return GoalPeriod::query()->where('goal_plan_id', $planId)->where('status', GoalPeriod::STATUS_OPEN)->sole()->member_targets;
}

// ---------------------------------------------------------------------------------------------
// Creating and editing
// ---------------------------------------------------------------------------------------------

it('creates an everyone plan by default (members absent) and a hand-picked one with members', function () {
    $owner = familyOwner(['tier' => 'family']);
    $sam = addMember($owner->family, 9);
    $category = Category::factory()->create();
    actingAsMember($owner);

    $this->postJson('/api/v1/goals', everyoneBody($category))
        ->assertCreated()
        ->assertJsonPath('data.everyone', true)
        ->assertJsonPath('data.all_categories', false)
        ->assertJsonCount(2, 'data.members');

    $this->postJson('/api/v1/goals', everyoneBody($category, ['members' => [['member_id' => $sam->id]]]))
        ->assertCreated()
        ->assertJsonPath('data.everyone', false)
        ->assertJsonCount(1, 'data.members')
        ->assertJsonPath('data.members.0.member_id', $sam->id);
});

it('uses members only for target overrides on an everyone plan', function () {
    $owner = familyOwner(['tier' => 'family']);
    $sam = addMember($owner->family, 9);
    $dad = addMember($owner->family, 40);
    $category = Category::factory()->create();
    actingAsMember($owner);

    $planId = $this->postJson('/api/v1/goals', everyoneBody($category, [
        'everyone' => true,
        'members' => [['member_id' => $sam->id, 'target_override' => 5]],
    ]))->assertCreated()
        ->assertJsonPath('data.everyone', true)
        ->assertJsonCount(3, 'data.members')
        ->json('data.id');

    expect(openTargets($planId))->toBe([$owner->id => 20, $sam->id => 5, $dad->id => 20]);
});

it('validates everyone, members and all_categories', function () {
    $owner = familyOwner(['tier' => 'family']);
    $category = Category::factory()->create();
    $other = familyOwner();
    actingAsMember($owner);

    // A hand-picked team needs at least one member.
    $this->postJson('/api/v1/goals', everyoneBody($category, ['everyone' => false]))
        ->assertUnprocessable()->assertJsonValidationErrors('members');
    $this->postJson('/api/v1/goals', everyoneBody($category, ['everyone' => false, 'members' => []]))
        ->assertUnprocessable()->assertJsonValidationErrors('members');
    // Overrides on an everyone plan must still be family members.
    $this->postJson('/api/v1/goals', everyoneBody($category, ['everyone' => true, 'members' => [['member_id' => $other->id, 'target_override' => 3]]]))
        ->assertUnprocessable()->assertJsonValidationErrors('members');
    // Categories: required unless all_categories, and then absent or empty.
    $this->postJson('/api/v1/goals', everyoneBody(null))
        ->assertUnprocessable()->assertJsonValidationErrors('category_ids');
    $this->postJson('/api/v1/goals', everyoneBody($category, ['all_categories' => true]))
        ->assertUnprocessable()->assertJsonValidationErrors('category_ids');
    $this->postJson('/api/v1/goals', everyoneBody(null, ['everyone' => 'yes']))
        ->assertUnprocessable()->assertJsonValidationErrors(['everyone', 'category_ids']);

    expect(GoalPlan::query()->count())->toBe(0);

    $this->postJson('/api/v1/goals', everyoneBody(null, ['all_categories' => true, 'category_ids' => []]))
        ->assertCreated()
        ->assertJsonPath('data.all_categories', true)
        ->assertJsonPath('data.scopes', []);
});

it('switches all_categories on and off with PATCH', function () {
    $owner = familyOwner(['tier' => 'family']);
    $category = Category::factory()->create();
    $other = Category::factory()->create();
    actingAsMember($owner);
    $planId = $this->postJson('/api/v1/goals', everyoneBody($category))->json('data.id');

    $this->patchJson("/api/v1/goals/{$planId}", ['all_categories' => true, 'category_ids' => [$other->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('category_ids');
    $this->patchJson("/api/v1/goals/{$planId}", ['all_categories' => true])
        ->assertOk()
        ->assertJsonPath('data.all_categories', true)
        ->assertJsonPath('data.scopes', []);
    // Turning it off needs categories again.
    $this->patchJson("/api/v1/goals/{$planId}", ['all_categories' => false])
        ->assertUnprocessable()->assertJsonValidationErrors('category_ids');
    // Sending categories alone goes back to scopes.
    $this->patchJson("/api/v1/goals/{$planId}", ['category_ids' => [$other->id]])
        ->assertOk()
        ->assertJsonPath('data.all_categories', false)
        ->assertJsonPath('data.scopes.0.category_id', $other->id);
});

it('adds the missing members to the plan and the open period when switching everyone on', function () {
    $owner = familyOwner(['tier' => 'family']);
    $sam = addMember($owner->family, 9);
    $dad = addMember($owner->family, 40);
    $category = Category::factory()->create();
    actingAsMember($owner);
    $planId = $this->postJson('/api/v1/goals', everyoneBody($category, [
        'members' => [['member_id' => $sam->id, 'target_override' => 5]],
    ]))->assertJsonPath('data.everyone', false)->json('data.id');

    expect(openTargets($planId))->toBe([$sam->id => 5]);

    $this->patchJson("/api/v1/goals/{$planId}", ['everyone' => true])
        ->assertOk()
        ->assertJsonPath('data.everyone', true)
        ->assertJsonCount(3, 'data.members')
        ->assertJsonCount(3, 'data.current_period.entries');

    // Sam keeps his override and his snapshotted target; the others get the period's target.
    expect(openTargets($planId))->toBe([$sam->id => 5, $owner->id => 20, $dad->id => 20])
        ->and(GoalPlanMember::query()->where('member_id', $sam->id)->value('target_override'))->toBe(5);

    // Back to hand-picked: the team stays as it is until members are sent.
    $this->patchJson("/api/v1/goals/{$planId}", ['everyone' => false])
        ->assertOk()->assertJsonPath('data.everyone', false)->assertJsonCount(3, 'data.members');
    $this->patchJson("/api/v1/goals/{$planId}", ['members' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('members');
});

// ---------------------------------------------------------------------------------------------
// Newcomers
// ---------------------------------------------------------------------------------------------

it('adds a newcomer to every everyone plan and its open period, and counts their points', function () {
    $owner = familyOwner(['tier' => 'family']);
    $owner->update(['nickname' => 'Mom']);
    $geo = Category::factory()->create();
    $math = Category::factory()->create();
    actingAsMember($owner);
    $everyoneId = $this->postJson('/api/v1/goals', everyoneBody($geo, ['target_points' => 30]))->json('data.id');
    $pickedId = $this->postJson('/api/v1/goals', everyoneBody($geo, ['members' => [['member_id' => $owner->id]]]))->json('data.id');
    $deleted = $this->postJson('/api/v1/goals', everyoneBody($geo, ['name' => 'Gone']))->json('data.id');
    $this->deleteJson("/api/v1/goals/{$deleted}")->assertNoContent();
    app('auth')->forgetGuards();

    // Points before joining can not exist; points after joining count.
    $jordan = joinWithInvite($owner);
    everyonePoints($jordan, $geo, 7, '2026-10-14 17:00:00');
    everyonePoints($jordan, $math, 9, '2026-10-14 17:00:00');

    expect(openTargets($everyoneId))->toBe([$owner->id => 30, $jordan->id => 30])
        ->and(openTargets($pickedId))->toBe([$owner->id => 20])
        ->and(GoalPlanMember::query()->where('member_id', $jordan->id)->pluck('goal_plan_id')->all())->toBe([$everyoneId]);

    actingAsMember($owner);
    $this->getJson("/api/v1/goals/{$everyoneId}")
        ->assertOk()
        ->assertJsonCount(2, 'data.members')
        ->assertJsonPath('data.members.1.member_id', $jordan->id)
        ->assertJsonPath('data.members.1.target_override', null)
        ->assertJsonPath('data.current_period.team_total', 7)
        ->assertJsonPath('data.current_period.entries.0.member_id', $jordan->id)
        ->assertJsonPath('data.current_period.entries.0.points', 7)
        ->assertJsonPath('data.current_period.entries.0.target', 30);
    $this->getJson("/api/v1/goals/{$pickedId}")->assertJsonCount(1, 'data.members');
});

it('adds a member who re-joins after a removal again, as a new membership', function () {
    $owner = familyOwner(['tier' => 'family']);
    $category = Category::factory()->create();
    actingAsMember($owner);
    $planId = $this->postJson('/api/v1/goals', everyoneBody($category))->json('data.id');
    app('auth')->forgetGuards();

    $first = joinWithInvite($owner, 'Sam');
    app(RemoveMember::class)->handle($first);
    expect(openTargets($planId))->toBe([$owner->id => 20]);

    $again = joinWithInvite($owner, 'Sam');
    expect($again->id)->not->toBe($first->id)
        ->and(openTargets($planId))->toBe([$owner->id => 20, $again->id => 20]);
});

it('adds the newcomer to a paused everyone plan so they are on the team when it resumes', function () {
    $owner = familyOwner(['tier' => 'family']);
    $category = Category::factory()->create();
    $plan = GoalPlan::factory()->everyone()->inactive()->create(['family_id' => $owner->family_id]);
    $plan->categories()->attach($category);
    $plan->planMembers()->create(['member_id' => $owner->id]);

    $jordan = joinWithInvite($owner);

    expect($plan->planMembers()->pluck('member_id')->all())->toBe([$owner->id, $jordan->id])
        ->and(GoalPeriod::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------------------------
// all_categories progress
// ---------------------------------------------------------------------------------------------

it('counts points in any category for an all_categories plan, scopes still filter the others', function () {
    $owner = familyOwner(['tier' => 'family']);
    $sam = addMember($owner->family, 9);
    $geo = Category::factory()->create();
    $math = Category::factory()->create();
    $capitals = Category::factory()->childOf($geo)->create();
    actingAsMember($owner);
    $allId = $this->postJson('/api/v1/goals', everyoneBody(null, ['all_categories' => true]))->json('data.id');
    $geoId = $this->postJson('/api/v1/goals', everyoneBody($geo))->json('data.id');

    everyonePoints($sam, $capitals, 6, '2026-10-14 13:00:00');
    everyonePoints($owner, $math, 4, '2026-10-14 15:00:00');
    everyonePoints($sam, $math, 9, '2026-10-14 03:00:00'); // before the plan started

    $plans = collect($this->getJson('/api/v1/goals')->assertOk()->json('data'))->keyBy('id');

    expect($plans[$allId]['current_period']['team_total'])->toBe(10)
        ->and($plans[$allId]['scopes'])->toBe([])
        ->and($plans[$geoId]['current_period']['team_total'])->toBe(6);
});

it('lists all_categories and scoped plans with a constant number of queries', function () {
    $count = function (int $plans): int {
        $owner = familyOwner(['tier' => 'family']);
        $category = Category::factory()->create();

        for ($i = 0; $i < $plans; $i++) {
            $plan = GoalPlan::factory()->everyone()->create(['family_id' => $owner->family_id, 'all_categories' => $i % 2 === 0]);

            if ($i % 2 === 1) {
                $plan->categories()->attach($category);
            }

            $plan->planMembers()->create(['member_id' => $owner->id]);
        }

        actingAsMember($owner);
        $this->getJson('/api/v1/goals')->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/goals')->assertOk()->assertJsonCount($plans, 'data');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        app('auth')->forgetGuards();

        return $queries;
    };

    expect($count(6))->toBe($count(2));
});

// ---------------------------------------------------------------------------------------------
// Migration backfill
// ---------------------------------------------------------------------------------------------

it('backfills existing plans: everyone on, missing active members added to plan and open period', function () {
    $owner = familyOwner(['tier' => 'family']);
    $sam = addMember($owner->family, 9);
    $removed = addMember($owner->family, 40);
    $removed->forceFill(['status' => FamilyMember::STATUS_REMOVED])->save();
    $jordan = addMember($owner->family, 12);
    $category = Category::factory()->create();
    actingAsMember($owner);
    $planId = $this->postJson('/api/v1/goals', everyoneBody($category, [
        'members' => [['member_id' => $owner->id], ['member_id' => $sam->id, 'target_override' => 5]],
    ]))->json('data.id');
    $other = GoalPlan::factory()->create();

    $migration = require database_path('migrations/2026_11_11_100000_add_everyone_and_all_categories_to_goal_plans.php');
    $migration->backfill();
    $migration->backfill(); // idempotent

    expect(GoalPlan::query()->whereKey([$planId, $other->id])->pluck('everyone')->all())->toBe([true, true])
        ->and(GoalPlanMember::query()->where('goal_plan_id', $planId)->orderBy('member_id')->pluck('target_override', 'member_id')->all())
        ->toBe([$owner->id => null, $sam->id => 5, $jordan->id => null])
        ->and(openTargets($planId))->toBe([$owner->id => 20, $sam->id => 5, $jordan->id => 20]);
});
