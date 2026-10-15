<?php

use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\WebhookEvent;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * A RevenueCat webhook fixture (tests/Fixtures/revenuecat) for this family, with times moved
 * relative to now so the entitlement is live (expiry in `$expiresInDays`).
 *
 * @param  array<string, mixed>  $overrides  Merged into `event`.
 * @return array<string, mixed>
 */
function rcFixture(string $name, Family $family, array $overrides = [], int $expiresInDays = 30): array
{
    /** @var array{api_version: string, event: array<string, mixed>} $payload */
    $payload = json_decode((string) file_get_contents(base_path("tests/Fixtures/revenuecat/{$name}.json")), true);
    $appUserId = 'family-'.$family->id;

    $event = [
        ...$payload['event'],
        'app_user_id' => $appUserId,
        'aliases' => [$appUserId],
        'event_timestamp_ms' => now()->getTimestampMs(),
        'purchased_at_ms' => now()->subMinute()->getTimestampMs(),
        'expiration_at_ms' => now()->addDays($expiresInDays)->getTimestampMs(),
    ];

    if (array_key_exists('grace_period_expiration_at_ms', $event)) {
        $event['grace_period_expiration_at_ms'] = now()->addDays(7)->getTimestampMs();
    }

    return [...$payload, 'event' => [...$event, ...$overrides]];
}

/**
 * @param  array<string, mixed>  $payload
 */
function postWebhook(array $payload, string $secret = 'rc-test-secret'): TestResponse
{
    return test()->postJson('/api/v1/webhooks/revenuecat', $payload, ['Authorization' => 'Bearer '.$secret]);
}

beforeEach(function () {
    config(['services.revenuecat.webhook_secret' => 'rc-test-secret']);
    $this->owner = familyOwner();
    $this->family = $this->owner->family;
});

