<?php

use App\Modules\Billing\Contracts\RevenueCatClient;
use App\Modules\Billing\Mail\WebCheckoutMail;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\WebCheckoutRequest;
use App\Modules\Billing\Models\WebhookEvent;
use App\Modules\Billing\Support\PaddleConfig;
use App\Modules\Family\Models\Family;
use App\Modules\Policy\Models\Setting;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * B12 step 1 (docs/API.md "Plans and tiers"): the `big_clan` and `classroom` tiers, per-tier
 * settings, the RevenueCat entitlement → tier map, Paddle prices per tier, `GET /pricing` per tier,
 * the web subscribe flow with `tier`, and `family_plan` worlds for every paid tier.
 */
const B12_RC_SECRET = 'rc-b12-secret';

const B12_PADDLE_SECRET = 'pdl-b12-secret';

/**
 * A RevenueCat webhook for the family with the given entitlements and product.
 *
 * @param  list<string>  $entitlements
 */
function b12RevenueCat(Family $family, string $type, array $entitlements, string $productId = 'brainyclan_family_monthly', int $expiresInDays = 30): TestResponse
{
    /** Strictly increasing event times, so no event is recorded as stale. */
    static $sequence = 0;

    return test()->postJson('/api/v1/webhooks/revenuecat', ['api_version' => '1.0', 'event' => [
        'id' => (string) Str::uuid(),
        'type' => $type,
        'app_user_id' => 'family-'.$family->id,
        'product_id' => $productId,
        'entitlement_ids' => $entitlements,
        'period_type' => 'NORMAL',
        'store' => 'APP_STORE',
        'environment' => 'SANDBOX',
        'event_timestamp_ms' => now()->getTimestampMs() + ($sequence += 1000),
        'purchased_at_ms' => now()->subMinute()->getTimestampMs(),
        'expiration_at_ms' => now()->addDays($expiresInDays)->getTimestampMs(),
    ]], ['Authorization' => 'Bearer '.B12_RC_SECRET]);
}

/**
 * A signed Paddle `subscription.created` webhook on a price.
 */
function b12Paddle(Family $family, string $priceId, string $status = 'active', string $subscriptionId = 'sub_b12'): TestResponse
{
    $body = (string) json_encode([
        'event_id' => 'evt_'.Str::lower(Str::random(20)),
        'event_type' => 'subscription.created',
        'occurred_at' => now()->toIso8601String(),
        'data' => [
            'id' => $subscriptionId,
            'status' => $status,
            'customer_id' => 'ctm_b12',
            'custom_data' => ['family_id' => $family->id],
            'current_billing_period' => ['starts_at' => now()->subMinute()->toIso8601String(), 'ends_at' => now()->addYear()->toIso8601String()],
            'items' => [['price' => ['id' => $priceId], 'quantity' => 1]],
        ],
    ]);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.':'.$body, B12_PADDLE_SECRET);

    return test()->call('POST', '/api/v1/webhooks/paddle', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_PADDLE_SIGNATURE' => "ts={$timestamp};h1={$signature}",
    ], $body);
}

beforeEach(function () {
    config([
        'services.revenuecat.webhook_secret' => B12_RC_SECRET,
        'billing.paddle.environment' => 'sandbox',
        'billing.paddle.api_key' => 'pdl_test_key',
        'billing.paddle.webhook_secret' => B12_PADDLE_SECRET,
        'billing.paddle.hosted_checkout_url' => 'https://sandbox-pay.paddle.io/checkout/hsc_test',
        'billing.paddle.price_ids' => paddlePriceIds(),
    ]);
    $this->owner = familyOwner();
    $this->family = $this->owner->family;
});

// ---------------------------------------------------------------------------------------------
// Tiers, rank and per-tier settings
// ---------------------------------------------------------------------------------------------

it('ranks the paid tiers family < big_clan < classroom and picks the highest', function () {
    expect(Family::TIERS)->toBe(['free', 'family', 'big_clan', 'classroom'])
        ->and(Family::PAID_TIERS)->toBe(['family', 'big_clan', 'classroom'])
        ->and(array_map(Family::tierRank(...), ['free', 'family', 'big_clan', 'classroom', 'unknown']))->toBe([0, 1, 2, 3, 0])
        ->and(Family::highestTier(['family', 'classroom', 'big_clan']))->toBe('classroom')
        ->and(Family::highestTier([]))->toBe('free')
        ->and(Family::isPaidTier('free'))->toBeFalse()
        ->and(Family::isPaidTier('big_clan'))->toBeTrue()
        ->and(Family::tierLabel('big_clan'))->toBe('Big Clan')
        ->and(Family::tierLabel('classroom', 'fr'))->toBe('Classe');
});

