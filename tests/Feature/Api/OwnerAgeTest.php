<?php

use App\Modules\Billing\Mail\WebCheckoutMail;
use App\Modules\Billing\Models\WebCheckoutRequest;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\Models\Setting;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/**
 * Owner ages (docs/API.md "Plans and tiers" → "Owner ages"): global settings
 * `family.owner_min_age` (18 since 2026-11-21: create a family on any plan, self sign-up) and
 * `classroom.owner_min_age` (18: a Classroom's family owner), 403 `owner_too_young` with `min_age`,
 * and `GET /pricing` `min_owner_age`.
 */
function ownerAgeRegister(int $age, string $email = 'young@example.com'): TestResponse
{
    return test()->postJson('/api/v1/auth/register', [
        'display_name' => 'Sam',
        'email' => $email,
        'password' => 'secret123',
        'birth_year' => now()->year - $age,
        'birth_month' => 1,
        'accept_terms' => true,
        'device_name' => 'Pixel 9',
        'platform' => 'android',
    ]);
}

function ownerAgeCreateFamily(): TestResponse
{
    return test()->postJson('/api/v1/families', ['name' => 'The Clan', 'timezone' => 'America/Toronto', 'country_code' => 'CA']);
}

function ownerAgeConfigurePaddle(): void
{
    config([
        'billing.paddle.environment' => 'sandbox',
        'billing.paddle.api_key' => 'pdl_test_key',
        'billing.paddle.webhook_secret' => 'pdl_secret',
        'billing.paddle.price_ids' => paddlePriceIds(),
        'billing.paddle.hosted_checkout_url' => 'https://sandbox-pay.paddle.io/checkout/hsc_test',
        'billing.web_checkout.link_storefronts' => ['USA'],
    ]);
}

/**
 * A family owned by someone of this age, with an adult co-admin who has an email login.
 *
 * @return array{owner: FamilyMember, admin: FamilyMember}
 */
function ownerAgeFamily(int $ownerAge): array
{
    $owner = familyOwner([], $ownerAge);

    return ['owner' => $owner, 'admin' => addMember($owner->family, 40, FamilyMember::ROLE_ADMIN)];
}

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    Mail::fake();
    RateLimiter::clear('web-subscribe');
});

// ---------------------------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------------------------

it('reads the owner ages from the PolicyResolver', function () {
    $policy = app(PolicyResolver::class);

    expect($policy->familyOwnerMinAge())->toBe(18)
        ->and($policy->classroomOwnerMinAge())->toBe(18)
        ->and($policy->minOwnerAgeFor(Family::TIER_CLASSROOM))->toBe(18)
        ->and($policy->minOwnerAgeFor(Family::TIER_FAMILY))->toBeNull()
        ->and($policy->minOwnerAgeFor(Family::TIER_BIG_CLAN))->toBeNull()
        ->and($policy->minOwnerAgeFor(Family::TIER_FREE))->toBeNull()
        ->and($policy->selfSignUpAllowed(now()->year - 13, 1))->toBeFalse()
        ->and($policy->selfSignUpAllowed(now()->year - 16, 1))->toBeFalse()
        ->and($policy->selfSignUpAllowed(now()->year - 17, 1))->toBeFalse()
        ->and($policy->selfSignUpAllowed(now()->year - 18, 1))->toBeTrue()
        ->and($policy->selfSignUpAllowed(now()->year - 40, 1))->toBeTrue()
        ->and($policy->selfSignUpAllowed(null, null))->toBeTrue()
        ->and($policy->userIsAtLeast(User::factory()->make(['birth_year' => null, 'birth_month' => null]), 18))->toBeTrue();
});

it('counts the birthday month: 18 only from the birth month on', function () {
    $this->travelTo(now()->setDate(2026, 5, 15));
    $policy = app(PolicyResolver::class);

    expect($policy->selfSignUpAllowed(2008, 5))->toBeTrue()
        ->and($policy->selfSignUpAllowed(2008, 6))->toBeFalse();
});

it('adds the owner age settings to an existing database, keeping admin edits', function () {
    Setting::query()->whereIn('key', ['family.owner_min_age', 'classroom.owner_min_age'])->delete();
    Setting::query()->create(['key' => 'classroom.owner_min_age', 'scope' => 'global', 'scope_id' => null, 'value' => 19, 'description' => 'Admin edit']);

    $migration = require database_path('migrations/2026_11_20_400000_add_owner_min_age_settings.php');
    $migration->up();
    $migration->up(); // twice changes nothing

    $rows = Setting::query()->whereIn('key', ['family.owner_min_age', 'classroom.owner_min_age'])->where('scope', 'global')->get()->keyBy('key');

    expect($rows)->toHaveCount(2)
        ->and($rows['family.owner_min_age']->value)->toBe(16)
        ->and($rows['classroom.owner_min_age']->value)->toBe(19);
});

