<?php

use App\Modules\Billing\Actions\ReconcileSubscriptions;
use App\Modules\Billing\Http\Requests\PaddleWebhookRequest;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\WebhookEvent;
use App\Modules\Family\Models\Family;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Paddle Billing webhook `POST /api/v1/webhooks/paddle` (docs/API.md "Web checkout (Paddle)").
 */
const PADDLE_TEST_SECRET = 'pdl_ntfset_test_secret';

/**
 * A Paddle subscription entity for this family.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function paddleSubscription(Family $family, array $overrides = []): array
{
    return [
        'id' => 'sub_01family'.$family->id,
        'status' => 'active',
        'customer_id' => 'ctm_01family'.$family->id,
        'custom_data' => ['family_id' => $family->id],
        'currency_code' => 'USD',
        'started_at' => now()->subMinute()->toIso8601String(),
        'first_billed_at' => now()->subMinute()->toIso8601String(),
        'next_billed_at' => now()->addYear()->toIso8601String(),
        'current_billing_period' => ['starts_at' => now()->subMinute()->toIso8601String(), 'ends_at' => now()->addYear()->toIso8601String()],
        'scheduled_change' => null,
        'canceled_at' => null,
        'items' => [['price' => ['id' => 'pri_annual'], 'quantity' => 1]],
        ...$overrides,
    ];
}

/**
 * A completed Paddle transaction (US$49.99, no tax, fee 5% + 50¢ = 300, earnings 4699).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function paddleTransaction(Family $family, array $overrides = []): array
{
    return [
        'id' => 'txn_01family'.$family->id,
        'status' => 'completed',
        'customer_id' => 'ctm_01family'.$family->id,
        'subscription_id' => 'sub_01family'.$family->id,
        'origin' => 'web',
        'custom_data' => ['family_id' => $family->id],
        'currency_code' => 'USD',
        'billed_at' => now()->toIso8601String(),
        'details' => [
            'totals' => ['subtotal' => '4999', 'discount' => '0', 'tax' => '0', 'total' => '4999', 'fee' => '300', 'earnings' => '4699', 'currency_code' => 'USD'],
            'payout_totals' => ['subtotal' => '4999', 'tax' => '0', 'total' => '4999', 'fee' => '300', 'earnings' => '4699', 'currency_code' => 'USD'],
        ],
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function paddleEvent(string $type, array $data, ?string $occurredAt = null, ?string $eventId = null): array
{
    return [
        'event_id' => $eventId ?? 'evt_'.Str::lower(Str::random(20)),
        'event_type' => $type,
        'occurred_at' => $occurredAt ?? now()->toIso8601String(),
        'notification_id' => 'ntf_'.Str::lower(Str::random(20)),
        'data' => $data,
    ];
}

/**
 * Posts the event signed like Paddle: `Paddle-Signature: ts=…;h1=HMAC-SHA256("ts:body")`.
 *
 * @param  array<string, mixed>  $payload
 */
function postPaddle(array $payload, ?int $timestamp = null, string $secret = PADDLE_TEST_SECRET): TestResponse
{
    $body = (string) json_encode($payload);
    $timestamp ??= time();
    $signature = hash_hmac('sha256', $timestamp.':'.$body, $secret);

    return test()->call('POST', '/api/v1/webhooks/paddle', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_PADDLE_SIGNATURE' => "ts={$timestamp};h1={$signature}",
    ], $body);
}

beforeEach(function () {
    config([
        'billing.paddle.webhook_secret' => PADDLE_TEST_SECRET,
        'billing.paddle.api_key' => 'pdl_test_key',
        'billing.paddle.environment' => 'sandbox',
    ]);
    $this->owner = familyOwner();
    $this->family = $this->owner->family;
});

