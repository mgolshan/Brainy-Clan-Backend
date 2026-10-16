<?php

use App\Modules\Billing\Actions\ApplyFamilyTier;
use App\Modules\Billing\Contracts\RevenueCatClient;
use App\Modules\Billing\Events\SubscriptionChanged;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\WebhookEvent;
use App\Modules\Billing\Support\HttpRevenueCatClient;
use App\Modules\Family\Models\Family;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * B8: `POST /family/subscription/sync` (RevenueCat REST API, faked) and the scheduled reconciliation
 * of subscriptions whose expiry passed without an EXPIRATION webhook.
 */
class FakeRevenueCatClient implements RevenueCatClient
{
    /** @var list<string> */
    public array $requested = [];

    /** @param array<string, mixed>|null $subscriber null = request fails */
    public function __construct(public bool $configured = true, public ?array $subscriber = null) {}

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function subscriber(string $appUserId): array
    {
        $this->requested[] = $appUserId;

        return $this->subscriber ?? throw new RuntimeException('RevenueCat unreachable');
    }
}

/**
 * A RevenueCat v1 `subscriber` with the `family` entitlement on product `brainy_family_monthly`.
 *
 * @param  array<string, mixed>  $entitlement
 * @param  array<string, mixed>  $subscription
 * @return array<string, mixed>
 */
function rcSubscriber(array $entitlement = [], array $subscription = []): array
{
    $expires = now()->addDays(30)->toIso8601ZuluString();

    return [
        'original_app_user_id' => 'family-1',
        'entitlements' => ['family' => [
            'expires_date' => $expires,
            'grace_period_expires_date' => null,
            'product_identifier' => 'brainy_family_monthly',
            'purchase_date' => now()->subDay()->toIso8601ZuluString(),
            ...$entitlement,
        ]],
        'subscriptions' => ['brainy_family_monthly' => [
            'store' => 'play_store',
            'period_type' => 'normal',
            'is_sandbox' => true,
            'expires_date' => $expires,
            'unsubscribe_detected_at' => null,
            'billing_issues_detected_at' => null,
            ...$subscription,
        ]],
    ];
}

function fakeRevenueCat(bool $configured = true, ?array $subscriber = null): FakeRevenueCatClient
{
    $client = new FakeRevenueCatClient($configured, $subscriber);
    app()->instance(RevenueCatClient::class, $client);

    return $client;
}

beforeEach(function () {
    $this->owner = familyOwner();
    $this->family = $this->owner->family;
});

// ---------------------------------------------------------------------------------------------
// POST /family/subscription/sync
// ---------------------------------------------------------------------------------------------

it('returns the current state without an API key', function () {
    $client = fakeRevenueCat(configured: false, subscriber: rcSubscriber());
    actingAsMember($this->owner);

    $this->postJson('/api/v1/family/subscription/sync')
        ->assertOk()
        ->assertExactJson(['data' => [
            'tier' => 'free', 'status' => 'none', 'store' => null, 'product_id' => null, 'expires_at' => null,
            'will_renew' => false, 'revenuecat_app_user_id' => 'family-'.$this->family->id,
        ]]);

    expect($client->requested)->toBe([]);
});

it('pulls the subscriber from RevenueCat and switches the tier', function () {
    Event::fake([SubscriptionChanged::class]);
    $client = fakeRevenueCat(subscriber: rcSubscriber());
    actingAsMember($this->owner);

    $this->postJson('/api/v1/family/subscription/sync')
        ->assertOk()
        ->assertJsonPath('data.tier', 'family')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.store', 'play_store')
        ->assertJsonPath('data.product_id', 'brainy_family_monthly')
        ->assertJsonPath('data.will_renew', true);

    expect($client->requested)->toBe(['family-'.$this->family->id])
        ->and($this->family->fresh()->tier)->toBe(Family::TIER_FAMILY);

    $subscription = Subscription::query()->where('family_id', $this->family->id)->sole();
    expect($subscription->environment)->toBe('SANDBOX')
        ->and($subscription->last_event_type)->toBe('SYNC')
        ->and($subscription->expires_at?->isFuture())->toBeTrue();

    Event::assertDispatched(SubscriptionChanged::class, fn (SubscriptionChanged $event) => $event->familyId === $this->family->id
        && $event->tier === 'family' && $event->tierChanged && $event->source === ApplyFamilyTier::SOURCE_SYNC);
});