it('raises family.owner_min_age from 16 to 18 on an existing database', function () {
    $migration = require database_path('migrations/2026_11_21_300000_raise_family_owner_min_age_to_18.php');
    $family = fn () => Setting::query()->where('key', 'family.owner_min_age')->where('scope', 'global')->whereNull('scope_id');

    $family()->update(['value' => json_encode(16)]);
    $migration->up();
    $migration->up(); // twice changes nothing

    expect($family()->count())->toBe(1)
        ->and($family()->sole()->value)->toBe(18)
        ->and(Setting::query()->where('key', 'classroom.owner_min_age')->sole()->value)->toBe(18);

    $migration->down();
    expect($family()->sole()->value)->toBe(16);

    // Missing row: inserted.
    $family()->delete();
    $migration->up();
    expect($family()->sole()->value)->toBe(18);
});

// ---------------------------------------------------------------------------------------------
// Self sign-up and POST /families
// ---------------------------------------------------------------------------------------------

it('lets an 18-year-old register and create a family', function () {
    $token = ownerAgeRegister(18)->assertCreated()->json('data.token');

    $this->withToken($token);
    ownerAgeCreateFamily()->assertCreated()->assertJsonPath('data.tier', 'free');

    $user = User::query()->where('email', 'young@example.com')->sole();
    expect(Family::query()->where('owner_user_id', $user->id)->count())->toBe(1);
});

it('refuses self sign-up under 18 with a validation error', function (int $age) {
    ownerAgeRegister($age)->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('birth_year')
        ->assertJsonPath('errors.birth_year.0', fn (string $message) => str_contains($message, 'at least 18'));

    expect(User::query()->count())->toBe(0);
})->with([13, 16, 17]);

it('refuses POST /families under 18 with owner_too_young and min_age, on any plan', function (int $age) {
    Sanctum::actingAs(User::factory()->aged($age)->create());

    ownerAgeCreateFamily()->assertStatus(403)
        ->assertJsonPath('code', 'owner_too_young')
        ->assertJsonPath('min_age', 18)
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'at least 18 to create a family'))
        ->assertJsonStructure(['message', 'code', 'min_age']);

    expect(Family::query()->count())->toBe(0);
})->with([16, 17]);

it('lets an 18-year-old without a family create one', function () {
    Sanctum::actingAs(User::factory()->aged(18)->create());

    ownerAgeCreateFamily()->assertCreated();
});

it('lets a user without birth data create a family (unknown age counts as an adult)', function () {
    Sanctum::actingAs(User::factory()->create(['birth_year' => null, 'birth_month' => null]));

    ownerAgeCreateFamily()->assertCreated();
});

it('checks already_in_family before the owner age', function () {
    $owner = familyOwner();
    actingAsMember(addMember($owner->family, 12));

    ownerAgeCreateFamily()->assertStatus(409)->assertJsonPath('code', 'already_in_family');
});

it('follows an admin change of family.owner_min_age', function () {
    Setting::query()->where('key', 'family.owner_min_age')->update(['value' => json_encode(14)]);
    PolicyResolver::flushCache();

    ownerAgeRegister(15)->assertCreated();
    $this->withToken((string) ownerAgeRegister(15, 'other@example.com')->json('data.token'));
    ownerAgeCreateFamily()->assertCreated();

    Sanctum::actingAs(User::factory()->aged(13)->create());
    ownerAgeCreateFamily()->assertStatus(403)->assertJsonPath('min_age', 14);
});

// ---------------------------------------------------------------------------------------------
// Classroom: pricing, in-app availability, website checkout
// ---------------------------------------------------------------------------------------------

it('serves min_owner_age per tier in GET /pricing', function () {
    $tiers = collect($this->getJson('/api/v1/pricing')->assertOk()->json('data.tiers'))->keyBy('id');

    expect($tiers['family']['min_owner_age'])->toBeNull()
        ->and($tiers['big_clan']['min_owner_age'])->toBeNull()
        ->and($tiers['classroom']['min_owner_age'])->toBe(18);
});

