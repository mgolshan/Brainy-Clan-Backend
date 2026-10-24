<?php

use App\Filament\Resources\AccountDeletionRequests\AccountDeletionRequestResource;
use App\Filament\Resources\WebhookEvents\WebhookEventResource;
use App\Filament\Support\DashboardMetrics;
use App\Filament\Widgets\ActivityStats;
use App\Filament\Widgets\BatchesAwaitingReview;
use App\Filament\Widgets\BusinessStats;
use App\Filament\Widgets\HealthStats;
use App\Filament\Widgets\NewFamiliesChart;
use App\Filament\Widgets\PlaysPerCategoryChart;
use App\Filament\Widgets\PlaysTrendChart;
use App\Filament\Widgets\RecentFamilies;
use App\Filament\Widgets\RecentWebhookErrors;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\WebhookEvent;
use App\Modules\Billing\Support\WebhookRejections;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentBatch;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Family\Models\AccountDeletionRequest;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\User;
use App\Modules\Play\Models\PlaySession;
use App\Modules\Scoring\Models\PointEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Admin dashboard (docs/PLAN.md "Admin dashboard"): stat cards, charts and lists computed by a
 * fixed number of aggregate queries, cached for a minute.
 */

/** Every dashboard widget. */
const DASHBOARD_WIDGETS = [
    ActivityStats::class,
    BusinessStats::class,
    HealthStats::class,
    PlaysTrendChart::class,
    NewFamiliesChart::class,
    PlaysPerCategoryChart::class,
    RecentFamilies::class,
    BatchesAwaitingReview::class,
    RecentWebhookErrors::class,
];

function dashboardPlay(FamilyMember $member, Category $category, CarbonImmutable $at): PlaySession
{
    return PlaySession::query()->create([
        'family_id' => $member->family_id,
        'member_id' => $member->id,
        'category_id' => $category->id,
        'target_points' => 10,
        'blunder_cap' => 3,
        'status' => PlaySession::STATUS_COMPLETED,
        'started_at' => $at,
    ]);
}

function dashboardPoints(PlaySession $session, int $amount, CarbonImmutable $at, string $reason = PointEntry::REASON_CORRECT_ANSWER): void
{
    PointEntry::query()->create([
        'family_id' => $session->family_id,
        'member_id' => $session->member_id,
        'category_id' => $session->category_id,
        'play_session_id' => $session->id,
        'amount' => $amount,
        'reason' => $reason,
        'created_at' => $at,
    ]);
}

function dashboardFailedJob(string $job, CarbonImmutable $at): void
{
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'redis',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => $job]),
        'exception' => 'RuntimeException: boom',
        'failed_at' => $at,
    ]);
}

function dashboardDeletionRequest(string $status, CarbonImmutable $expiresAt): void
{
    AccountDeletionRequest::query()->create([
        'email_hash' => hash('sha256', Str::random()),
        'status' => $status,
        'expires_at' => $expiresAt,
    ]);
}

/**
 * The known dataset, at 2026-10-17 12:00 UTC:
 * - families: Alpha (family plan, 2 days old, owner + 2 kids), Beta (free, 20 days), Gamma (free, today),
 *   Delta (deleted, 40 days old, its owner removed);
 * - plays: Alpha owner 2 today + 1 three days ago, Alpha kid 1 today, Beta owner 1 ten days ago;
 *   categories Geography → Capitals (3 plays in 7 days) and Science (1);
 * - points: +5 today, +3 two days ago, a −2 correction today, +7 ten days ago;
 * - subscriptions: Alpha active, Beta trial, Gamma grace, Delta expired;
 * - content: published en 3 / fr 2, draft 2, review 1, archived 1; batches: 1 in review, 1 done;
 * - health: 2 failed jobs (1 push job today, 1 three days ago), webhook errors 1 this week + 1 older,
 *   3 devices (2 with a push token), deletion requests: 1 pending, 1 expired, 1 blocked.
 *
 * @return array{alpha: FamilyMember, kid: FamilyMember, beta: FamilyMember, gamma: FamilyMember}
 */