it('applies cancelled, billing-issue and expired states from RevenueCat', function () {
    actingAsMember($this->owner);

    fakeRevenueCat(subscriber: rcSubscriber(subscription: ['unsubscribe_detected_at' => now()->subHour()->toIso8601ZuluString()]));
    $this->postJson('/api/v1/family/subscription/sync')->assertJsonPath('data.status', 'active')->assertJsonPath('data.will_renew', false)->assertJsonPath('data.tier', 'family');

    fakeRevenueCat(subscriber: rcSubscriber(
        ['expires_date' => now()->subHour()->toIso8601ZuluString(), 'grace_period_expires_date' => now()->addDays(6)->toIso8601ZuluString()],
        ['billing_issues_detected_at' => now()->subHour()->toIso8601ZuluString()],
    ));
    $this->postJson('/api/v1/family/subscription/sync')->assertJsonPath('data.status', 'grace')->assertJsonPath('data.tier', 'family');

    fakeRevenueCat(subscriber: rcSubscriber(['expires_date' => now()->subDay()->toIso8601ZuluString()]));
    $this->postJson('/api/v1/family/subscription/sync')->assertJsonPath('data.status', 'expired')->assertJsonPath('data.tier', 'free')->assertJsonPath('data.will_renew', false);
});

it('leaves a family that never subscribed alone and ends a stored subscription the subscriber no longer has', function () {
    actingAsMember($this->owner);
    fakeRevenueCat(subscriber: ['entitlements' => [], 'subscriptions' => []]);

    $this->postJson('/api/v1/family/subscription/sync')->assertJsonPath('data.status', 'none');
    expect(Subscription::query()->count())->toBe(0);

    Subscription::factory()->create(['family_id' => $this->family->id]);
    $this->family->update(['tier' => Family::TIER_FAMILY]);

    $this->postJson('/api/v1/family/subscription/sync')->assertJsonPath('data.status', 'expired')->assertJsonPath('data.tier', 'free');
});

it('returns the current state when RevenueCat cannot be reached', function () {
    Subscription::factory()->create(['family_id' => $this->family->id]);
    $this->family->update(['tier' => Family::TIER_FAMILY]);
    fakeRevenueCat(subscriber: null);
    actingAsMember($this->owner);

    $this->postJson('/api/v1/family/subscription/sync')->assertOk()->assertJsonPath('data.status', 'active')->assertJsonPath('data.tier', 'family');
});

it('is for admins only', function () {
    fakeRevenueCat(subscriber: rcSubscriber());
    actingAsMember(addMember($this->family, 30));

    $this->postJson('/api/v1/family/subscription/sync')->assertForbidden()->assertJsonPath('code', 'forbidden');
});

it('counts a sync as the newest event, so older webhooks delivered later are stale', function () {
    config(['services.revenuecat.webhook_secret' => 'rc-test-secret']);
    fakeRevenueCat(subscriber: rcSubscriber());
    actingAsMember($this->owner);
    $this->postJson('/api/v1/family/subscription/sync')->assertOk();

    $this->postJson('/api/v1/webhooks/revenuecat', ['api_version' => '1.0', 'event' => [
        'id' => 'late-expiration', 'type' => 'EXPIRATION', 'app_user_id' => 'family-'.$this->family->id,
        'event_timestamp_ms' => now()->subMinutes(5)->getTimestampMs(), 'entitlement_ids' => ['family'],
    ]], ['Authorization' => 'Bearer rc-test-secret'])->assertOk()->assertJsonPath('data.outcome', WebhookEvent::OUTCOME_STALE);

    expect($this->family->fresh()->tier)->toBe(Family::TIER_FAMILY);
});

it('calls the RevenueCat REST API v1 with the secret key', function () {
    config(['services.revenuecat.api_key' => 'sk_test_123']);
    Http::fake(['api.revenuecat.com/*' => fn (Request $request) => str_ends_with($request->url(), '/family-7')
        ? Http::response(['request_date' => now()->toIso8601ZuluString(), 'subscriber' => rcSubscriber()])
        : Http::response(['code' => 7225, 'message' => 'Internal error'], 500)]);

    $client = app(HttpRevenueCatClient::class);
    expect($client->isConfigured())->toBeTrue()
        ->and($client->subscriber('family-7')['entitlements'])->toHaveKey('family');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.revenuecat.com/v1/subscribers/family-7'
        && $request->method() === 'GET'
        && $request->hasHeader('Authorization', 'Bearer sk_test_123'));

    expect(fn () => $client->subscriber('family-8'))->toThrow(RuntimeException::class);

    config(['services.revenuecat.api_key' => null]);
    expect(app(HttpRevenueCatClient::class)->isConfigured())->toBeFalse();
});

// ---------------------------------------------------------------------------------------------
// billing:reconcile-subscriptions
// ---------------------------------------------------------------------------------------------

/**
 * A family-tier family with a subscription in the given state.
 *
 * @param  array<string, mixed>  $attributes
 */
function reconcileFamily(array $attributes): Family
{
    $family = familyOwner(['tier' => Family::TIER_FAMILY])->family;
    Subscription::factory()->create(['family_id' => $family->id, ...$attributes]);

    return $family;
}

