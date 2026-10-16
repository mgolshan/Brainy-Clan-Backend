<?php

use App\Filament\Resources\Families\Pages\ViewFamily;
use App\Filament\Resources\Families\RelationManagers\AllMembersRelationManager;
use App\Filament\Resources\Families\RelationManagers\InvitesRelationManager;
use App\Filament\Resources\GoalPlans\Pages\ViewGoalPlan;
use App\Filament\Resources\GoalPlans\RelationManagers\PeriodsRelationManager;
use App\Filament\Resources\WebhookEvents\Pages\ListWebhookEvents;
use App\Filament\Resources\WebhookEvents\WebhookEventResource;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\WebhookEvent;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentBatch;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Content\Models\Tag;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\Invite;
use App\Modules\Family\Models\SignInCode;
use App\Modules\Goals\Models\GoalPeriod;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\SocialAccount;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * No N+1 in the admin (docs/PLAN.md): every list page, relation manager and view page renders
 * with several rows while lazy loading throws, and the busiest tables run the same number of
 * queries for 2 and 6 rows.
 */

/**
 * A family with `$members` extra members (each with a device), invites, and a goal plan with
 * closed and open periods. Content rows are added per family too.
 */
function adminFamily(int $members): FamilyMember
{
    $owner = familyOwner(['tier' => 'family']);
    $category = Category::factory()->create();
    $batch = ContentBatch::query()->create(['category_id' => $category->id, 'type_key' => 'multiple_choice', 'status' => 'draft']);
    $tag = Tag::query()->create(['name' => 'tag-'.$owner->family_id]);

    for ($i = 0; $i < $members; $i++) {
        $member = addMember($owner->family, $i % 2 === 0 ? 9 : 30);
        Device::factory()->count(2)->create(['user_id' => $member->user_id]);

        if ($i % 2 === 1) {
            SocialAccount::factory()->create(['user_id' => $member->user_id]);
            SocialAccount::factory()->facebook()->create(['user_id' => $member->user_id]);
        }

        Invite::factory()->create(['family_id' => $owner->family_id]);
        SignInCode::factory()->create(['member_id' => $member->id]);
        ContentItem::factory()->create(['category_id' => $category->id, 'content_batch_id' => $batch->id])->tags()->attach($tag);
    }

    Subscription::factory()->create(['family_id' => $owner->family_id]);
    WebhookEvent::factory()->count($members)->create(['family_id' => $owner->family_id, 'type' => 'RENEWAL', 'outcome' => WebhookEvent::OUTCOME_APPLIED]);
    WebhookEvent::factory()->create(['family_id' => null, 'outcome' => WebhookEvent::OUTCOME_IGNORED]);

    $plan = GoalPlan::factory()->create(['family_id' => $owner->family_id, 'name' => 'Plan '.$owner->family_id]);
    $plan->categories()->attach($category->id);
    $plan->planMembers()->createMany($owner->family->members()->pluck('id')->map(fn ($id) => ['member_id' => $id])->all());

    for ($i = 1; $i <= $members; $i++) {
        GoalPeriod::query()->create([
            'goal_plan_id' => $plan->id,
            'starts_at' => now()->subWeeks($i + 1),
            'ends_at' => now()->subWeeks($i),
            'status' => GoalPeriod::STATUS_CLOSED,
            'target_points' => 50,
            'member_targets' => [(string) $owner->id => 50],
            'result' => [
                'team_total' => 60, 'reached' => true, 'winner_member_ids' => [$owner->id],
                'entries' => [['member_id' => $owner->id, 'nickname' => 'Mom', 'avatar' => 'owl', 'points' => 60, 'target' => 50, 'reached' => true, 'rank' => 1]],
            ],
            'closed_at' => now()->subWeeks($i),
        ]);
    }

    return $owner;
}

function adminQueries(string $url): int
{
    test()->get($url)->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->get($url)->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

beforeEach(function () {
    $this->actingAs(User::factory()->staff()->create());
});

it('renders every admin list page with several rows', function (string $url) {
    adminFamily(4);
    adminFamily(3);

    $this->get($url)->assertOk();
})->with([
    '/admin/families',
    '/admin/goal-plans',
    '/admin/content-items',
    '/admin/content-batches',
    '/admin/categories',
    '/admin/tags',
    '/admin/age-bands',
    '/admin/settings',
    '/admin/challenge-types',
    '/admin/activities',
    '/admin/webhook-events',
]);

it('keeps the busiest admin tables at a constant number of queries', function (string $url) {
    adminFamily(2);
    $small = adminQueries($url);

    adminFamily(4);
    adminFamily(4);

    expect(adminQueries($url))->toBe($small);
})->with([
    '/admin/families',
    '/admin/goal-plans',
    '/admin/content-items',
    '/admin/content-batches',
    '/admin/tags',
    '/admin/activities',
    '/admin/webhook-events',
]);

it('shows members with email login and devices, and invites, on the family page', function () {
    $owner = adminFamily(4);
    $family = Family::query()->findOrFail($owner->family_id);

    Livewire::test(AllMembersRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class])
        ->assertOk()
        ->assertCanSeeTableRecords($family->allMembers()->get())
        ->assertSee($owner->user->email)
        ->assertSee('Google ('.SocialAccount::query()->where('provider', 'google')->firstOrFail()->email.')');

    Livewire::test(InvitesRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class])
        ->assertOk()
        ->assertCanSeeTableRecords($family->invites()->get());
});

