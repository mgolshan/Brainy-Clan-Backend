<?php

use App\Modules\Billing\Mail\WebCheckoutMail;
use App\Modules\Billing\Models\AffiliateCode;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\WebCheckoutRequest;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Pricing, the website subscribe flow (`/subscribe`, `POST /web/subscribe`, the emailed link),
 * `GET /family/web-checkout/availability` and `POST /family/subscription/manage-link`.
 */
function configurePaddle(array $extra = []): void
{
    config([
        'billing.paddle.environment' => 'sandbox',
        'billing.paddle.api_key' => 'pdl_test_key',
        'billing.paddle.webhook_secret' => 'pdl_secret',
        'billing.paddle.price_ids' => ['monthly' => 'pri_monthly', 'semiannual' => 'pri_semi', 'annual' => 'pri_annual'],
        'billing.paddle.hosted_checkout_url' => 'https://sandbox-pay.paddle.io/checkout/hsc_test',
        ...$extra,
    ]);
}

function fakePaddleTransaction(string $id = 'txn_01test'): void
{
    Http::fake(['sandbox-api.paddle.com/transactions' => Http::response(['data' => [
        'id' => $id, 'status' => 'draft', 'checkout' => ['url' => 'https://brainyclan.test/subscribe/pay?_ptxn='.$id],
    ]], 201)]);
}

/**
 * Submits the subscribe form and returns the token from the queued checkout mail (null when none).
 */
function subscribeAndGetToken(string $email, array $fields = []): ?string
{
    test()->post('/web/subscribe', ['email' => $email, 'plan' => 'annual', ...$fields])->assertOk();

    $url = null;
    Mail::assertQueued(WebCheckoutMail::class, function (WebCheckoutMail $mail) use (&$url, $email) {
        if ($mail->hasTo($email) && $mail->checkoutUrl !== null) {
            $url = $mail->checkoutUrl;
        }

        return true;
    });

    return $url !== null ? (string) preg_replace('/.*[?&]token=([A-Za-z0-9]{64}).*/', '$1', $url) : null;
}

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Mail::fake();
    RateLimiter::clear('web-subscribe');
});

it('serves the display prices in USD and CAD', function () {
    $this->getJson('/api/v1/pricing')->assertOk()
        ->assertHeader('Cache-Control', 'max-age=300, public')
        ->assertExactJson(['data' => ['currency' => 'USD', 'plans' => [
            ['id' => 'annual', 'price' => '49.99', 'per_month' => '4.17', 'saving_percent' => 40, 'trial_days' => 7, 'highlighted' => true],
            ['id' => 'semiannual', 'price' => '29.99', 'per_month' => '5.00', 'saving_percent' => 28, 'trial_days' => 7, 'highlighted' => false],
            ['id' => 'monthly', 'price' => '6.99', 'per_month' => '6.99', 'saving_percent' => 0, 'trial_days' => 0, 'highlighted' => false],
        ]]]);

    $this->getJson('/api/v1/pricing?currency=CAD')->assertOk()
        ->assertJsonPath('data.currency', 'CAD')
        ->assertJsonPath('data.plans.0.price', '64.99')
        ->assertJsonPath('data.plans.2.price', '8.99');

    $this->getJson('/api/v1/pricing?currency=EUR')->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
});

it('shows "coming soon" without Paddle keys and sends nothing', function () {
    $owner = familyOwner();

    $this->get('/subscribe')->assertOk()
        ->assertSee('Subscribing on the web is coming soon')
        ->assertSee('US$49.99')
        ->assertDontSee('name="email"', false);
    $this->get('/subscribe?lang=fr&currency=CAD')->assertOk()->assertSee('arrive bientôt')->assertSee('64,99 CA$');

    $this->post('/web/subscribe', ['email' => $owner->user->email, 'plan' => 'annual'])->assertOk()->assertSee('coming soon');
    Mail::assertNothingQueued();
});

it('shows the subscribe form with plans, strict headers and the code prefilled', function () {
    configurePaddle();
    $code = AffiliateCode::factory()->create(['code' => 'MAYA']);

    $response = $this->get('/subscribe?code=maya&src=link&plan=monthly')->assertOk()
        ->assertSee('name="email"', false)
        ->assertSee('value="MAYA"', false)
        ->assertSee('name="src" value="link"', false)
        ->assertSee('Code MAYA applied')
        ->assertSee('merchant of record')
        ->assertHeader('X-Frame-Options', 'DENY');

    expect($response->headers->get('Content-Security-Policy'))->toContain("form-action 'self'")
        ->and($response->getContent())->toMatch('/value="monthly"\s+checked/');
});

