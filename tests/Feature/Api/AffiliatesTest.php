<?php

use App\Modules\Billing\Actions\RecordRevenue;
use App\Modules\Billing\Actions\ReleasePayableCommissions;
use App\Modules\Billing\Events\RevenueRecorded;
use App\Modules\Billing\Models\Affiliate;
use App\Modules\Billing\Models\AffiliateCode;
use App\Modules\Billing\Models\CommissionEntry;
use App\Modules\Billing\Models\FamilyAttribution;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Referral codes (`POST /family/referral-code`, Family `referral_code`), attribution from store
 * offer codes and Paddle checkouts, and the commission ledger (docs/API.md "Referral / affiliate
 * codes (B10)", docs/PLAN.md B10).
 */
function rcRevenueEvent(Family $family, array $overrides = []): array
{
    $appUserId = 'family-'.$family->id;

    return ['api_version' => '1.0', 'event' => [
        'id' => (string) Str::uuid(),
        'type' => 'INITIAL_PURCHASE',
        'app_user_id' => $appUserId,
        'aliases' => [$appUserId],
        'product_id' => 'brainyclan_family_annual',
        'entitlement_ids' => ['family'],
        'period_type' => 'NORMAL',
        'store' => 'APP_STORE',
        'environment' => 'PRODUCTION',
        'event_timestamp_ms' => now()->getTimestampMs(),
        'purchased_at_ms' => now()->getTimestampMs(),
        'expiration_at_ms' => now()->addYear()->getTimestampMs(),
        'currency' => 'CAD',
        'price' => 47.0,
        'price_in_purchased_currency' => 64.99,
        'tax_percentage' => 0.13,
        'commission_percentage' => 0.15,
        'offer_code' => null,
        'transaction_id' => 'store-txn-'.Str::random(8),
        ...$overrides,
    ]];
}

function postRevenueCat(array $payload): TestResponse
{
    return test()->postJson('/api/v1/webhooks/revenuecat', $payload, ['Authorization' => 'Bearer rc-test-secret']);
}

/**
 * A Paddle sale for the family (USD 49.99, fee 300, earnings 4699), signed.
 */
function paddleSale(Family $family, array $customData = [], ?string $transactionId = null, int $total = 4999, int $fee = 300): TestResponse
{
    $payload = [
        'event_id' => 'evt_'.Str::lower(Str::random(16)),
        'event_type' => 'transaction.completed',
        'occurred_at' => now()->toIso8601String(),
        'data' => [
            'id' => $transactionId ?? 'txn_'.Str::lower(Str::random(10)),
            'status' => 'completed',
            'subscription_id' => 'sub_aff'.$family->id,
            'custom_data' => ['family_id' => $family->id, ...$customData],
            'billed_at' => now()->toIso8601String(),
            'details' => ['totals' => ['total' => (string) $total, 'tax' => '0', 'fee' => (string) $fee, 'earnings' => (string) ($total - $fee), 'currency_code' => 'USD']],
        ],
    ];

    return signedPaddlePost($payload);
}

function signedPaddlePost(array $payload): TestResponse
{
    $body = (string) json_encode($payload);
    $ts = time();

    return test()->call('POST', '/api/v1/webhooks/paddle', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_PADDLE_SIGNATURE' => "ts={$ts};h1=".hash_hmac('sha256', "{$ts}:{$body}", 'aff-paddle-secret'),
    ], $body);
}

function paddleAdjustment(string $action, string $status, string $transactionId, int $total = 4999, int $fee = 300): TestResponse
{
    return signedPaddlePost([
        'event_id' => 'evt_'.Str::lower(Str::random(16)),
        'event_type' => 'adjustment.updated',
        'occurred_at' => now()->toIso8601String(),
        'data' => [
            'id' => 'adj_'.$transactionId.$action,
            'action' => $action,
            'status' => $status,
            'transaction_id' => $transactionId,
            'subscription_id' => null,
            'totals' => ['total' => (string) $total, 'tax' => '0', 'fee' => (string) $fee, 'earnings' => (string) ($total - $fee), 'currency_code' => 'USD'],
        ],
    ]);
}

beforeEach(function () {
    config([
        'services.revenuecat.webhook_secret' => 'rc-test-secret',
        'billing.paddle.webhook_secret' => 'aff-paddle-secret',
    ]);
    $this->owner = familyOwner();
    $this->family = $this->owner->family;
    $this->affiliate = Affiliate::factory()->create(['name' => 'Maya', 'commission_rate' => '0.2500', 'commission_months' => 12]);
    $this->code = AffiliateCode::factory()->create(['affiliate_id' => $this->affiliate->id, 'code' => 'MAYA', 'store_offer_codes' => ['FOUNDING-MAYA']]);
});