it('seeds the settings of every tier as in the API table', function (string $tier, int $members, int $categories, ?int $goals, int $secondChances, bool $ads, int $chatDays) {
    $policy = app(PolicyResolver::class);

    expect($policy->maxMembersFor($tier))->toBe($members)
        ->and((int) $policy->setting('limits.max_categories', $tier))->toBe($categories)
        ->and($policy->maxActiveGoalsFor($tier))->toBe($goals)
        ->and((int) $policy->setting('second_chance.free_per_day', $tier))->toBe($secondChances)
        ->and((bool) $policy->setting('ads.enabled', $tier))->toBe($ads)
        ->and($policy->chatRetentionDays($tier))->toBe($chatDays);
})->with([
    'free' => ['free', 2, 3, 1, 0, true, 7],
    'family' => ['family', 6, 100, null, 3, false, 365],
    'big_clan' => ['big_clan', 12, 100, null, 3, false, 365],
    'classroom' => ['classroom', 37, 100, null, 3, false, 365],
]);

it('gives a big_clan family its own limits in /me', function () {
    $this->family->forceFill(['tier' => Family::TIER_BIG_CLAN])->save();
    actingAsMember($this->owner);

    $this->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.policy.tier', 'big_clan')
        ->assertJsonPath('data.policy.limits.max_members', 12)
        ->assertJsonPath('data.policy.ads_allowed', false)
        ->assertJsonPath('data.policy.chat.retention_days', 365);
});

it('adds the new tier settings to an existing database and moves Family from 7 to 6 members, keeping admin edits', function () {
    Setting::query()->where('scope', 'tier')->whereIn('scope_id', ['big_clan', 'classroom'])->delete();
    Setting::query()->where('key', 'limits.max_members')->where('scope_id', 'family')->update(['value' => json_encode(7)]);
    Setting::query()->create(['key' => 'limits.max_members', 'scope' => 'tier', 'scope_id' => 'classroom', 'value' => 40, 'description' => 'Admin edit']);

    $migration = require database_path('migrations/2026_11_20_100000_add_big_clan_and_classroom_tier_settings.php');
    $migration->up();
    $migration->up(); // twice changes nothing

    $value = fn (string $tier, string $key): mixed => Setting::query()->where('scope', 'tier')->where('scope_id', $tier)->where('key', $key)->first()?->value;

    expect(Setting::query()->where('scope', 'tier')->where('scope_id', 'big_clan')->count())->toBe(6)
        ->and(Setting::query()->where('scope', 'tier')->where('scope_id', 'classroom')->count())->toBe(6)
        ->and($value('family', 'limits.max_members'))->toBe(6)
        ->and($value('big_clan', 'limits.max_members'))->toBe(12)
        ->and($value('classroom', 'limits.max_members'))->toBe(40)
        ->and($value('big_clan', 'ads.enabled'))->toBeFalse();

    // An admin's own Family value is not the old default and stays.
    Setting::query()->where('key', 'limits.max_members')->where('scope_id', 'family')->update(['value' => json_encode(8)]);
    $migration->up();
    expect($value('family', 'limits.max_members'))->toBe(8);
});

it('unlocks family_plan worlds for every paid tier and not for free', function (string $tier, bool $unlocked) {
    $this->family->forceFill(['tier' => $tier])->save();
    actingAsMember($this->owner);

    $worlds = collect($this->getJson('/api/v1/me/worlds')->assertOk()->json('data'))->keyBy('key');

    expect($worlds->filter(fn (array $world): bool => $world['unlock']['type'] === 'family_plan')->pluck('unlocked')->unique()->values()->all())->toBe([$unlocked]);
})->with([
    'free' => ['free', false],
    'family' => ['family', true],
    'big_clan' => ['big_clan', true],
    'classroom' => ['classroom', true],
]);

// ---------------------------------------------------------------------------------------------
// RevenueCat: entitlement → tier, highest rank wins
// ---------------------------------------------------------------------------------------------