it('upgrades the family on an initial purchase', function () {
    postWebhook(rcFixture('initial_purchase', $this->family))
        ->assertOk()
        ->assertJsonPath('data.outcome', WebhookEvent::OUTCOME_APPLIED);

    expect($this->family->fresh()->tier)->toBe(Family::TIER_FAMILY);

    $subscription = Subscription::query()->where('family_id', $this->family->id)->sole();
    expect($subscription->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($subscription->store)->toBe('app_store')
        ->and($subscription->product_id)->toBe('brainy_family_monthly')
        ->and($subscription->period_type)->toBe('normal')
        ->and($subscription->will_renew)->toBeTrue()
        ->and($subscription->app_user_id)->toBe('family-'.$this->family->id)
        ->and($subscription->expires_at?->isFuture())->toBeTrue();

    expect(WebhookEvent::query()->sole())
        ->family_id->toBe($this->family->id)
        ->outcome->toBe(WebhookEvent::OUTCOME_APPLIED)
        ->processed_at->not->toBeNull();
});

it('treats a redelivered event as a no-op', function () {
    $payload = rcFixture('initial_purchase', $this->family);
    postWebhook($payload)->assertOk();

    // The admin downgrades by hand in between; the duplicate must not re-apply.
    Family::query()->whereKey($this->family->id)->update(['tier' => Family::TIER_FREE]);

    postWebhook($payload)->assertOk()->assertJsonPath('data.outcome', 'duplicate');

    expect(WebhookEvent::query()->count())->toBe(1)
        ->and($this->family->fresh()->tier)->toBe(Family::TIER_FREE);
});

it('downgrades the family on expiration', function () {
    postWebhook(rcFixture('initial_purchase', $this->family))->assertOk();
    $this->travel(31)->days();

    postWebhook(rcFixture('expiration', $this->family, expiresInDays: -1))->assertOk();

    expect($this->family->fresh()->tier)->toBe(Family::TIER_FREE);
    expect(Subscription::query()->sole())
        ->status->toBe(Subscription::STATUS_EXPIRED)
        ->will_renew->toBeFalse();
});

it('keeps the family tier after a cancellation until expiry', function () {
    postWebhook(rcFixture('initial_purchase', $this->family))->assertOk();
    $this->travel(1)->hours();

    postWebhook(rcFixture('cancellation', $this->family, expiresInDays: 20))->assertOk();

    expect($this->family->fresh()->tier)->toBe(Family::TIER_FAMILY);
    expect(Subscription::query()->sole())
        ->status->toBe(Subscription::STATUS_ACTIVE)
        ->will_renew->toBeFalse();
});

it('keeps the family tier during the billing grace period, and an uncancellation reactivates', function () {
    postWebhook(rcFixture('initial_purchase', $this->family))->assertOk();
    $this->travel(1)->hours();

    postWebhook(rcFixture('billing_issue', $this->family, expiresInDays: 0))->assertOk();

    expect($this->family->fresh()->tier)->toBe(Family::TIER_FAMILY);
    $subscription = Subscription::query()->sole();
    expect($subscription->status)->toBe(Subscription::STATUS_GRACE)
        ->and($subscription->store)->toBe('play_store')
        ->and($subscription->grace_expires_at?->isFuture())->toBeTrue();

    $this->travel(1)->hours();
    postWebhook(rcFixture('initial_purchase', $this->family, ['id' => 'renewal-1', 'type' => 'RENEWAL']))->assertOk();

    expect(Subscription::query()->sole())->status->toBe(Subscription::STATUS_ACTIVE)->grace_expires_at->toBeNull();
});

it('records an out-of-order older event as stale', function () {
    postWebhook(rcFixture('initial_purchase', $this->family))->assertOk();

    postWebhook(rcFixture('expiration', $this->family, ['event_timestamp_ms' => now()->subDay()->getTimestampMs()]))
        ->assertOk()
        ->assertJsonPath('data.outcome', WebhookEvent::OUTCOME_STALE);

    expect($this->family->fresh()->tier)->toBe(Family::TIER_FAMILY);
});

it('rejects a wrong or missing secret', function () {
    postWebhook(rcFixture('initial_purchase', $this->family), 'wrong')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
    $this->postJson('/api/v1/webhooks/revenuecat', rcFixture('initial_purchase', $this->family))->assertUnauthorized();

    config(['services.revenuecat.webhook_secret' => '']);
    postWebhook(rcFixture('initial_purchase', $this->family), '')->assertUnauthorized();

    expect(WebhookEvent::query()->count())->toBe(0)
        ->and($this->family->fresh()->tier)->toBe(Family::TIER_FREE);
});

it('acknowledges but ignores unknown families, other entitlements and unhandled types', function () {
    postWebhook(rcFixture('initial_purchase', $this->family, ['app_user_id' => 'family-999999', 'aliases' => ['family-999999'], 'original_app_user_id' => '$RCAnonymousID:x']))
        ->assertOk()
        ->assertJsonPath('data.outcome', WebhookEvent::OUTCOME_IGNORED);
    postWebhook(rcFixture('initial_purchase', $this->family, ['id' => 'other-ent', 'entitlement_ids' => ['pro']]))
        ->assertJsonPath('data.outcome', WebhookEvent::OUTCOME_IGNORED);
    postWebhook(rcFixture('initial_purchase', $this->family, ['id' => 'test-evt', 'type' => 'TEST']))
        ->assertJsonPath('data.outcome', WebhookEvent::OUTCOME_IGNORED);

    expect(WebhookEvent::query()->count())->toBe(3)
        ->and(Subscription::query()->count())->toBe(0)
        ->and($this->family->fresh()->tier)->toBe(Family::TIER_FREE);
});

it('finds the family through an alias when app_user_id is anonymous', function () {
    postWebhook(rcFixture('initial_purchase', $this->family, ['app_user_id' => '$RCAnonymousID:abc']))->assertOk();

    expect($this->family->fresh()->tier)->toBe(Family::TIER_FAMILY);
});

it('validates the webhook body', function () {
    postWebhook(['api_version' => '1.0', 'event' => ['type' => 'INITIAL_PURCHASE']])->assertUnprocessable();
});

it('shows the family subscription to admins', function () {
    actingAsMember($this->owner);

    $this->getJson('/api/v1/family/subscription')
        ->assertOk()
        ->assertExactJson(['data' => [
            'tier' => Family::TIER_FREE,
            'status' => Subscription::STATUS_NONE,
            'store' => null,
            'product_id' => null,
            'expires_at' => null,
            'will_renew' => false,
            'revenuecat_app_user_id' => 'family-'.$this->family->id,
        ]]);

    postWebhook(rcFixture('initial_purchase', $this->family))->assertOk();
    app('auth')->forgetGuards();
    actingAsMember($this->owner->fresh() ?? $this->owner);

    DB::enableQueryLog();
    $this->getJson('/api/v1/family/subscription')
        ->assertOk()
        ->assertJsonPath('data.tier', Family::TIER_FAMILY)
        ->assertJsonPath('data.status', Subscription::STATUS_ACTIVE)
        ->assertJsonPath('data.store', 'app_store')
        ->assertJsonPath('data.product_id', 'brainy_family_monthly')
        ->assertJsonPath('data.will_renew', true);
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(8);
});

it('hides the family subscription from non-admins', function () {
    actingAsMember(addMember($this->family, 9, FamilyMember::ROLE_MEMBER));

    $this->getJson('/api/v1/family/subscription')->assertForbidden();
});

it('reports a non-store provider as a null store', function () {
    Subscription::factory()->create(['family_id' => $this->family->id, 'store' => 'promotional']);
    actingAsMember($this->owner);

    $this->getJson('/api/v1/family/subscription')->assertOk()->assertJsonPath('data.store', null);
});