it('lets a family admin set an active code once, case-insensitively', function () {
    actingAsMember($this->owner);

    $this->postJson('/api/v1/family/referral-code', ['code' => ' maya '])->assertOk()
        ->assertExactJson(['data' => ['code' => 'MAYA', 'source' => 'code']]);

    expect(FamilyAttribution::query()->sole())
        ->family_id->toBe($this->family->id)
        ->affiliate_id->toBe($this->affiliate->id)
        ->source->toBe('code')
        ->locked_at->toBeNull();

    $this->postJson('/api/v1/family/referral-code', ['code' => 'MAYA'])->assertStatus(409)->assertJsonPath('code', 'referral_code_locked');
});

it('rejects unknown, inactive, malformed and paused-affiliate codes', function () {
    actingAsMember($this->owner);
    AffiliateCode::factory()->create(['code' => 'OFF', 'active' => false]);
    $paused = Affiliate::factory()->create(['status' => Affiliate::STATUS_PAUSED]);
    AffiliateCode::factory()->create(['code' => 'PAUSED', 'affiliate_id' => $paused->id]);

    foreach (['NOPE', 'off', 'PAUSED', 'bad code!', str_repeat('A', 21)] as $code) {
        $this->postJson('/api/v1/family/referral-code', ['code' => $code])->assertUnprocessable()
            ->assertJsonPath('code', 'referral_code_invalid')
            ->assertJsonStructure(['errors' => ['code']]);
    }

    $this->postJson('/api/v1/family/referral-code', [])->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
    expect(FamilyAttribution::query()->count())->toBe(0);
});

it('locks the code once the family has paid', function () {
    $this->family->forceFill(['first_paid_at' => now()->subDay()])->save();
    actingAsMember($this->owner);

    $this->postJson('/api/v1/family/referral-code', ['code' => 'MAYA'])->assertStatus(409)->assertJsonPath('code', 'referral_code_locked');
});

it('keeps the referral code admin-only, also in the Family object', function () {
    actingAsMember($this->owner);
    $this->postJson('/api/v1/family/referral-code', ['code' => 'maya'])->assertOk();

    $this->getJson('/api/v1/family')->assertOk()->assertJsonPath('data.family.referral_code', 'MAYA');
    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.family.referral_code', 'MAYA');

    $member = addMember($this->family, 35);
    actingAsMember($member);
    $this->getJson('/api/v1/family')->assertOk()->assertJsonPath('data.family.referral_code', null);
    $this->postJson('/api/v1/family/referral-code', ['code' => 'MAYA'])->assertForbidden();

    $other = familyOwner();
    actingAsMember($other);
    $this->getJson('/api/v1/family')->assertOk()->assertJsonPath('data.family.referral_code', null);
});

it('attributes a store purchase with a mapped offer code and pays commission on net', function () {
    postRevenueCat(rcRevenueEvent($this->family, ['offer_code' => 'founding-maya']))->assertOk();

    $attribution = FamilyAttribution::query()->sole();
    expect($attribution->source)->toBe(FamilyAttribution::SOURCE_OFFER_CODE)
        ->and($attribution->affiliate_code_id)->toBe($this->code->id)
        ->and($attribution->locked_at)->not->toBeNull()
        ->and($this->family->fresh()->first_paid_at)->not->toBeNull();

    // CA$64.99: tax 13% = 8.45, store fee 15% of 56.54 = 8.48, net 48.06, commission 25% = 12.02.
    $entry = CommissionEntry::query()->sole();
    expect($entry)
        ->source->toBe('revenuecat')
        ->kind->toBe('sale')
        ->currency->toBe('CAD')
        ->gross_minor->toBe(6499)
        ->tax_minor->toBe(845)
        ->fee_minor->toBe(848)
        ->net_minor->toBe(4806)
        ->commission_rate->toBe('0.2500')
        ->commission_minor->toBe(1202)
        ->status->toBe(CommissionEntry::STATUS_PENDING)
        ->and($entry->net_usd_minor)->toBe(3476) // US$47: 4700 − 611 − 613
        ->and($entry->commission_usd_minor)->toBe(869)
        ->and($entry->payable_at->diffInDays($entry->occurred_at, true))->toEqual(30);
});

it('uses the configured store fee when RevenueCat sends no commission percentage', function () {
    actingAsMember($this->owner);
    $this->postJson('/api/v1/family/referral-code', ['code' => 'MAYA'])->assertOk();

    postRevenueCat(rcRevenueEvent($this->family, ['currency' => 'USD', 'price' => 49.99, 'price_in_purchased_currency' => 49.99, 'tax_percentage' => null, 'commission_percentage' => null]))->assertOk();

    expect(CommissionEntry::query()->sole())
        ->gross_minor->toBe(4999)
        ->tax_minor->toBe(0)
        ->fee_minor->toBe(750)
        ->net_minor->toBe(4249)
        ->commission_minor->toBe(1062);
});