it('maps each RevenueCat entitlement to its tier and stores the entitlement', function (string $entitlement, string $product) {
    b12RevenueCat($this->family, 'INITIAL_PURCHASE', [$entitlement], $product)->assertOk()->assertJsonPath('data.outcome', 'applied');

    expect($this->family->fresh()->tier)->toBe($entitlement)
        ->and(Subscription::query()->sole()->entitlement)->toBe($entitlement);

    actingAsMember($this->owner);
    $this->getJson('/api/v1/family/subscription')->assertOk()
        ->assertJsonPath('data.tier', $entitlement)
        ->assertJsonPath('data.entitlement', $entitlement)
        ->assertJsonPath('data.product_id', $product);
})->with([
    'family' => ['family', 'brainyclan_family_annual'],
    'big_clan' => ['big_clan', 'brainyclan_big_clan_monthly'],
    'classroom' => ['classroom', 'brainyclan_classroom_6months'],
]);

it('gives the highest-ranked tier when an event carries several entitlements', function () {
    b12RevenueCat($this->family, 'INITIAL_PURCHASE', ['family', 'classroom', 'big_clan'], 'brainyclan_classroom_annual')->assertOk();

    expect($this->family->fresh()->tier)->toBe(Family::TIER_CLASSROOM)
        ->and(Subscription::query()->sole()->entitlement)->toBe('classroom');
});

it('ignores events whose entitlements are none of ours', function () {
    b12RevenueCat($this->family, 'INITIAL_PURCHASE', ['premium'], 'other_product')->assertOk()->assertJsonPath('data.outcome', 'ignored');

    expect($this->family->fresh()->tier)->toBe(Family::TIER_FREE)
        ->and(Subscription::query()->count())->toBe(0);
});

it('reads the entitlement ids from config (env-overridable map)', function () {
    config(['services.revenuecat.entitlements.big_clan' => 'grand_plan']);

    b12RevenueCat($this->family, 'INITIAL_PURCHASE', ['big_clan'])->assertOk()->assertJsonPath('data.outcome', 'ignored');
    b12RevenueCat($this->family, 'INITIAL_PURCHASE', ['grand_plan'], 'brainyclan_big_clan_annual')->assertOk()->assertJsonPath('data.outcome', 'applied');

    expect($this->family->fresh()->tier)->toBe(Family::TIER_BIG_CLAN)
        ->and(Subscription::query()->sole()->tier())->toBe(Family::TIER_BIG_CLAN);
});

it('switches tier on a store plan change and back to free on expiration', function () {
    b12RevenueCat($this->family, 'INITIAL_PURCHASE', ['family'])->assertOk();
    b12RevenueCat($this->family, 'PRODUCT_CHANGE', ['big_clan'], 'brainyclan_big_clan_monthly')->assertOk();
    expect($this->family->fresh()->tier)->toBe(Family::TIER_BIG_CLAN);

    b12RevenueCat($this->family, 'EXPIRATION', ['big_clan'], 'brainyclan_big_clan_monthly', -1)->assertOk();
    expect($this->family->fresh()->tier)->toBe(Family::TIER_FREE);
});

it('infers the tier from the product id when an event has no entitlement ids', function () {
    b12RevenueCat($this->family, 'INITIAL_PURCHASE', [], 'brainyclan_classroom_annual')->assertOk()->assertJsonPath('data.outcome', 'applied');

    expect($this->family->fresh()->tier)->toBe(Family::TIER_CLASSROOM);
});

it('keeps the highest tier of a store plan and a web plan', function () {
    b12Paddle($this->family, 'pri_big_clan_annual')->assertOk();
    expect($this->family->fresh()->tier)->toBe(Family::TIER_BIG_CLAN);

    b12RevenueCat($this->family, 'INITIAL_PURCHASE', ['family'])->assertOk();
    expect($this->family->fresh()->tier)->toBe(Family::TIER_BIG_CLAN);

    b12RevenueCat($this->family, 'RENEWAL', ['classroom'], 'brainyclan_classroom_monthly')->assertOk();
    expect($this->family->fresh()->tier)->toBe(Family::TIER_CLASSROOM);
});