function dashboardDataset(): array
{
    $now = CarbonImmutable::now();

    $alpha = familyOwner(['name' => 'Alpha family', 'tier' => Family::TIER_FAMILY, 'created_at' => $now->subDays(2)]);
    $kid = addMember($alpha->family);
    addMember($alpha->family, 11);
    $beta = familyOwner(['name' => 'Beta family', 'created_at' => $now->subDays(20)]);
    $gamma = familyOwner(['name' => 'Gamma family', 'created_at' => $now]);
    $delta = familyOwner(['name' => 'Delta family', 'created_at' => $now->subDays(40)]);
    Family::query()->whereKey($delta->family_id)->update(['deleted_at' => $now->subDays(1)]);
    $delta->update(['status' => FamilyMember::STATUS_REMOVED]);

    $geography = Category::factory()->create(['name' => ['en' => 'Geography']]);
    $capitals = Category::factory()->create(['name' => ['en' => 'Capitals'], 'parent_id' => $geography->id]);
    $science = Category::factory()->create(['name' => ['en' => 'Science']]);

    $today = dashboardPlay($alpha, $capitals, $now->subHours(2));
    dashboardPlay($alpha, $capitals, $now->subHour());
    $earlier = dashboardPlay($alpha, $geography, $now->subDays(3));
    dashboardPlay($kid, $science, $now->subMinutes(30));
    $old = dashboardPlay($beta, $science, $now->subDays(10));

    dashboardPoints($today, 5, $now->subHours(2));
    dashboardPoints($earlier, 3, $now->subDays(2));
    dashboardPoints($today, -2, $now->subHour(), PointEntry::REASON_CORRECTION);
    dashboardPoints($old, 7, $now->subDays(10));

    Subscription::factory()->create(['family_id' => $alpha->family_id]);
    Subscription::factory()->create(['family_id' => $beta->family_id, 'period_type' => 'trial']);
    Subscription::factory()->grace()->create(['family_id' => $gamma->family_id]);
    Subscription::factory()->expired()->create(['family_id' => $delta->family_id]);

    ContentItem::factory()->count(3)->create(['category_id' => $science->id]);
    ContentItem::factory()->count(2)->create(['category_id' => $science->id, 'locale' => 'fr']);
    ContentItem::factory()->draft()->count(2)->create(['category_id' => $science->id]);
    ContentItem::factory()->create(['category_id' => $science->id, 'status' => ContentItem::STATUS_REVIEW]);
    ContentItem::factory()->create(['category_id' => $science->id, 'status' => ContentItem::STATUS_ARCHIVED]);
    $batch = ContentBatch::factory()->create(['category_id' => $capitals->id, 'status' => ContentBatch::STATUS_REVIEW]);
    ContentItem::factory()->count(4)->create(['category_id' => $capitals->id, 'content_batch_id' => $batch->id, 'status' => ContentItem::STATUS_ARCHIVED]);
    ContentBatch::factory()->create(['category_id' => $science->id, 'status' => ContentBatch::STATUS_DONE]);

    dashboardFailedJob('App\\Modules\\Notifications\\Jobs\\DeliverPushNotification', $now->subHour());
    dashboardFailedJob('App\\Modules\\Content\\Jobs\\GenerateContentBatchJob', $now->subDays(3));

    WebhookEvent::factory()->create(['outcome' => WebhookEvent::OUTCOME_FAILED, 'type' => 'RENEWAL', 'family_id' => $alpha->family_id, 'note' => 'Product not found', 'created_at' => $now->subDay()]);
    WebhookEvent::factory()->create(['outcome' => WebhookEvent::OUTCOME_FAILED, 'created_at' => $now->subDays(9)]);
    WebhookEvent::factory()->create(['outcome' => WebhookEvent::OUTCOME_APPLIED]);

    // AdMob SSV rejections: 2 bad signatures today + 1 unusable payload 3 days ago in the week; 1 older.
    $rejections = app(WebhookRejections::class);
    $rejections->record(WebhookRejections::PROVIDER_ADMOB, WebhookRejections::REASON_SIGNATURE_INVALID);
    $rejections->record(WebhookRejections::PROVIDER_ADMOB, WebhookRejections::REASON_SIGNATURE_INVALID);
    DB::table('webhook_rejections')->insert([
        ['provider' => WebhookRejections::PROVIDER_ADMOB, 'reason' => WebhookRejections::REASON_INVALID_PAYLOAD, 'day' => $now->subDays(3)->toDateString(), 'hits' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['provider' => WebhookRejections::PROVIDER_ADMOB, 'reason' => WebhookRejections::REASON_SIGNATURE_INVALID, 'day' => $now->subDays(10)->toDateString(), 'hits' => 5, 'created_at' => $now, 'updated_at' => $now],
    ]);

    Device::factory()->create(['user_id' => $alpha->user_id, 'push_token' => 'ExponentPushToken[a]']);
    Device::factory()->create(['user_id' => $kid->user_id, 'push_token' => 'ExponentPushToken[b]']);
    Device::factory()->create(['user_id' => $beta->user_id]);

    dashboardDeletionRequest(AccountDeletionRequest::STATUS_PENDING, $now->addMinutes(30));
    dashboardDeletionRequest(AccountDeletionRequest::STATUS_PENDING, $now->subMinutes(30));
    dashboardDeletionRequest(AccountDeletionRequest::STATUS_BLOCKED, $now->subMinutes(30));

    return ['alpha' => $alpha, 'kid' => $kid, 'beta' => $beta, 'gamma' => $gamma];
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-17 12:00:00', 'UTC'));
    Cache::flush();
    $this->actingAs(User::factory()->staff()->create());
});