it('ignores offer codes for families that already have a code (first touch) and unmapped offer codes', function () {
    $other = AffiliateCode::factory()->create(['code' => 'OTHER', 'store_offer_codes' => ['OTHER-OFFER']]);
    actingAsMember($this->owner);
    $this->postJson('/api/v1/family/referral-code', ['code' => 'MAYA'])->assertOk();

    postRevenueCat(rcRevenueEvent($this->family, ['offer_code' => 'OTHER-OFFER']))->assertOk();
    expect(FamilyAttribution::query()->sole()->affiliate_code_id)->toBe($this->code->id)
        ->and(CommissionEntry::query()->sole()->affiliate_id)->toBe($this->affiliate->id);

    $second = familyOwner();
    postRevenueCat(rcRevenueEvent($second->family, ['offer_code' => 'UNKNOWN']))->assertOk();
    expect(FamilyAttribution::query()->where('family_id', $second->family_id)->exists())->toBeFalse()
        ->and($second->family->fresh()->first_paid_at)->not->toBeNull()
        ->and($other->attributions()->count())->toBe(0);
});

it('attributes a free trial with an offer code without commission', function () {
    postRevenueCat(rcRevenueEvent($this->family, ['period_type' => 'TRIAL', 'price' => 0, 'price_in_purchased_currency' => 0, 'offer_code' => 'FOUNDING-MAYA']))->assertOk();

    expect(FamilyAttribution::query()->sole()->locked_at)->toBeNull()
        ->and(CommissionEntry::query()->count())->toBe(0)
        ->and($this->family->fresh()->first_paid_at)->toBeNull();
});

it('attributes a web checkout from custom_data and records Paddle earnings', function () {
    paddleSale($this->family, ['affiliate_code' => 'MAYA', 'affiliate_source' => 'link'], 'txn_web1')->assertOk();

    expect(FamilyAttribution::query()->sole())
        ->source->toBe(FamilyAttribution::SOURCE_LINK)
        ->locked_at->not->toBeNull();

    expect(CommissionEntry::query()->sole())
        ->source->toBe('paddle')
        ->source_reference->toBe('txn_web1')
        ->gross_minor->toBe(4999)
        ->fee_minor->toBe(300)
        ->net_minor->toBe(4699)
        ->commission_minor->toBe(1175)
        ->commission_usd_minor->toBe(1175);

    $second = familyOwner();
    paddleSale($second->family, ['affiliate_code' => 'maya'])->assertOk();
    expect(FamilyAttribution::query()->where('family_id', $second->family_id)->sole()->source)->toBe(FamilyAttribution::SOURCE_WEB_CHECKOUT);
});

it('adds negative entries for Paddle refunds and chargebacks, once, mirroring the sale', function () {
    paddleSale($this->family, ['affiliate_code' => 'MAYA'], 'txn_ref')->assertOk();

    paddleAdjustment('refund', 'pending_approval', 'txn_ref')->assertOk()->assertJsonPath('data.outcome', 'ignored');
    paddleAdjustment('refund', 'approved', 'txn_ref')->assertOk()->assertJsonPath('data.outcome', 'applied');
    paddleAdjustment('refund', 'approved', 'txn_ref')->assertOk();

    $refund = CommissionEntry::query()->where('kind', 'refund')->sole();
    expect($refund->gross_minor)->toBe(-4999)
        ->and($refund->net_minor)->toBe(-4699)
        ->and($refund->commission_minor)->toBe(-1175)
        ->and($refund->affiliate_id)->toBe($this->affiliate->id)
        ->and(CommissionEntry::query()->sum('commission_minor'))->toEqual(0);

    paddleAdjustment('chargeback', 'approved', 'txn_ref')->assertOk();
    paddleAdjustment('chargeback_reverse', 'approved', 'txn_ref')->assertOk();
    expect(CommissionEntry::query()->where('kind', 'chargeback')->sole()->commission_minor)->toBe(-1175)
        ->and(CommissionEntry::query()->where('kind', 'chargeback_reverse')->sole()->commission_minor)->toBe(1175);

    // A refund of a sale that earned no commission adds nothing.
    paddleAdjustment('refund', 'approved', 'txn_unknown')->assertOk();
    expect(CommissionEntry::query()->count())->toBe(4);
});