it('syncs the highest-ranked active entitlement from the RevenueCat API', function () {
    $active = fn (string $product): array => ['expires_date' => now()->addMonth()->toIso8601ZuluString(), 'product_identifier' => $product, 'purchase_date' => now()->subDay()->toIso8601ZuluString()];
    $subscriber = ['entitlements' => [
        'family' => [...$active('brainyclan_family_monthly'), 'expires_date' => now()->subDay()->toIso8601ZuluString()],
        'big_clan' => $active('brainyclan_big_clan_monthly'),
        'premium' => $active('other'),
    ], 'subscriptions' => [
        'brainyclan_big_clan_monthly' => ['store' => 'app_store', 'period_type' => 'normal', 'is_sandbox' => true, 'unsubscribe_detected_at' => null, 'billing_issues_detected_at' => null],
    ]];
    app()->instance(RevenueCatClient::class, new class($subscriber) implements RevenueCatClient
    {
        /** @param array<string, mixed> $subscriber */
        public function __construct(private array $subscriber) {}

        public function isConfigured(): bool
        {
            return true;
        }

        public function subscriber(string $appUserId): array
        {
            return $this->subscriber;
        }
    });
    actingAsMember($this->owner);

    $this->postJson('/api/v1/family/subscription/sync')->assertOk()
        ->assertJsonPath('data.tier', 'big_clan')
        ->assertJsonPath('data.entitlement', 'big_clan')
        ->assertJsonPath('data.product_id', 'brainyclan_big_clan_monthly');
});

// ---------------------------------------------------------------------------------------------
// Paddle: price → (tier, period)
// ---------------------------------------------------------------------------------------------

it('maps Paddle prices to tier and period, with the old env names as the Family prices', function () {
    expect(PaddleConfig::tierAndPlanForPrice('pri_big_clan_semiannual'))->toBe(['tier' => 'big_clan', 'plan' => 'semiannual'])
        ->and(PaddleConfig::tierAndPlanForPrice('pri_annual'))->toBe(['tier' => 'family', 'plan' => 'annual'])
        ->and(PaddleConfig::tierAndPlanForPrice('pri_unknown'))->toBeNull()
        ->and(PaddleConfig::availableTiers())->toBe(['family', 'big_clan', 'classroom']);

    config(['billing.paddle.price_ids' => paddlePriceIds(['classroom']), 'billing.paddle.legacy_price_ids' => ['monthly' => 'pri_old_m', 'semiannual' => 'pri_old_s', 'annual' => 'pri_old_a']]);

    expect(PaddleConfig::priceId('family', 'annual'))->toBe('pri_old_a')
        ->and(PaddleConfig::priceId('big_clan', 'annual'))->toBe('')
        ->and(PaddleConfig::webCheckoutAvailable('big_clan'))->toBeFalse()
        ->and(PaddleConfig::availableTiers())->toBe(['family', 'classroom']);
});

it('applies the tier of the Paddle price and ignores an unknown price', function () {
    b12Paddle($this->family, 'pri_unknown')->assertOk()->assertJsonPath('data.outcome', WebhookEvent::OUTCOME_IGNORED);
    expect($this->family->fresh()->tier)->toBe(Family::TIER_FREE);

    b12Paddle($this->family, 'pri_classroom_monthly')->assertOk()->assertJsonPath('data.outcome', WebhookEvent::OUTCOME_APPLIED);

    $subscription = Subscription::query()->sole();
    expect($this->family->fresh()->tier)->toBe(Family::TIER_CLASSROOM)
        ->and($subscription->entitlement)->toBe('classroom')
        ->and($subscription->product_id)->toBe('pri_classroom_monthly');
});

// ---------------------------------------------------------------------------------------------
// GET /pricing, web subscribe and the in-app link
// ---------------------------------------------------------------------------------------------

it('serves every tier with members, availability and the PLAN prices', function () {
    config(['billing.paddle.price_ids' => paddlePriceIds(['big_clan'])]);

    $tiers = $this->getJson('/api/v1/pricing')->assertOk()->assertJsonPath('data.currency', 'USD')->json('data.tiers');

    expect(array_column($tiers, 'id'))->toBe(['family', 'big_clan', 'classroom'])
        ->and(array_column($tiers, 'members'))->toBe([6, 12, 37])
        ->and(array_column($tiers, 'highlighted'))->toBe([true, false, false])
        ->and(array_column($tiers, 'available'))->toBe([false, true, false])
        ->and(array_map(fn (array $tier): array => array_column($tier['plans'], 'price', 'id'), $tiers))->toBe([
            ['annual' => '49.99', 'semiannual' => '29.99', 'monthly' => '6.99'],
            ['annual' => '69.99', 'semiannual' => '39.99', 'monthly' => '9.99'],
            ['annual' => '119.99', 'semiannual' => '69.99', 'monthly' => '14.99'],
        ])
        ->and(array_column($tiers[1]['plans'], 'trial_days'))->toBe([7, 7, 0]);

    $cad = $this->getJson('/api/v1/pricing?currency=cad')->assertOk()->json('data.tiers');
    expect(array_map(fn (array $tier): array => array_column($tier['plans'], 'price', 'id'), $cad))->toBe([
        ['annual' => '64.99', 'semiannual' => '39.99', 'monthly' => '8.99'],
        ['annual' => '89.99', 'semiannual' => '52.99', 'monthly' => '12.99'],
        ['annual' => '159.99', 'semiannual' => '89.99', 'monthly' => '19.99'],
    ]);
});