it('renders the dashboard with every widget for staff only', function () {
    dashboardDataset();

    $response = $this->get('/admin')->assertOk();

    foreach (array_diff(DASHBOARD_WIDGETS, [RecentWebhookErrors::class]) as $widget) {
        $response->assertSeeLivewire($widget);
    }

    $this->actingAs(User::factory()->create());
    $this->get('/admin')->assertForbidden();
});

it('computes the activity figures from a known dataset', function () {
    dashboardDataset();

    $activity = app(DashboardMetrics::class)->activity();

    expect($activity)->toMatchArray([
        'families' => 3,
        'families_new_week' => 2,
        'families_on_family_plan' => 1,
        'members' => 5,
        'players_today' => 2,
        'players_week' => 2,
        'plays_today' => 3,
        'plays_week' => 4,
        'points_today' => 5,
        'points_week' => 8,
    ])
        ->and($activity['plays_daily'])->toHaveCount(7)
        ->and($activity['plays_daily']['2026-10-14'])->toBe(1)
        ->and($activity['plays_daily']['2026-10-17'])->toBe(3)
        ->and(array_values($activity['points_daily']))->toBe([0, 0, 0, 0, 3, 0, 5]);

    Livewire::test(ActivityStats::class)
        ->assertSee('Families')
        ->assertSee('+2 new in 7 days')
        ->assertSee('2 played today · 2 in 7 days')
        ->assertSee('4 in 7 days')
        ->assertSee('8 awarded in 7 days')
        ->assertSee('/admin/families', false);
});

it('computes the trend and per-category charts', function () {
    dashboardDataset();

    $trend = app(DashboardMetrics::class)->trend();

    expect($trend['plays'])->toHaveCount(30)
        ->and(array_sum($trend['plays']))->toBe(5)
        ->and($trend['plays']['2026-10-07'])->toBe(1)
        ->and(array_sum($trend['families']))->toBe(3)
        ->and($trend['families']['2026-10-15'])->toBe(1)
        ->and(app(DashboardMetrics::class)->playsPerCategory())->toBe(['Geography' => 3, 'Science' => 1]);

    Livewire::test(PlaysTrendChart::class)->assertOk()->assertSee('Plays per day');
    Livewire::test(NewFamiliesChart::class)->assertOk()->assertSee('New families per day')->assertSee('/admin/families', false);
    Livewire::test(PlaysPerCategoryChart::class)->assertOk()->assertSee('Geography')->assertSee('Science')->assertDontSee('Capitals');
});

it('computes subscription and content figures', function () {
    dashboardDataset();

    expect(app(DashboardMetrics::class)->business())->toBe([
        'subscriptions_active' => 1,
        'subscriptions_trial' => 1,
        'subscriptions_grace' => 1,
        'content_published' => 5,
        'content_published_by_locale' => ['en' => 3, 'fr' => 2],
        'content_awaiting_review' => 3,
        'batches_awaiting_review' => 1,
    ]);

    Livewire::test(BusinessStats::class)
        ->assertSee('Paying subscriptions')
        ->assertSee('1 in trial · 1 in grace period')
        ->assertSee('33%')
        ->assertSee('1 of 3 families')
        ->assertSee('en 3 · fr 2')
        ->assertSee('1 AI batches to review');
});