it('adds a negative entry for a RevenueCat refund', function () {
    postRevenueCat(rcRevenueEvent($this->family, ['offer_code' => 'FOUNDING-MAYA', 'transaction_id' => 'store-1']))->assertOk();
    postRevenueCat(rcRevenueEvent($this->family, [
        'type' => 'CANCELLATION', 'cancel_reason' => 'CUSTOMER_SUPPORT', 'transaction_id' => 'store-1',
        'price' => -47.0, 'price_in_purchased_currency' => -64.99,
    ]))->assertOk();

    $refund = CommissionEntry::query()->where('kind', 'refund')->sole();
    expect($refund->gross_minor)->toBe(-6499)
        ->and($refund->commission_minor)->toBe(-1202)
        ->and(CommissionEntry::query()->sum('commission_minor'))->toEqual(0);
});

it('pays commission only within commission_months of the first paid transaction', function () {
    $this->affiliate->update(['commission_months' => 2, 'commission_rate' => '0.1000']);
    actingAsMember($this->owner);
    $this->postJson('/api/v1/family/referral-code', ['code' => 'MAYA'])->assertOk();

    postRevenueCat(rcRevenueEvent($this->family, ['type' => 'INITIAL_PURCHASE', 'currency' => 'USD', 'price' => 6.99, 'price_in_purchased_currency' => 6.99, 'tax_percentage' => 0, 'commission_percentage' => 0.15]))->assertOk();

    $this->travel(35)->days();
    postRevenueCat(rcRevenueEvent($this->family, ['type' => 'RENEWAL', 'currency' => 'USD', 'price' => 6.99, 'price_in_purchased_currency' => 6.99, 'tax_percentage' => 0, 'commission_percentage' => 0.15]))->assertOk();

    $this->travel(30)->days();
    postRevenueCat(rcRevenueEvent($this->family, ['type' => 'RENEWAL', 'currency' => 'USD', 'price' => 6.99, 'price_in_purchased_currency' => 6.99, 'tax_percentage' => 0, 'commission_percentage' => 0.15]))->assertOk();

    // 699 − 105 fee = 594 net → 10% = 59; the third payment is past the 2-month window.
    expect(CommissionEntry::query()->pluck('commission_minor')->all())->toBe([59, 59]);
});

it('earns nothing for an ended affiliate and for families without a code', function () {
    $this->affiliate->update(['status' => Affiliate::STATUS_ENDED]);
    FamilyAttribution::query()->create([
        'family_id' => $this->family->id, 'affiliate_code_id' => $this->code->id, 'affiliate_id' => $this->affiliate->id,
        'source' => 'code', 'attributed_at' => now(),
    ]);

    paddleSale($this->family)->assertOk();
    paddleSale(familyOwner()->family)->assertOk();

    expect(CommissionEntry::query()->count())->toBe(0);
});

it('is idempotent per revenue event', function () {
    paddleSale($this->family, ['affiliate_code' => 'MAYA'], 'txn_once')->assertOk();
    $entry = CommissionEntry::query()->sole();

    $event = new RevenueRecorded($this->family->id, 'paddle', $entry->source_event_id, 'sale', 'txn_once', now()->toIso8601String(), 'USD', 4999, 0, 300, 4699);
    expect(app(RecordRevenue::class)->handle($event)?->id)->toBe($entry->id)
        ->and(CommissionEntry::query()->count())->toBe(1);
});

it('makes entries payable after the 30-day refund window', function () {
    paddleSale($this->family, ['affiliate_code' => 'MAYA'])->assertOk();

    $this->travel(29)->days();
    expect(app(ReleasePayableCommissions::class)->handle())->toBe(0);

    $this->travel(2)->days();
    Artisan::call('billing:release-commissions');
    expect(CommissionEntry::query()->sole()->status)->toBe(CommissionEntry::STATUS_PAYABLE);
});

it('keeps the commission with the earning affiliate after staff move the family', function () {
    paddleSale($this->family, ['affiliate_code' => 'MAYA'], 'txn_move')->assertOk();
    $newCode = AffiliateCode::factory()->create(['code' => 'NEWCODE']);
    FamilyAttribution::query()->update(['affiliate_code_id' => $newCode->id, 'affiliate_id' => $newCode->affiliate_id]);

    paddleAdjustment('refund', 'approved', 'txn_move')->assertOk();
    paddleSale($this->family, [], 'txn_after')->assertOk();

    expect(CommissionEntry::query()->where('kind', 'refund')->sole()->affiliate_id)->toBe($this->affiliate->id)
        ->and(CommissionEntry::query()->where('source_reference', 'txn_after')->sole()->affiliate_id)->toBe($newCode->affiliate_id);
});

it('only lets admins of the family use the referral endpoint', function () {
    $this->postJson('/api/v1/family/referral-code', ['code' => 'MAYA'])->assertUnauthorized();

    $child = addMember($this->family, 9);
    actingAsMember($child);
    $this->postJson('/api/v1/family/referral-code', ['code' => 'MAYA'])->assertForbidden();

    expect(FamilyMember::query()->count())->toBeGreaterThan(1)
        ->and(FamilyAttribution::query()->count())->toBe(0);
});