it('verifies the Paddle-Signature header', function () {
    $payload = paddleEvent('subscription.created', paddleSubscription($this->family));

    postPaddle($payload, secret: 'wrong')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    postPaddle($payload, timestamp: time() - 301)->assertUnauthorized();
    postPaddle($payload, timestamp: time() + 301)->assertUnauthorized();
    $this->postJson('/api/v1/webhooks/paddle', $payload)->assertUnauthorized();

    expect(WebhookEvent::query()->count())->toBe(0)
        ->and(DB::table('webhook_rejections')->where('provider', 'paddle')->where('reason', 'signature_invalid')->value('hits'))->toBe(4);

    postPaddle($payload, timestamp: time() - 290)->assertOk()->assertJsonPath('data.outcome', WebhookEvent::OUTCOME_APPLIED);
});

it('accepts any of several h1 signatures (secret rotation) and rejects malformed headers', function () {
    $body = '{"event_id":"evt_1"}';
    $ts = 1_760_000_000;
    $good = hash_hmac('sha256', "{$ts}:{$body}", 'secret');

    expect(PaddleWebhookRequest::signatureIsValid("ts={$ts};h1=deadbeef;h1={$good}", $body, 'secret', 300, $ts))->toBeTrue()
        ->and(PaddleWebhookRequest::signatureIsValid("ts={$ts};h1={$good}", $body.' ', 'secret', 300, $ts))->toBeFalse()
        ->and(PaddleWebhookRequest::signatureIsValid("h1={$good}", $body, 'secret', 300, $ts))->toBeFalse()
        ->and(PaddleWebhookRequest::signatureIsValid("ts={$ts};h1={$good}", $body, '', 300, $ts))->toBeFalse();
});

it('rejects a signed event without event_id or data with 422', function () {
    postPaddle(['event_type' => 'subscription.created', 'data' => []])->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
});

