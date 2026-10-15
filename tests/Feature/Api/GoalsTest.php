<?php

use App\Modules\Content\Models\Category;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Goals\Actions\CloseGoalPeriods;
use App\Modules\Goals\Events\GoalReached;
use App\Modules\Goals\Events\PeriodClosed;
use App\Modules\Goals\Models\GoalPeriod;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Scoring\Actions\AppendPoints;
use App\Modules\Scoring\Models\PointEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

/**
 * "Now" is Wednesday 2026-10-14 12:00 in Toronto (16:00 UTC); the week runs Mon 12 to Mon 19.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-14 16:00:00');
});

afterEach(fn () => Carbon::setTestNow());

function goalPoints(FamilyMember $member, Category $category, int $amount, string $at): void
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
function goalBody(Category $category, array $overrides = []): array
{
    return [
        'name' => 'Pizza Friday',
        'mode' => 'cooperation',
        'period_type' => 'weekly',
        'target_points' => 20,
        'reward_text' => 'Pizza night!',
        'starts_on' => '2026-10-14',
        'category_ids' => [$category->id],
        ...$overrides,
    ];
}

it('creates a plan with all members by default and opens the current period', function () {
    $owner = familyOwner(['tier' => 'family']);
    $sam = addMember($owner->family, 9);
    $category = Category::factory()->create();
    actingAsMember($owner);

    $this->postJson('/api/v1/goals', goalBody($category))
        ->assertCreated()
        ->assertJsonPath('data.name', 'Pizza Friday')
        ->assertJsonPath('data.mode', 'cooperation')
        ->assertJsonPath('data.starts_on', '2026-10-14')
        ->assertJsonPath('data.ends_on', null)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.scopes', [['category_id' => $category->id, 'name' => $category->localizedName()]])
        ->assertJsonCount(2, 'data.members')
        ->assertJsonPath('data.current_period.status', 'open')
        // Plan starts Wednesday: the first week is clipped to Wed 00:00 local.
        ->assertJsonPath('data.current_period.starts_at', '2026-10-14T04:00:00Z')
        ->assertJsonPath('data.current_period.ends_at', '2026-10-19T04:00:00Z')
        ->assertJsonPath('data.current_period.team_total', 0)
        ->assertJsonPath('data.current_period.target_points', 20)
        ->assertJsonPath('data.current_period.reached', false);

    expect(GoalPeriod::query()->sole()->member_targets)->toBe([(string) $owner->id => 20, (string) $sam->id => 20]);
});

it('validates and protects plan creation', function () {
    $owner = familyOwner(['tier' => 'family']);
    $category = Category::factory()->create();
    $other = familyOwner();
    actingAsMember($owner);

    $this->postJson('/api/v1/goals', [])->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'mode', 'period_type', 'target_points', 'starts_on', 'category_ids']);
    $this->postJson('/api/v1/goals', goalBody($category, ['starts_on' => '2026-10-13']))
        ->assertUnprocessable()->assertJsonValidationErrors('starts_on');
    $this->postJson('/api/v1/goals', goalBody($category, ['members' => [['member_id' => $other->id]]]))
        ->assertUnprocessable()->assertJsonValidationErrors('members');

    actingAsMember(addMember($owner->family, 9));
    $this->postJson('/api/v1/goals', goalBody($category))->assertForbidden();
});

it('enforces the free tier goal limit and locked categories', function () {
    $roots = Category::factory()->count(4)->sequence(fn ($s) => ['sort' => $s->index])->create();
    $owner = familyOwner(['tier' => 'free']);
    actingAsMember($owner);

    $this->postJson('/api/v1/goals', goalBody($roots[3]))->assertForbidden()->assertJsonPath('code', 'category_not_allowed');
    $this->postJson('/api/v1/goals', goalBody($roots[0]))->assertCreated();
    $this->postJson('/api/v1/goals', goalBody($roots[1]))->assertForbidden()->assertJsonPath('code', 'goal_limit_reached');

    // Pausing frees the slot; reactivating while another plan is active is refused.
    $first = GoalPlan::query()->firstOrFail();
    $this->patchJson("/api/v1/goals/{$first->id}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
    expect($first->fresh()->is_active)->toBeFalse();
    $this->postJson('/api/v1/goals', goalBody($roots[1]))->assertCreated();
    $this->patchJson("/api/v1/goals/{$first->id}", ['is_active' => true])->assertForbidden()->assertJsonPath('code', 'goal_limit_reached');
});

it('computes cooperation progress from the ledger: window, scope subtree and members only', function () {
    $owner = familyOwner(['tier' => 'family']);
    $owner->update(['nickname' => 'Mom']);
    $sam = addMember($owner->family, 9);
    $sam->update(['nickname' => 'Sam']);
    $dad = addMember($owner->family, 40);
    $geo = Category::factory()->create();
    $capitals = Category::factory()->childOf($geo)->create();
    $math = Category::factory()->create();
    actingAsMember($owner);

    $planId = $this->postJson('/api/v1/goals', goalBody($geo, [
        'members' => [['member_id' => $owner->id], ['member_id' => $sam->id, 'target_override' => 5]],
    ]))->assertCreated()->json('data.id');

    goalPoints($sam, $capitals, 6, '2026-10-14 13:00:00');   // in scope (subtree), in window
    goalPoints($owner, $geo, 4, '2026-10-14 15:00:00');      // in scope
    goalPoints($owner, $math, 9, '2026-10-14 15:00:00');     // other category
    goalPoints($dad, $geo, 9, '2026-10-14 15:00:00');        // not a plan member
    goalPoints($sam, $geo, 9, '2026-10-14 03:00:00');        // before the plan started (Tue local)

    $this->getJson("/api/v1/goals/{$planId}")
        ->assertOk()
        ->assertJsonPath('data.members.1.target_override', 5)
        ->assertJsonPath('data.current_period.team_total', 10)
        ->assertJsonPath('data.current_period.reached', false)
        ->assertJsonPath('data.current_period.entries', [
            ['member_id' => $sam->id, 'nickname' => 'Sam', 'avatar' => $sam->user->avatar, 'points' => 6, 'target' => 5, 'reached' => true, 'rank' => 1],
            ['member_id' => $owner->id, 'nickname' => 'Mom', 'avatar' => $owner->user->avatar, 'points' => 4, 'target' => 20, 'reached' => false, 'rank' => 2],
        ]);
});

it('lists active plans first and hides other families', function () {
    $owner = familyOwner(['tier' => 'family']);
    $category = Category::factory()->create();
    $inactive = GoalPlan::factory()->inactive()->create(['family_id' => $owner->family_id]);
    $active = GoalPlan::factory()->create(['family_id' => $owner->family_id]);
    $active->categories()->attach($category);
    $foreign = GoalPlan::factory()->create();

    actingAsMember(addMember($owner->family, 9));

    $this->getJson('/api/v1/goals')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $active->id)
        ->assertJsonPath('data.0.current_period.status', 'open')
        ->assertJsonPath('data.1.id', $inactive->id)
        ->assertJsonPath('data.1.current_period', null);

    $this->getJson("/api/v1/goals/{$foreign->id}")->assertNotFound();
});

it('has a current period late in the evening, when UTC is already the next day', function () {
    // 21:40 in Toronto = 01:40 UTC on Thursday the 15th; the family's "today" is still Wednesday.
    Carbon::setTestNow(Carbon::parse('2026-10-14 21:40', 'America/Toronto'));
    $owner = familyOwner(['tier' => 'family']);
    $category = Category::factory()->create();
    $factoryPlan = GoalPlan::factory()->create(['family_id' => $owner->family_id]);
    $factoryPlan->categories()->attach($category);
    actingAsMember($owner);

    expect($factoryPlan->starts_on->toDateString())->toBe('2026-10-14');

    $this->postJson('/api/v1/goals', goalBody($category, ['name' => 'Evening plan']))
        ->assertCreated()
        ->assertJsonPath('data.current_period.starts_at', '2026-10-14T04:00:00Z')
        ->assertJsonPath('data.current_period.ends_at', '2026-10-19T04:00:00Z');

    $this->getJson('/api/v1/goals')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.current_period.status', 'open')
        ->assertJsonPath('data.1.current_period.status', 'open')
        ->assertJsonPath('data.1.current_period.starts_at', '2026-10-14T04:00:00Z');
});

it('applies edits from the next period only', function () {
    $owner = familyOwner(['tier' => 'family']);
    $category = Category::factory()->create();
    actingAsMember($owner);
    $planId = $this->postJson('/api/v1/goals', goalBody($category))->json('data.id');

    $this->patchJson("/api/v1/goals/{$planId}", ['target_points' => 99, 'name' => 'Movie night'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Movie night')
        ->assertJsonPath('data.target_points', 99)
        ->assertJsonPath('data.current_period.target_points', 20);

    Carbon::setTestNow('2026-10-19 05:00:00');
    app(CloseGoalPeriods::class)->handle();

    $this->getJson("/api/v1/goals/{$planId}")->assertJsonPath('data.current_period.target_points', 99);
});

it('closes ended periods, writes results, opens the next and serves history', function () {
    Event::fake([PeriodClosed::class, GoalReached::class]);
    $owner = familyOwner(['tier' => 'family']);
    $sam = addMember($owner->family, 9);
    $category = Category::factory()->create();
    actingAsMember($owner);
    $planId = $this->postJson('/api/v1/goals', goalBody($category, ['mode' => 'competition', 'period_type' => 'daily', 'target_points' => 3]))->json('data.id');

    goalPoints($sam, $category, 4, '2026-10-14 17:00:00');
    goalPoints($owner, $category, 2, '2026-10-14 18:00:00');

    // The scheduler was down for two days: it catches up day by day.
    expect(app(CloseGoalPeriods::class)->handle(Carbon::parse('2026-10-16 05:00:00')->toImmutable()))->toBe(2);

    $periods = GoalPeriod::query()->orderBy('starts_at')->get();
    expect($periods)->toHaveCount(3)
        ->and($periods->pluck('status')->all())->toBe(['closed', 'closed', 'open'])
        ->and($periods[0]->result['winner_member_ids'])->toBe([$sam->id])
        ->and($periods[0]->result['reached'])->toBeTrue()
        ->and($periods[1]->result['winner_member_ids'])->toBe([]);

    Event::assertDispatchedTimes(PeriodClosed::class, 2);
    Event::assertDispatchedTimes(GoalReached::class, 1);
    Event::assertDispatched(GoalReached::class, fn (GoalReached $e) => $e->winnerMemberIds === [$sam->id]);

    // Ledger rows written after closing do not change the stored result.
    goalPoints($owner, $category, 50, '2026-10-14 20:00:00');

    $this->getJson("/api/v1/goals/{$planId}/history")
        ->assertOk()
        ->assertJsonPath('meta', ['current_page' => 1, 'last_page' => 1])
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.starts_at', '2026-10-15T04:00:00Z')
        ->assertJsonPath('data.1.status', 'closed')
        ->assertJsonPath('data.1.team_total', 6)
        ->assertJsonPath('data.1.winner_member_ids', [$sam->id])
        ->assertJsonPath('data.1.entries.0.member_id', $sam->id);
});

it('cooperation winners are everyone when reached and nobody otherwise', function () {
    $owner = familyOwner(['tier' => 'family']);
    $sam = addMember($owner->family, 9);
    $category = Category::factory()->create();
    actingAsMember($owner);
    $this->postJson('/api/v1/goals', goalBody($category, ['period_type' => 'daily', 'target_points' => 5]))->assertCreated();

    goalPoints($sam, $category, 3, '2026-10-14 17:00:00');
    goalPoints($owner, $category, 2, '2026-10-14 18:00:00');
    app(CloseGoalPeriods::class)->handle(Carbon::parse('2026-10-15 05:00:00')->toImmutable());

    $result = GoalPeriod::query()->where('status', 'closed')->sole()->result;
    expect($result['reached'])->toBeTrue()
        ->and($result['team_total'])->toBe(5)
        ->and($result['winner_member_ids'])->toEqualCanonicalizing([$owner->id, $sam->id]);
});

it('stops opening periods after ends_on', function () {
    $owner = familyOwner(['tier' => 'family']);
    $category = Category::factory()->create();
    actingAsMember($owner);
    $this->postJson('/api/v1/goals', goalBody($category, ['period_type' => 'weekly', 'ends_on' => '2026-10-15']))
        ->assertCreated()
        ->assertJsonPath('data.current_period.ends_at', '2026-10-16T04:00:00Z');

    app(CloseGoalPeriods::class)->handle(Carbon::parse('2026-10-20 05:00:00')->toImmutable());

    expect(GoalPeriod::query()->count())->toBe(1)
        ->and(GoalPeriod::query()->sole()->status)->toBe('closed');
});

it('soft deletes a plan: hidden from the API, history kept, slot freed; pausing stays listed', function () {
    $roots = Category::factory()->count(2)->sequence(fn ($s) => ['sort' => $s->index])->create();
    $owner = familyOwner(['tier' => 'free']);
    actingAsMember($owner);

    $paused = $this->postJson('/api/v1/goals', goalBody($roots[0], ['name' => 'Paused']))->assertCreated()->json('data.id');
    $this->patchJson("/api/v1/goals/{$paused}", ['is_active' => false])->assertOk();
    $deleted = $this->postJson('/api/v1/goals', goalBody($roots[1], ['name' => 'Deleted']))->assertCreated()->json('data.id');

    $this->deleteJson("/api/v1/goals/{$deleted}")->assertNoContent();

    $this->getJson('/api/v1/goals')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Paused')->assertJsonPath('data.0.is_active', false);
    $this->getJson("/api/v1/goals/{$deleted}")->assertNotFound()->assertJsonPath('code', 'not_found');
    $this->getJson("/api/v1/goals/{$deleted}/history")->assertNotFound();
    $this->patchJson("/api/v1/goals/{$deleted}", ['name' => 'Back'])->assertNotFound();
    $this->deleteJson("/api/v1/goals/{$deleted}")->assertNotFound();

    $plan = GoalPlan::withTrashed()->findOrFail($deleted);
    expect($plan->trashed())->toBeTrue()
        ->and($plan->periods()->count())->toBe(1)
        ->and(GoalPlan::query()->count())->toBe(1);

    // The deleted plan no longer counts toward the free tier's single active plan.
    $this->postJson('/api/v1/goals', goalBody($roots[1], ['name' => 'New']))->assertCreated();
});

it('skips deleted plans when closing periods', function () {
    $owner = familyOwner(['tier' => 'family']);
    $category = Category::factory()->create();
    actingAsMember($owner);
    $id = $this->postJson('/api/v1/goals', goalBody($category, ['period_type' => 'daily']))->assertCreated()->json('data.id');
    $this->deleteJson("/api/v1/goals/{$id}")->assertNoContent();

    Carbon::setTestNow('2026-10-20 16:00:00');
    Event::fake([PeriodClosed::class, GoalReached::class]);

    expect(app(CloseGoalPeriods::class)->handle())->toBe(0);

    $periods = GoalPeriod::query()->where('goal_plan_id', $id)->get();
    expect($periods)->toHaveCount(1)->and($periods->first()->status)->toBe(GoalPeriod::STATUS_OPEN);
    Event::assertNotDispatched(PeriodClosed::class);
});
