<?php

use App\Modules\ChallengeTypes\Handlers\Sudoku;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\Invite;
use App\Modules\Goals\Models\GoalPeriod;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\SocialAccount;
use App\Modules\Play\Models\PlaySession;
use App\Modules\Play\Models\SessionItem;
use App\Modules\Scoring\Actions\AppendPoints;
use App\Modules\Scoring\Models\PointEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * No N+1 (docs/PLAN.md): every list endpoint runs the same number of queries for 2 and for 6
 * rows. Lazy loading throws outside production, so a missing eager load also fails here.
 */

/**
 * Queries run by one GET as this member (after a warm-up call, so one-off cache fills of the
 * policy snapshot or category tree are not counted).
 */
function qcQueries(FamilyMember $member, string $url): int
{
    app('auth')->forgetGuards();
    actingAsMember($member);
    test()->getJson($url)->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->getJson($url)->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

/**
 * A family-tier family with `$rows` extra members, each with ledger points and a device.
 */
function qcFamily(int $rows, ?Category $category = null): FamilyMember
{
    $owner = familyOwner(['tier' => 'family']);
    $category ??= Category::factory()->create();

    for ($i = 0; $i < $rows; $i++) {
        $member = addMember($owner->family, $i % 2 === 0 ? 9 : 30);
        Device::factory()->create(['user_id' => $member->user_id]);
        app(AppendPoints::class)->handle($owner->family_id, $member->id, $category->id, $i + 1, PointEntry::REASON_CORRECT_ANSWER);
    }

    return $owner;
}

it('lists the family with a constant number of queries', function () {
    expect(qcQueries(qcFamily(2), '/api/v1/family'))->toBe(qcQueries(qcFamily(6), '/api/v1/family'));
});

it('lists invites with a constant number of queries', function () {
    $small = familyOwner(['tier' => 'family']);
    $large = familyOwner(['tier' => 'family']);
    Invite::factory()->count(2)->create(['family_id' => $small->family_id]);
    Invite::factory()->count(6)->create(['family_id' => $large->family_id]);

    expect(qcQueries($small, '/api/v1/family/invites'))->toBe(qcQueries($large, '/api/v1/family/invites'));
});

it('lists member devices with a constant number of queries', function () {
    $small = familyOwner();
    $large = familyOwner();
    Device::factory()->count(2)->create(['user_id' => $small->user_id]);
    Device::factory()->count(6)->create(['user_id' => $large->user_id]);

    expect(qcQueries($small, "/api/v1/family/members/{$small->id}/devices"))
        ->toBe(qcQueries($large, "/api/v1/family/members/{$large->id}/devices"));
});

it('builds the leaderboard with a constant number of queries', function () {
    expect(qcQueries(qcFamily(2), '/api/v1/family/leaderboard?period=all'))
        ->toBe(qcQueries(qcFamily(6), '/api/v1/family/leaderboard?period=all'));
});

it('builds the category tree with a constant number of queries', function () {
    $owner = familyOwner(['tier' => 'family']);
    $roots = Category::factory()->count(2)->create();
    ContentItem::factory()->create(['category_id' => $roots[0]->id]);
    $before = qcQueries($owner, '/api/v1/categories');

    foreach (Category::factory()->count(4)->create() as $category) {
        Category::factory()->create(['parent_id' => $category->id]);
        ContentItem::factory()->create(['category_id' => $category->id]);
    }

    expect(qcQueries($owner, '/api/v1/categories'))->toBe($before);
});

it('lists goal plans with progress with a constant number of queries', function () {
    // Late evening in Toronto: UTC is already the next day (regression: plans had no current period).
    $this->travelTo(CarbonImmutable::parse('2026-10-14 21:40', 'America/Toronto'));

    $category = Category::factory()->create();
    $small = qcFamily(2, $category);
    $large = qcFamily(6, $category);

    foreach ([[$small, 2], [$large, 6]] as [$owner, $plans]) {
        for ($i = 0; $i < $plans; $i++) {
            $plan = GoalPlan::factory()->create(['family_id' => $owner->family_id, 'mode' => $i % 2 === 0 ? 'cooperation' : 'competition']);
            $plan->categories()->attach($category->id);
            $plan->planMembers()->createMany($owner->family->members()->pluck('id')->map(fn ($id) => ['member_id' => $id])->all());
        }
    }

    $smallCount = qcQueries($small, '/api/v1/goals');
    $largeCount = qcQueries($large, '/api/v1/goals');

    expect($largeCount)->toBe($smallCount);

    actingAsMember($large);
    $data = test()->getJson('/api/v1/goals')->json('data');
    expect($data)->toHaveCount(6)
        ->and($data[0]['current_period']['entries'])->toHaveCount(7)
        ->and($data[0]['current_period']['team_total'])->toBe(21);
});

it('lists goal history with a constant number of queries', function () {
    $category = Category::factory()->create();
    $plans = [];

    foreach ([2, 6] as $rows) {
        $owner = qcFamily(2, $category);
        $plan = GoalPlan::factory()->create(['family_id' => $owner->family_id, 'starts_on' => now()->subWeeks(10)->toDateString()]);
        $plan->categories()->attach($category->id);

        for ($i = 1; $i <= $rows; $i++) {
            GoalPeriod::query()->create([
                'goal_plan_id' => $plan->id,
                'starts_at' => now()->subWeeks($i + 1),
                'ends_at' => now()->subWeeks($i),
                'status' => GoalPeriod::STATUS_CLOSED,
                'target_points' => 50,
                'member_targets' => [(string) $owner->id => 50],
                'result' => ['period_id' => 0, 'team_total' => $i, 'reached' => false, 'winner_member_ids' => [], 'entries' => []],
                'closed_at' => now()->subWeeks($i),
            ]);
        }

        $plans[$rows] = [$owner, $plan];
    }

    expect(qcQueries($plans[2][0], "/api/v1/goals/{$plans[2][1]->id}/history"))
        ->toBe(qcQueries($plans[6][0], "/api/v1/goals/{$plans[6][1]->id}/history"));
});

it('returns the play session payload with a constant number of queries', function () {
    $category = Category::factory()->create();
    $items = ContentItem::factory()->count(6)->create(['category_id' => $category->id]);
    $sessions = [];

    foreach ([2, 6] as $rows) {
        $owner = familyOwner(['tier' => 'family']);
        $session = PlaySession::query()->create([
            'family_id' => $owner->family_id,
            'member_id' => $owner->id,
            'category_id' => $category->id,
            'target_points' => 10,
            'blunder_cap' => 3,
            'status' => PlaySession::STATUS_ACTIVE,
            'started_at' => now(),
        ]);

        foreach ($items->take($rows)->values() as $position => $item) {
            SessionItem::query()->create(['play_session_id' => $session->id, 'content_item_id' => $item->id, 'position' => $position + 1]);
        }

        $sessions[$rows] = [$owner, $session];
    }

    expect(qcQueries($sessions[2][0], "/api/v1/play/sessions/{$sessions[2][1]->id}"))
        ->toBe(qcQueries($sessions[6][0], "/api/v1/play/sessions/{$sessions[6][1]->id}"));
});

it('returns a play session payload with generated items with a constant number of queries', function () {
    $category = Category::factory()->create();
    $content = ContentItem::factory()->count(3)->create(['category_id' => $category->id]);
    $sudoku = app(Sudoku::class);
    $sessions = [];

    foreach ([2, 6] as $rows) {
        $owner = familyOwner(['tier' => 'family']);
        $session = PlaySession::query()->create([
            'family_id' => $owner->family_id,
            'member_id' => $owner->id,
            'category_id' => $category->id,
            'target_points' => 10,
            'blunder_cap' => 3,
            'status' => PlaySession::STATUS_ACTIVE,
            'started_at' => now(),
        ]);

        for ($position = 1; $position <= $rows; $position++) {
            $generated = $sudoku->generate("qc-{$position}", 2, ['size' => 4]);
            SessionItem::query()->create([
                'play_session_id' => $session->id, 'type_key' => 'sudoku', 'category_id' => $category->id, 'seed' => "qc-{$position}",
                'position' => $position, 'points' => 3, 'payload' => $generated['payload'], 'answer' => $generated['answer'],
            ]);
        }

        // Plus content-backed items, so both kinds are in the same payload.
        foreach ($content->take($rows === 2 ? 1 : 3)->values() as $i => $item) {
            SessionItem::query()->create(['play_session_id' => $session->id, 'content_item_id' => $item->id, 'type_key' => 'multiple_choice', 'position' => 100 + $i]);
        }

        $sessions[$rows] = [$owner, $session];
    }

    expect(qcQueries($sessions[2][0], "/api/v1/play/sessions/{$sessions[2][1]->id}"))
        ->toBe(qcQueries($sessions[6][0], "/api/v1/play/sessions/{$sessions[6][1]->id}"));
});

it('lists my social accounts with a constant number of queries', function () {
    $small = familyOwner();
    $large = familyOwner();
    SocialAccount::factory()->create(['user_id' => $small->user_id]);
    SocialAccount::factory()->create(['user_id' => $large->user_id]);
    SocialAccount::factory()->facebook()->create(['user_id' => $large->user_id]);

    expect(qcQueries($small, '/api/v1/me/social-accounts'))->toBe(qcQueries($large, '/api/v1/me/social-accounts'));
});