it('emails a checkout link for the chosen tier and charges that tier’s price', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Mail::fake();
    RateLimiter::clear('web-subscribe');
    Http::fake(['sandbox-api.paddle.com/transactions' => Http::response(['data' => ['id' => 'txn_b12', 'status' => 'draft', 'checkout' => ['url' => 'https://example.test/pay']]], 201)]);

    $this->post('/web/subscribe', ['email' => $this->owner->user->email, 'tier' => 'big_clan', 'plan' => 'semiannual'])->assertOk()->assertSee('Check your email');

    $request = WebCheckoutRequest::query()->sole();
    expect($request->tier)->toBe('big_clan')->and($request->plan)->toBe('semiannual');

    $url = null;
    Mail::assertQueued(WebCheckoutMail::class, function (WebCheckoutMail $mail) use (&$url): bool {
        $url = $mail->checkoutUrl;

        return $mail->tier === 'big_clan' && $mail->render() !== '' && str_contains($mail->render(), 'Big Clan plan');
    });
    $token = (string) preg_replace('/.*[?&]token=([A-Za-z0-9]{64}).*/', '$1', (string) $url);

    $this->get('/subscribe/checkout?token='.$token)->assertStatus(303);
    Http::assertSent(fn (HttpRequest $sent): bool => $sent['items'] === [['price_id' => 'pri_big_clan_semiannual', 'quantity' => 1]]);
});

it('defaults the web subscribe tier to family, rejects an unknown tier and refuses a tier without prices', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Mail::fake();
    RateLimiter::clear('web-subscribe');

    $this->post('/web/subscribe', ['email' => $this->owner->user->email, 'plan' => 'annual'])->assertOk();
    expect(WebCheckoutRequest::query()->sole()->tier)->toBe('family');

    $this->post('/web/subscribe', ['email' => $this->owner->user->email, 'tier' => 'gold', 'plan' => 'annual'])->assertSessionHasErrors('tier');

    config(['billing.paddle.price_ids' => paddlePriceIds(['family'])]);
    $this->post('/web/subscribe', ['email' => $this->owner->user->email, 'tier' => 'classroom', 'plan' => 'annual'])->assertOk()->assertSee('coming soon');
    expect(WebCheckoutRequest::query()->count())->toBe(1);
});

it('links the app to the web subscribe page of the asked tier', function () {
    config(['billing.web_checkout.link_storefronts' => ['USA'], 'billing.paddle.price_ids' => paddlePriceIds(['family', 'big_clan'])]);
    actingAsMember($this->owner);

    $this->getJson('/api/v1/family/web-checkout/availability?storefront=USA&tier=big_clan')->assertOk()
        ->assertJsonPath('data.link_allowed', true)
        ->assertJsonPath('data.url', fn (string $url): bool => str_ends_with($url, '/subscribe?tier=big_clan&plan=annual&src=app'));
    $this->getJson('/api/v1/family/web-checkout/availability?storefront=USA&tier=classroom')->assertOk()
        ->assertExactJson(['data' => ['link_allowed' => false, 'url' => null]]);
    $this->getJson('/api/v1/family/web-checkout/availability?storefront=USA&tier=gold')->assertUnprocessable();
});

it('shows the tier picker with per-tier prices and marks tiers without web prices as coming soon', function () {
    config(['billing.paddle.price_ids' => paddlePriceIds(['family', 'big_clan'])]);

    $html = (string) $this->get('/subscribe?tier=big_clan&plan=monthly')->assertOk()->getContent();

    expect($html)->toMatch('/name="tier" value="big_clan"\s+checked/')
        ->toMatch('/name="tier" value="classroom"\s+disabled/')
        ->toContain('Coming soon on the web')
        ->toContain('Up to 12 members')
        ->toContain('Up to 37 members')
        ->toContain('From US$9.99 / month')
        ->toContain('data-tier="big_clan"')
        ->not->toContain('data-tier="classroom"')
        ->not->toContain('up to 7')
        ->and($html)->toMatch('/value="monthly"\s+checked/');

    $this->get('/subscribe?lang=fr&currency=CAD')->assertOk()
        ->assertSee('Grand Clan')
        ->assertSee('Dès 12,99 CA$ / mois', false);
});