it('keeps the members relation manager at a constant number of queries', function () {
    $count = function (FamilyMember $owner): int {
        $family = Family::query()->findOrFail($owner->family_id);
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(AllMembersRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class])->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $count(adminFamily(1));

    expect($count(adminFamily(6)))->toBe($count(adminFamily(2)));
});

it('issues a sign-in code and signs a device out from the members relation manager', function () {
    $owner = adminFamily(1);
    $family = Family::query()->findOrFail($owner->family_id);
    $kid = $family->members()->where('id', '!=', $owner->id)->firstOrFail();
    $device = Device::query()->where('user_id', $kid->user_id)->firstOrFail();

    Livewire::test(AllMembersRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class])
        ->callTableAction('signInCode', $kid)
        ->assertNotified()
        ->callTableAction('signOutDevice', $kid, data: ['device_id' => $device->id])
        ->assertHasNoTableActionErrors();

    expect(SignInCode::query()->where('member_id', $kid->id)->whereNull('voided_at')->count())->toBe(1)
        ->and(Device::query()->find($device->id))->toBeNull();
});

it('shows a goal plan with its periods and results', function () {
    $owner = adminFamily(3);
    $plan = GoalPlan::query()->where('family_id', $owner->family_id)->firstOrFail();

    $this->get("/admin/goal-plans/{$plan->id}")->assertOk()->assertSee($plan->name);

    Livewire::test(PeriodsRelationManager::class, ['ownerRecord' => $plan, 'pageClass' => ViewGoalPlan::class])
        ->assertOk()
        ->assertCanSeeTableRecords($plan->periods()->get())
        ->assertSee('Mom');
});

it('lets support find and open plans a family deleted', function () {
    $owner = adminFamily(2);
    $plan = GoalPlan::query()->where('family_id', $owner->family_id)->firstOrFail();
    $plan->delete();

    $this->get('/admin/goal-plans')->assertOk()->assertDontSee($plan->name);
    $this->get("/admin/goal-plans/{$plan->id}")->assertOk()->assertSee($plan->name);
});

it('shows the subscription read-only on the family page', function () {
    $owner = adminFamily(1);
    Subscription::query()->where('family_id', $owner->family_id)->update(['product_id' => 'brainy_family_yearly', 'status' => Subscription::STATUS_GRACE]);

    $this->get("/admin/families/{$owner->family_id}")->assertOk()
        ->assertSee('Subscription')
        ->assertSee('grace')
        ->assertSee('brainy_family_yearly')
        ->assertSee('family-'.$owner->family_id);

    $neverSubscribed = familyOwner();
    $this->get("/admin/families/{$neverSubscribed->family_id}")->assertOk()->assertSee('none');
});

it('lists webhook events with provider and outcome filters and shows the payload', function () {
    $owner = adminFamily(2);
    $applied = WebhookEvent::query()->where('outcome', WebhookEvent::OUTCOME_APPLIED)->get();
    $ignored = WebhookEvent::query()->where('outcome', WebhookEvent::OUTCOME_IGNORED)->get();

    Livewire::test(ListWebhookEvents::class)
        ->assertCanSeeTableRecords($applied->merge($ignored))
        ->filterTable('outcome', WebhookEvent::OUTCOME_IGNORED)
        ->assertCanSeeTableRecords($ignored)
        ->assertCanNotSeeTableRecords($applied)
        ->resetTableFilters()
        ->filterTable('provider', WebhookEvent::PROVIDER_REVENUECAT)
        ->assertCanSeeTableRecords($applied);

    $event = $applied->first();
    $this->get("/admin/webhook-events/{$event->id}")->assertOk()
        ->assertSee($event->event_id)
        ->assertSee($owner->family->name)
        ->assertSee('&quot;api_version&quot;: &quot;1.0&quot;', false);

    expect(WebhookEventResource::canCreate())->toBeFalse()
        ->and(WebhookEventResource::canEdit($event))->toBeFalse()
        ->and(WebhookEventResource::canDelete($event))->toBeFalse();
});

it('keeps the invites and periods relation managers at a constant number of queries', function () {
    $count = function (FamilyMember $owner): array {
        $family = Family::query()->findOrFail($owner->family_id);
        $plan = GoalPlan::query()->where('family_id', $owner->family_id)->firstOrFail();
        $queries = [];

        foreach ([
            [InvitesRelationManager::class, $family, ViewFamily::class],
            [PeriodsRelationManager::class, $plan, ViewGoalPlan::class],
        ] as [$manager, $record, $page]) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::test($manager, ['ownerRecord' => $record, 'pageClass' => $page])->assertOk();
            $queries[] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }

        return $queries;
    };

    $count(adminFamily(1));

    expect($count(adminFamily(6)))->toBe($count(adminFamily(2)));
});

it('keeps the family, goal plan and content batch view pages at a constant number of queries', function () {
    $urls = function (FamilyMember $owner): array {
        return [
            "/admin/families/{$owner->family_id}",
            '/admin/goal-plans/'.GoalPlan::query()->where('family_id', $owner->family_id)->value('id'),
            '/admin/content-batches/'.ContentBatch::query()->latest('id')->value('id'),
            '/admin/categories',
        ];
    };

    $small = array_map(adminQueries(...), $urls(adminFamily(2)));
    $large = array_map(adminQueries(...), $urls(adminFamily(6)));

    expect($large)->toBe($small);
});