it('refuses the in-app Classroom checkout link when the family owner is under 18', function () {
    ownerAgeConfigurePaddle();
    ['owner' => $owner, 'admin' => $admin] = ownerAgeFamily(17);

    foreach ([$owner, $admin] as $member) {
        actingAsMember($member);

        $this->getJson('/api/v1/family/web-checkout/availability?storefront=USA&tier=classroom')
            ->assertStatus(403)
            ->assertJsonPath('code', 'owner_too_young')
            ->assertJsonPath('min_age', 18);
    }

    // Other tiers keep the usual answer (the adult admin may buy Family).
    $this->getJson('/api/v1/family/web-checkout/availability?storefront=USA&tier=family')
        ->assertOk()->assertJsonPath('data.link_allowed', true);
});

it('allows the Classroom link for an adult admin when the owner is 18+', function () {
    ownerAgeConfigurePaddle();
    ['admin' => $admin] = ownerAgeFamily(18);
    actingAsMember($admin);

    $this->getJson('/api/v1/family/web-checkout/availability?storefront=USA&tier=classroom')
        ->assertOk()->assertJsonPath('data.link_allowed', true);
});

it('emails owner_too_young instead of a Classroom link when the owner is under 18', function () {
    ownerAgeConfigurePaddle();
    ['admin' => $admin] = ownerAgeFamily(16);

    $this->post('/web/subscribe', ['email' => $admin->user->email, 'plan' => 'annual', 'tier' => 'classroom'])
        ->assertOk()->assertSee('Check your email');

    Mail::assertQueued(WebCheckoutMail::class, fn (WebCheckoutMail $mail) => $mail->hasTo($admin->user->email)
        && $mail->checkoutUrl === null && $mail->reason === 'owner_too_young'
        && str_contains($mail->render(), 'at least 18'));

    $request = WebCheckoutRequest::query()->sole();
    expect($request->status)->toBe(WebCheckoutRequest::STATUS_INELIGIBLE)->and($request->reason)->toBe('owner_too_young');

    // The same family may still buy Family on the web.
    $this->post('/web/subscribe', ['email' => $admin->user->email, 'plan' => 'annual', 'tier' => 'family'])->assertOk();
    Mail::assertQueued(WebCheckoutMail::class, fn (WebCheckoutMail $mail) => $mail->checkoutUrl !== null && $mail->tier === 'family');
});

it('refuses an emailed Classroom link with 403 once the owner is under 18, without a Paddle call', function () {
    ownerAgeConfigurePaddle();
    Http::fake();
    ['owner' => $owner, 'admin' => $admin] = ownerAgeFamily(40);

    $this->post('/web/subscribe', ['email' => $admin->user->email, 'plan' => 'annual', 'tier' => 'classroom'])->assertOk();
    $url = null;
    Mail::assertQueued(WebCheckoutMail::class, function (WebCheckoutMail $mail) use (&$url) {
        $url = $mail->checkoutUrl;

        return $url !== null;
    });
    $token = (string) preg_replace('/.*[?&]token=([A-Za-z0-9]{64}).*/', '$1', (string) $url);

    $owner->user->forceFill(['birth_year' => now()->year - 17, 'birth_month' => 1])->save();

    $this->get('/subscribe/checkout?token='.$token)->assertStatus(403)->assertSee('Can’t subscribe right now')
        ->assertSee('A Classroom needs a family owner who is at least 18.');

    Http::assertNothingSent();
    expect(WebCheckoutRequest::query()->sole()->transaction_id)->toBeNull();
});

it('still grants a Classroom bought in the store for a young owner (payment already made)', function () {
    config(['services.revenuecat.webhook_secret' => 'rc-test-secret']);
    $family = familyOwner([], 16)->family;

    $this->postJson('/api/v1/webhooks/revenuecat', ['api_version' => '1.0', 'event' => [
        'id' => 'evt-young-classroom',
        'type' => 'INITIAL_PURCHASE',
        'app_user_id' => 'family-'.$family->id,
        'product_id' => 'brainyclan_classroom_monthly',
        'entitlement_ids' => ['classroom'],
        'period_type' => 'NORMAL',
        'store' => 'APP_STORE',
        'environment' => 'SANDBOX',
        'event_timestamp_ms' => now()->getTimestampMs(),
        'purchased_at_ms' => now()->subMinute()->getTimestampMs(),
        'expiration_at_ms' => now()->addDays(30)->getTimestampMs(),
    ]], ['Authorization' => 'Bearer rc-test-secret'])->assertOk();

    expect($family->fresh()->tier)->toBe(Family::TIER_CLASSROOM);
});