it('computes the system health figures', function () {
    dashboardDataset();

    expect(app(DashboardMetrics::class)->health())->toBe([
        'failed_jobs' => 2,
        'failed_jobs_day' => 1,
        'failed_push_jobs' => 1,
        'webhook_errors_week' => 1,
        'admob_ssv_rejections_week' => 3,
        'admob_ssv_signature_invalid_week' => 2,
        'devices' => 3,
        'devices_with_push' => 2,
        'deletion_requests_pending' => 1,
        'deletion_requests_blocked_week' => 1,
    ]);

    Livewire::test(HealthStats::class)
        ->assertSee('1 in the last 24 hours')
        ->assertSee('67% of 3 devices · 1 failed push jobs')
        ->assertSee('1 blocked in 7 days')
        ->assertSee('AdMob SSV rejections')
        ->assertSee('2 bad signatures · 7 days')
        ->assertSee('/horizon/failed')
        ->assertSee(WebhookEventResource::getUrl('index', ['filters' => ['outcome' => ['value' => WebhookEvent::OUTCOME_FAILED]]]))
        ->assertSee(AccountDeletionRequestResource::getUrl('index', ['filters' => ['status' => ['value' => AccountDeletionRequest::STATUS_PENDING]]]));
});

it('lists recent families, batches to review and webhook errors', function () {
    $dataset = dashboardDataset();
    $batch = ContentBatch::query()->where('status', ContentBatch::STATUS_REVIEW)->firstOrFail();

    Livewire::test(RecentFamilies::class)
        ->assertCanSeeTableRecords(Family::query()->whereIn('id', [$dataset['alpha']->family_id, $dataset['gamma']->family_id])->get())
        ->assertSee('Alpha family')
        ->assertSee('grace');

    Livewire::test(BatchesAwaitingReview::class)
        ->assertCanSeeTableRecords([$batch])
        ->assertSee('Geography → Capitals')
        ->assertSee('ai-batch-review?batch='.$batch->id, false);

    Livewire::test(RecentWebhookErrors::class)
        ->assertCanSeeTableRecords(WebhookEvent::query()->where('outcome', WebhookEvent::OUTCOME_FAILED)->get())
        ->assertSee('Product not found');
});

it('hides the webhook errors list when there was none this week', function () {
    expect(RecentWebhookErrors::canView())->toBeFalse();

    $this->get('/admin')->assertOk()->assertDontSeeLivewire(RecentWebhookErrors::class);
});

it('serves the aggregates from the cache for a minute', function () {
    dashboardDataset();
    $metrics = app(DashboardMetrics::class);
    $metrics->activity();

    familyOwner(['created_at' => now()]);

    expect($metrics->activity()['families'])->toBe(3);

    $this->travel(61)->seconds();

    expect($metrics->activity()['families'])->toBe(4);
});

it('runs the same number of queries whatever the number of families and sessions', function () {
    $count = function (): array {
        $queries = [];

        foreach (DASHBOARD_WIDGETS as $widget) {
            Cache::flush();
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::test($widget)->assertOk();
            $queries[$widget] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }

        return $queries;
    };

    dashboardDataset();
    $small = $count();

    $category = Category::factory()->create();
    foreach (range(1, 6) as $i) {
        $owner = familyOwner(['tier' => $i % 2 === 0 ? Family::TIER_FAMILY : Family::TIER_FREE]);
        addMember($owner->family);
        Subscription::factory()->create(['family_id' => $owner->family_id]);
        $session = dashboardPlay($owner, $category, CarbonImmutable::now()->subDays($i));
        dashboardPoints($session, 4, CarbonImmutable::now()->subDays($i));
        ContentBatch::factory()->create(['category_id' => $category->id, 'status' => ContentBatch::STATUS_REVIEW]);
        WebhookEvent::factory()->create(['outcome' => WebhookEvent::OUTCOME_FAILED, 'family_id' => $owner->family_id]);
        DB::table('webhook_rejections')->insert(['provider' => WebhookRejections::PROVIDER_ADMOB, 'reason' => WebhookRejections::REASON_SIGNATURE_INVALID, 'day' => CarbonImmutable::now()->subDays($i)->toDateString(), 'hits' => $i, 'created_at' => now(), 'updated_at' => now()]);
    }

    expect($count())->toBe($small)
        ->and(max($small))->toBeLessThan(12);
});

it('runs no aggregate queries while the cache is warm', function () {
    dashboardDataset();

    foreach ([ActivityStats::class, BusinessStats::class, HealthStats::class, PlaysTrendChart::class, PlaysPerCategoryChart::class] as $widget) {
        Livewire::test($widget);
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test($widget)->assertOk();
        $tables = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => preg_match('/play_sessions|point_entries|families|subscriptions|content_items|failed_jobs|devices|webhook_rejections/', $sql));
        DB::disableQueryLog();

        expect($tables)->toBeEmpty();
    }
});