it('expires overdue subscriptions without an API key and moves the family back to free', function () {
    Event::fake([SubscriptionChanged::class]);
    fakeRevenueCat(configured: false);

    $overdue = reconcileFamily(['expires_at' => now()->subHours(2)]);
    $justExpired = reconcileFamily(['expires_at' => now()->subMinutes(10)]); // RevenueCat may still send EXPIRATION
    $graceRunning = reconcileFamily(['status' => 'grace', 'expires_at' => now()->subDays(2), 'grace_expires_at' => now()->addDay()]);
    $graceOver = reconcileFamily(['status' => 'grace', 'expires_at' => now()->subDays(8), 'grace_expires_at' => now()->subHours(3)]);
    $lifetime = reconcileFamily(['expires_at' => null]);
    $renewing = reconcileFamily(['expires_at' => now()->addDays(20)]);

    $this->artisan('billing:reconcile-subscriptions')->assertSuccessful()->expectsOutputToContain('Reconciled 2 subscription(s).');

    $status = fn (Family $family) => [Subscription::query()->where('family_id', $family->id)->value('status'), $family->fresh()->tier];
    expect($status($overdue))->toBe(['expired', 'free'])
        ->and($status($graceOver))->toBe(['expired', 'free'])
        ->and($status($justExpired))->toBe(['active', 'family'])
        ->and($status($graceRunning))->toBe(['grace', 'family'])
        ->and($status($lifetime))->toBe(['active', 'family'])
        ->and($status($renewing))->toBe(['active', 'family'])
        ->and(Subscription::query()->where('family_id', $overdue->id)->value('will_renew'))->toBeFalse();

    Event::assertDispatched(SubscriptionChanged::class, fn (SubscriptionChanged $event) => $event->familyId === $overdue->id
        && $event->tier === 'free' && $event->source === ApplyFamilyTier::SOURCE_RECONCILE);
    Event::assertDispatchedTimes(SubscriptionChanged::class, 2);
});

it('re-syncs overdue subscriptions from RevenueCat when a key is configured (missed renewal)', function () {
    $client = fakeRevenueCat(subscriber: rcSubscriber());
    $family = reconcileFamily(['expires_at' => now()->subHours(2)]);

    $this->artisan('billing:reconcile-subscriptions')->assertSuccessful();

    expect($client->requested)->toBe(['family-'.$family->id])
        ->and(Subscription::query()->where('family_id', $family->id)->value('status'))->toBe('active')
        ->and($family->fresh()->tier)->toBe(Family::TIER_FAMILY);
});

it('leaves overdue subscriptions for the next run when RevenueCat cannot be reached', function () {
    fakeRevenueCat(subscriber: null);
    $family = reconcileFamily(['expires_at' => now()->subHours(2)]);

    $this->artisan('billing:reconcile-subscriptions')->assertSuccessful()->expectsOutputToContain('Reconciled 0 subscription(s).');

    expect($family->fresh()->tier)->toBe(Family::TIER_FAMILY);
});

it('switches the tier through the same action for webhooks', function () {
    Event::fake([SubscriptionChanged::class]);
    config(['services.revenuecat.webhook_secret' => 'rc-test-secret']);

    $this->postJson('/api/v1/webhooks/revenuecat', ['api_version' => '1.0', 'event' => [
        'id' => 'purchase-1', 'type' => 'INITIAL_PURCHASE', 'app_user_id' => 'family-'.$this->family->id, 'entitlement_ids' => ['family'],
        'event_timestamp_ms' => now()->getTimestampMs(), 'expiration_at_ms' => now()->addMonth()->getTimestampMs(), 'store' => 'APP_STORE',
    ]], ['Authorization' => 'Bearer rc-test-secret'])->assertOk()->assertJsonPath('data.outcome', 'applied');

    Event::assertDispatched(SubscriptionChanged::class, fn (SubscriptionChanged $event) => $event->source === ApplyFamilyTier::SOURCE_WEBHOOK && $event->tier === 'family');
});

it('schedules the reconciliation hourly', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'billing:reconcile-subscriptions'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 * * * *');
});

it('turns a RevenueCat connection error into a failed sync (current state returned)', function () {
    config(['services.revenuecat.api_key' => 'sk_test_123']);
    Http::fake(['api.revenuecat.com/*' => Http::failedConnection('timeout')]);

    expect(fn () => app(HttpRevenueCatClient::class)->subscriber('family-1'))->toThrow(RuntimeException::class, 'could not be reached');

    app()->forgetInstance(RevenueCatClient::class);
    actingAsMember($this->owner);
    $this->postJson('/api/v1/family/subscription/sync')->assertOk()->assertJsonPath('data.status', 'none');
});