it('answers the same for every email and only mails a link to an eligible adult admin', function () {
    configurePaddle();
    $owner = familyOwner();
    $email = $owner->user->email;

    $eligible = $this->post('/web/subscribe', ['email' => strtoupper($email), 'plan' => 'annual'])->assertOk()->getContent();
    $unknown = $this->post('/web/subscribe', ['email' => 'nobody@example.com', 'plan' => 'annual'])->assertOk()->getContent();

    $strip = fn (string $html) => preg_replace('/name="_token" value="[^"]*"/', '', $html);
    expect($strip($eligible))->toBe($strip($unknown))->toContain('Check your email');

    Mail::assertQueued(WebCheckoutMail::class, fn (WebCheckoutMail $mail) => $mail->hasTo($email) && $mail->checkoutUrl !== null
        && str_contains($mail->checkoutUrl, '/subscribe/checkout?token='));
    Mail::assertQueued(WebCheckoutMail::class, fn (WebCheckoutMail $mail) => $mail->hasTo('nobody@example.com') && $mail->checkoutUrl === null && $mail->reason === 'no_account');

    expect(WebCheckoutRequest::query()->where('user_id', $owner->user_id)->sole()->status)->toBe(WebCheckoutRequest::STATUS_PENDING)
        ->and(WebCheckoutRequest::query()->whereNull('user_id')->sole()->status)->toBe(WebCheckoutRequest::STATUS_NO_ACCOUNT)
        ->and(WebCheckoutRequest::query()->pluck('email_hash')->implode(''))->not->toContain('@');
});

it('emails the reason instead of a link when the family can’t buy on the web', function (Closure $setup, string $reason) {
    configurePaddle();
    $owner = familyOwner();
    /** @var User $user */
    $user = $setup($owner);

    $this->post('/web/subscribe', ['email' => $user->email, 'plan' => 'annual'])->assertOk()->assertSee('Check your email');

    Mail::assertQueued(WebCheckoutMail::class, fn (WebCheckoutMail $mail) => $mail->checkoutUrl === null && $mail->reason === $reason);
    expect(WebCheckoutRequest::query()->latest('id')->first()->status)->toBe(WebCheckoutRequest::STATUS_INELIGIBLE);
})->with([
    'not an admin' => [fn (FamilyMember $owner) => addMember($owner->family, 35)->user, 'not_admin'],
    'teen admin' => [function (FamilyMember $owner) {
        $teen = addMember($owner->family, 15, FamilyMember::ROLE_ADMIN);
        $teen->user->forceFill(['email' => 'teen@example.com'])->save();

        return $teen->user;
    }, 'purchases_not_allowed'],
    'store plan active' => [function (FamilyMember $owner) {
        Subscription::factory()->create(['family_id' => $owner->family_id, 'store' => 'play_store']);

        return $owner->user;
    }, 'store_plan_active'],
    'web plan active' => [function (FamilyMember $owner) {
        Subscription::factory()->paddle()->create(['family_id' => $owner->family_id]);

        return $owner->user;
    }, 'web_plan_active'],
    'no family' => [fn () => User::factory()->aged(40)->create(), 'no_family'],
]);

it('allows a family whose store plan expired', function () {
    configurePaddle();
    $owner = familyOwner();
    Subscription::factory()->expired()->create(['family_id' => $owner->family_id]);

    expect(subscribeAndGetToken($owner->user->email))->not->toBeNull();
});

it('validates the form, ignores the honeypot and rejects an unknown referral code', function () {
    configurePaddle();
    $owner = familyOwner();

    $this->post('/web/subscribe', ['email' => 'not-an-email', 'plan' => 'annual'])->assertSessionHasErrors('email');
    $this->post('/web/subscribe', ['email' => $owner->user->email, 'plan' => 'weekly'])->assertSessionHasErrors('plan');
    $this->post('/web/subscribe', ['email' => $owner->user->email, 'plan' => 'annual', 'code' => 'NOPE'])->assertSessionHasErrors('code');
    $this->post('/web/subscribe', ['email' => $owner->user->email, 'plan' => 'annual', 'website' => 'http://spam'])->assertOk();

    Mail::assertNothingQueued();
});

it('throttles the form per email (3/h) and per IP (5/h)', function () {
    configurePaddle();

    foreach (range(1, 3) as $i) {
        $this->post('/web/subscribe', ['email' => 'same@example.com', 'plan' => 'annual'])->assertOk();
    }
    $this->post('/web/subscribe', ['email' => 'SAME@example.com', 'plan' => 'annual'])->assertTooManyRequests();

    foreach (range(1, 2) as $i) {
        $this->post('/web/subscribe', ['email' => "other{$i}@example.com", 'plan' => 'annual'])->assertOk();
    }
    $this->post('/web/subscribe', ['email' => 'third@example.com', 'plan' => 'annual'])->assertTooManyRequests();
});