it('activates the Family plan on subscription.created and stores the Paddle ids', function () {
    postPaddle(paddleEvent('subscription.created', paddleSubscription($this->family)))
        ->assertOk()
        ->assertJsonPath('data.outcome', WebhookEvent::OUTCOME_APPLIED);

    expect($this->family->fresh()->tier)->toBe(Family::TIER_FAMILY);

    $subscription = Subscription::query()->where('family_id', $this->family->id)->sole();
    expect($subscription->provider)->toBe(Subscription::PROVIDER_PADDLE)
        ->and($subscription->store)->toBe('paddle')
        ->and($subscription->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($subscription->external_id)->toBe('sub_01family'.$this->family->id)
        ->and($subscription->customer_id)->toBe('ctm_01family'.$this->family->id)
        ->and($subscription->product_id)->toBe('pri_annual')
        ->and($subscription->environment)->toBe('SANDBOX')
        ->and($subscription->will_renew)->toBeTrue()
        ->and($subscription->expires_at?->isFuture())->toBeTrue();

    actingAsMember($this->owner);
    $this->getJson('/api/v1/family/subscription')->assertOk()
        ->assertJsonPath('data.store', 'paddle')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.tier', 'family');
});

it('maps every subscription status to the shared states', function (string $type, array $overrides, string $status, bool $entitled, bool $willRenew, ?string $periodType) {
    postPaddle(paddleEvent($type, paddleSubscription($this->family, $overrides)))->assertOk();

    $subscription = Subscription::query()->where('family_id', $this->family->id)->sole();
    expect($subscription->status)->toBe($status)
        ->and($subscription->isEntitled())->toBe($entitled)
        ->and($subscription->will_renew)->toBe($willRenew)
        ->and($this->family->fresh()->tier)->toBe($entitled ? Family::TIER_FAMILY : Family::TIER_FREE);

    if ($periodType !== null) {
        expect($subscription->period_type)->toBe($periodType);
    }
})->with([
    'trialing' => ['subscription.created', ['status' => 'trialing'], Subscription::STATUS_ACTIVE, true, true, 'trial'],
    'activated' => ['subscription.activated', [], Subscription::STATUS_ACTIVE, true, true, 'normal'],
    'cancel scheduled' => ['subscription.updated', ['scheduled_change' => ['action' => 'cancel', 'effective_at' => now()->addYear()->toIso8601String()]], Subscription::STATUS_ACTIVE, true, false, null],
    'past_due → grace' => ['subscription.past_due', ['status' => 'past_due', 'current_billing_period' => ['starts_at' => now()->subYear()->toIso8601String(), 'ends_at' => now()->subDay()->toIso8601String()]], Subscription::STATUS_GRACE, true, true, null],
    'paused' => ['subscription.paused', ['status' => 'paused', 'current_billing_period' => null, 'paused_at' => now()->toIso8601String()], Subscription::STATUS_EXPIRED, false, false, null],
    'canceled' => ['subscription.canceled', ['status' => 'canceled', 'current_billing_period' => null, 'next_billed_at' => null, 'canceled_at' => now()->toIso8601String()], Subscription::STATUS_EXPIRED, false, false, null],
    'resumed' => ['subscription.resumed', [], Subscription::STATUS_ACTIVE, true, true, null],
]);

it('is idempotent by event_id', function () {
    $payload = paddleEvent('subscription.created', paddleSubscription($this->family), eventId: 'evt_same');

    postPaddle($payload)->assertOk()->assertJsonPath('data.outcome', WebhookEvent::OUTCOME_APPLIED);
    postPaddle($payload)->assertOk()->assertJsonPath('data.outcome', 'duplicate')->assertJsonPath('data.event_id', 'evt_same');

    expect(WebhookEvent::query()->where('provider', 'paddle')->count())->toBe(1);
});

it('records an older event delivered late as stale', function () {
    postPaddle(paddleEvent('subscription.canceled', paddleSubscription($this->family, ['status' => 'canceled', 'current_billing_period' => null, 'canceled_at' => now()->toIso8601String()])))->assertOk();
    postPaddle(paddleEvent('subscription.updated', paddleSubscription($this->family), now()->subHour()->toIso8601String()))
        ->assertOk()
        ->assertJsonPath('data.outcome', WebhookEvent::OUTCOME_STALE);

    expect(Subscription::query()->sole()->status)->toBe(Subscription::STATUS_EXPIRED)
        ->and($this->family->fresh()->tier)->toBe(Family::TIER_FREE);
});

it('finds the family by the stored Paddle subscription id when custom_data is missing', function () {
    postPaddle(paddleEvent('subscription.created', paddleSubscription($this->family)))->assertOk();
    postPaddle(paddleEvent('subscription.canceled', paddleSubscription($this->family, ['custom_data' => null, 'status' => 'canceled', 'current_billing_period' => null, 'canceled_at' => now()->toIso8601String()])))
        ->assertOk()->assertJsonPath('data.outcome', WebhookEvent::OUTCOME_APPLIED);

    expect($this->family->fresh()->tier)->toBe(Family::TIER_FREE);
});

it('ignores unknown families and unhandled event types with 200', function () {
    postPaddle(paddleEvent('subscription.created', paddleSubscription($this->family, ['id' => 'sub_other', 'custom_data' => ['family_id' => 999999]])))
        ->assertOk()->assertJsonPath('data.outcome', WebhookEvent::OUTCOME_IGNORED);
    postPaddle(paddleEvent('customer.updated', ['id' => 'ctm_1']))
        ->assertOk()->assertJsonPath('data.outcome', WebhookEvent::OUTCOME_IGNORED);
    postPaddle(paddleEvent('adjustment.created', ['id' => 'adj_1', 'action' => 'refund', 'status' => 'pending_approval']))
        ->assertOk()->assertJsonPath('data.outcome', WebhookEvent::OUTCOME_IGNORED);

    expect($this->family->fresh()->tier)->toBe(Family::TIER_FREE);
});

it('does not let an old Paddle subscription ending cancel the family’s newer one', function () {
    postPaddle(paddleEvent('subscription.created', paddleSubscription($this->family, ['id' => 'sub_new'])))->assertOk();
    postPaddle(paddleEvent('subscription.canceled', paddleSubscription($this->family, ['id' => 'sub_old', 'status' => 'canceled', 'current_billing_period' => null])))
        ->assertOk()->assertJsonPath('data.outcome', WebhookEvent::OUTCOME_IGNORED);

    expect($this->family->fresh()->tier)->toBe(Family::TIER_FAMILY);
});

it('keeps a store plan and a web plan side by side and the tier while either is active', function () {
    Subscription::factory()->create(['family_id' => $this->family->id]);
    $this->family->forceFill(['tier' => Family::TIER_FAMILY])->save();

    postPaddle(paddleEvent('subscription.created', paddleSubscription($this->family)))
        ->assertOk()
        ->assertJsonPath('data.outcome', WebhookEvent::OUTCOME_APPLIED);

    expect(Subscription::query()->where('family_id', $this->family->id)->count())->toBe(2)
        ->and(WebhookEvent::query()->where('provider', 'paddle')->sole()->note)->toContain('Conflict');

    // The web plan ends: the store plan still holds the tier.
    postPaddle(paddleEvent('subscription.canceled', paddleSubscription($this->family, ['status' => 'canceled', 'current_billing_period' => null])))->assertOk();
    expect($this->family->fresh()->tier)->toBe(Family::TIER_FAMILY);

    // A late RevenueCat EXPIRATION only ends the store row.
    Subscription::query()->where('provider', 'revenuecat')->update(['status' => Subscription::STATUS_EXPIRED]);
    postPaddle(paddleEvent('subscription.resumed', paddleSubscription($this->family)))->assertOk();
    expect($this->family->fresh()->tier)->toBe(Family::TIER_FAMILY);
});

it('never lets a RevenueCat event touch the Paddle row', function () {
    config(['services.revenuecat.webhook_secret' => 'rc-test-secret']);
    postPaddle(paddleEvent('subscription.created', paddleSubscription($this->family)))->assertOk();

    $this->postJson('/api/v1/webhooks/revenuecat', ['api_version' => '1.0', 'event' => [
        'id' => 'rc-expire-1', 'type' => 'EXPIRATION', 'app_user_id' => 'family-'.$this->family->id,
        'event_timestamp_ms' => now()->getTimestampMs(), 'expiration_at_ms' => now()->subDay()->getTimestampMs(),
        'store' => 'APP_STORE', 'entitlement_ids' => ['family'],
    ]], ['Authorization' => 'Bearer rc-test-secret'])->assertOk();

    expect(Subscription::query()->where('provider', 'paddle')->sole()->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($this->family->fresh()->tier)->toBe(Family::TIER_FAMILY);
});

it('reconciles an overdue Paddle subscription from the Paddle API', function () {
    $subscription = Subscription::factory()->paddle()->create([
        'family_id' => $this->family->id,
        'external_id' => 'sub_overdue',
        'expires_at' => now()->subHours(3),
    ]);

    Http::fake(['sandbox-api.paddle.com/subscriptions/sub_overdue' => Http::response(['data' => paddleSubscription($this->family, [
        'id' => 'sub_overdue', 'status' => 'canceled', 'current_billing_period' => null, 'canceled_at' => now()->subHours(3)->toIso8601String(),
    ])])]);

    expect(app(ReconcileSubscriptions::class)->handle())->toBe(1)
        ->and($subscription->fresh()->status)->toBe(Subscription::STATUS_EXPIRED)
        ->and($subscription->fresh()->last_event_type)->toBe('paddle.sync')
        ->and($this->family->fresh()->tier)->toBe(Family::TIER_FREE);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer pdl_test_key'));
});

it('expires an overdue Paddle subscription without an API key', function () {
    config(['billing.paddle.api_key' => null]);
    $this->family->forceFill(['tier' => Family::TIER_FAMILY])->save();
    Subscription::factory()->paddle()->create(['family_id' => $this->family->id, 'expires_at' => now()->subHours(3)]);

    expect(app(ReconcileSubscriptions::class)->handle())->toBe(1)
        ->and($this->family->fresh()->tier)->toBe(Family::TIER_FREE);
});