it('creates the Paddle transaction with custom_data and redirects to the hosted checkout', function () {
    configurePaddle();
    fakePaddleTransaction();
    $owner = familyOwner();
    AffiliateCode::factory()->create(['code' => 'MAYA', 'paddle_discount_id' => 'dsc_founding']);

    $token = subscribeAndGetToken($owner->user->email, ['code' => 'maya', 'src' => 'link']);

    $response = $this->get('/subscribe/checkout?token='.$token)->assertStatus(303);
    $location = (string) $response->headers->get('Location');

    expect($location)->toStartWith('https://sandbox-pay.paddle.io/checkout/hsc_test?transaction_id=txn_01test')
        ->toContain('user_email='.rawurlencode($owner->user->email))
        ->toContain('locale=en');

    Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://sandbox-api.paddle.com/transactions'
        && $request->hasHeader('Authorization', 'Bearer pdl_test_key')
        && $request['items'] === [['price_id' => 'pri_annual', 'quantity' => 1]]
        && $request['custom_data'] === ['family_id' => $owner->family_id, 'affiliate_code' => 'MAYA', 'affiliate_source' => 'link']
        && $request['discount_id'] === 'dsc_founding');

    // Opened again (or first by a mail scanner): the same checkout, no second transaction.
    $this->get('/subscribe/checkout?token='.$token)->assertStatus(303)->assertHeader('Location', $location);
    Http::assertSentCount(1);

    expect(WebCheckoutRequest::query()->where('user_id', $owner->user_id)->sole())
        ->status->toBe(WebCheckoutRequest::STATUS_OPENED)
        ->transaction_id->toBe('txn_01test');
});

it('uses the website payment page with Paddle.js when there is no hosted checkout', function () {
    configurePaddle(['billing.paddle.hosted_checkout_url' => null, 'billing.paddle.client_token' => 'test_client_token']);
    fakePaddleTransaction('txn_02');
    $owner = familyOwner();

    $token = subscribeAndGetToken($owner->user->email);
    $location = (string) $this->get('/subscribe/checkout?token='.$token)->assertStatus(303)->headers->get('Location');

    expect($location)->toContain('/subscribe/pay?_ptxn=txn_02');
    Http::assertSent(fn (HttpRequest $request) => $request['custom_data'] === ['family_id' => $owner->family_id] && ! isset($request['discount_id']));

    $page = $this->get('/subscribe/pay?_ptxn=txn_02')->assertOk()
        ->assertSee('data-paddle-token="test_client_token"', false)
        ->assertSee('https://cdn.paddle.com/paddle/v2/paddle.js', false);
    expect($page->headers->get('Content-Security-Policy'))->toContain('script-src \'self\' https://cdn.paddle.com');
});

it('refuses expired, superseded, unknown and no-longer-eligible links', function () {
    configurePaddle();
    fakePaddleTransaction();
    $owner = familyOwner();

    $old = subscribeAndGetToken($owner->user->email);
    $new = subscribeAndGetToken($owner->user->email);
    expect($new)->not->toBe($old);

    $this->get('/subscribe/checkout?token='.$old)->assertOk()->assertSee('Link expired');
    $this->get('/subscribe/checkout?token=unknown')->assertOk()->assertSee('Link expired');
    $this->get('/subscribe/checkout')->assertOk()->assertSee('Link expired');

    $this->travel(31)->minutes();
    $this->get('/subscribe/checkout?token='.$new)->assertOk()->assertSee('Link expired');
    $this->travelBack();

    $fresh = subscribeAndGetToken($owner->user->email);
    Subscription::factory()->create(['family_id' => $owner->family_id]);
    $this->get('/subscribe/checkout?token='.$fresh)->assertOk()->assertSee('App Store or Google Play');

    Http::assertNothingSent();
});

it('shows a retry page when Paddle fails and keeps the link usable', function () {
    configurePaddle();
    Http::fake(['sandbox-api.paddle.com/transactions' => Http::sequence()
        ->push(['error' => ['code' => 'internal_error']], 500)
        ->push(['data' => ['id' => 'txn_ok', 'checkout' => ['url' => null]]], 201)]);
    $owner = familyOwner();
    $token = subscribeAndGetToken($owner->user->email);

    $this->get('/subscribe/checkout?token='.$token)->assertOk()->assertSee('Something went wrong');
    $this->get('/subscribe/checkout?token='.$token)->assertStatus(303);
});

it('shows the thank-you page in both languages', function () {
    $this->get('/subscribe/thanks')->assertOk()->assertSee('Thank you!');
    $this->get('/subscribe/thanks?lang=fr')->assertOk()->assertSee('Merci');
});

it('allows the in-app web checkout link only for configured storefronts of eligible families', function () {
    $owner = familyOwner();
    actingAsMember($owner);

    $this->getJson('/api/v1/family/web-checkout/availability?storefront=USA')->assertOk()
        ->assertExactJson(['data' => ['link_allowed' => false, 'url' => null]]);

    configurePaddle(['billing.web_checkout.link_storefronts' => ['USA']]);

    $this->getJson('/api/v1/family/web-checkout/availability?storefront=usa')->assertOk()
        ->assertJsonPath('data.link_allowed', true)
        ->assertJsonPath('data.url', fn (string $url) => str_ends_with($url, '/subscribe?plan=annual&src=app'));
    $this->getJson('/api/v1/family/web-checkout/availability?storefront=CAN')->assertOk()->assertJsonPath('data.link_allowed', false);
    $this->getJson('/api/v1/family/web-checkout/availability')->assertOk()->assertJsonPath('data.link_allowed', false);
    $this->getJson('/api/v1/family/web-checkout/availability?storefront=US')->assertUnprocessable();

    Subscription::factory()->create(['family_id' => $owner->family_id]);
    $this->getJson('/api/v1/family/web-checkout/availability?storefront=USA')->assertOk()->assertJsonPath('data.link_allowed', false);

    actingAsMember(addMember($owner->family, 30));
    $this->getJson('/api/v1/family/web-checkout/availability?storefront=USA')->assertForbidden();
});

it('returns where to manage the plan: Paddle portal, App Store or Google Play', function () {
    $owner = familyOwner();
    actingAsMember($owner);

    $this->postJson('/api/v1/family/subscription/manage-link')->assertNotFound()->assertJsonPath('code', 'not_found');

    $store = Subscription::factory()->create(['family_id' => $owner->family_id, 'store' => 'app_store']);
    $this->postJson('/api/v1/family/subscription/manage-link')->assertOk()
        ->assertExactJson(['data' => ['url' => 'https://apps.apple.com/account/subscriptions']]);

    $store->update(['store' => 'play_store']);
    $this->postJson('/api/v1/family/subscription/manage-link')->assertOk()
        ->assertJsonPath('data.url', 'https://play.google.com/store/account/subscriptions');

    $store->delete();
    Subscription::factory()->paddle()->create(['family_id' => $owner->family_id, 'customer_id' => 'ctm_1', 'external_id' => 'sub_1']);
    $this->postJson('/api/v1/family/subscription/manage-link')->assertStatus(503)->assertJsonPath('code', 'web_checkout_unavailable');

    configurePaddle();
    Http::fake(['sandbox-api.paddle.com/customers/ctm_1/portal-sessions' => Http::response(['data' => [
        'id' => 'cpls_1', 'urls' => ['general' => ['overview' => 'https://customer-portal.paddle.com/cpl_1?token=abc'], 'subscriptions' => []],
    ]], 201)]);

    $this->postJson('/api/v1/family/subscription/manage-link')->assertOk()
        ->assertJsonPath('data.url', 'https://customer-portal.paddle.com/cpl_1?token=abc')
        ->assertHeader('Cache-Control', 'no-store, private');
    Http::assertSent(fn (HttpRequest $request) => $request['subscription_ids'] === ['sub_1']);

    actingAsMember(addMember($owner->family, 30));
    $this->postJson('/api/v1/family/subscription/manage-link')->assertForbidden();
});

it('shows the affiliate banner on /r/{code} without cookies, and the normal page for unknown codes', function () {
    AffiliateCode::factory()->create(['code' => 'MAYA', 'store_offer_codes' => ['FOUNDING']]);

    $page = $this->get('/r/maya')->assertOk()
        ->assertSee('Code MAYA')
        ->assertSee('redeem the App Store / Google Play offer')
        ->assertDontSee('Or subscribe on the web');
    expect($page->headers->getCookies())->toBe([])
        ->and($page->getContent())->toContain('rel="canonical" href="'.route('website.home', ['lang' => 'en']).'"');

    configurePaddle();
    $this->get('/r/MAYA?lang=fr')->assertOk()
        ->assertSee('Ou abonnez-vous sur le Web')
        ->assertSee('/subscribe?code=MAYA&amp;src=link&amp;lang=fr', false);

    $this->get('/r/UNKNOWN')->assertOk()->assertDontSee('Code UNKNOWN')->assertSee('Learn, play and grow');
});
