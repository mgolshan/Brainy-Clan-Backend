<?php

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\SocialAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Social\SocialIdentity;
use App\Modules\Identity\Social\SocialTokenVerifier;
use App\Modules\Identity\Social\SocialTokenVerifiers;
use Laravel\Sanctum\Sanctum;

/**
 * Replace a provider's verifier: the token `bad` (or a null identity) fails like a real
 * invalid token; every other token belongs to `$identity`.
 */
function fakeSocialProvider(string $provider, ?SocialIdentity $identity): void
{
    app()->instance(SocialTokenVerifiers::VERIFIERS[$provider], new class($identity) implements SocialTokenVerifier
    {
        public function __construct(private readonly ?SocialIdentity $identity) {}

        public function verify(string $token): SocialIdentity
        {
            if ($this->identity === null || $token === 'bad') {
                throw ApiException::socialTokenInvalid();
            }

            return $this->identity;
        }
    });
}

function googleIdentity(string $sub = 'g-123', ?string $email = 'mom@gmail.com', ?string $name = 'Mom Golshan'): SocialIdentity
{
    $identity = new SocialIdentity(SocialAccount::GOOGLE, $sub, $email, $name);
    fakeSocialProvider(SocialAccount::GOOGLE, $identity);

    return $identity;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function socialPayload(array $overrides = []): array
{
    return ['token' => 'provider-token', 'device_name' => 'Pixel 9', 'platform' => 'android', ...$overrides];
}

it('creates a new adult user from a Google sign-in (201)', function () {
    googleIdentity();

    $response = $this->postJson('/api/v1/auth/social/google', socialPayload(['birth_year' => 1988, 'birth_month' => 4]), ['X-App-Version' => '1.2.0'])
        ->assertCreated()
        ->assertJsonPath('data.user.email', 'mom@gmail.com')
        ->assertJsonPath('data.user.display_name', 'Mom Golshan')
        ->assertJsonPath('data.user.birth_year', 1988)
        ->assertJsonPath('data.member', null)
        ->assertJsonPath('data.policy.age_band', 'adult');

    $user = User::query()->sole();
    expect($user->password)->toBeNull()
        ->and($user->socialAccounts()->sole()->only(['provider', 'provider_user_id', 'email']))
        ->toBe(['provider' => 'google', 'provider_user_id' => 'g-123', 'email' => 'mom@gmail.com']);

    $device = Device::query()->sole();
    expect($device->platform)->toBe('android')->and($device->app_version)->toBe('1.2.0');

    $this->withToken($response->json('data.token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.user.id', $user->id);
});

it('uses the display name and locale the app sends for a new user', function () {
    googleIdentity();

    $this->postJson('/api/v1/auth/social/google', socialPayload(['birth_year' => 1988, 'birth_month' => 4, 'display_name' => 'Moe', 'locale' => 'fr']))
        ->assertCreated()
        ->assertJsonPath('data.user.display_name', 'Moe')
        ->assertJsonPath('data.user.locale', 'fr');
});

it('asks for birth data before creating a new user (social_signup_incomplete)', function () {
    googleIdentity();

    $this->postJson('/api/v1/auth/social/google', socialPayload())
        ->assertStatus(422)
        ->assertJsonPath('code', 'social_signup_incomplete')
        ->assertJsonStructure(['message', 'code', 'errors' => ['birth_year', 'birth_month']]);

    expect(User::query()->count())->toBe(0)->and(SocialAccount::query()->count())->toBe(0);
});

it('refuses a new social user under the adult age', function (int $age) {
    googleIdentity();

    $this->postJson('/api/v1/auth/social/google', socialPayload(['birth_year' => now()->year - $age, 'birth_month' => 1]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('birth_year');

    expect(User::query()->count())->toBe(0);
})->with([9, 15]);

it('creates a user without email when the provider shares none, and never links by email then', function () {
    User::factory()->create(['email' => 'dad@example.com']);
    fakeSocialProvider(SocialAccount::FACEBOOK, new SocialIdentity('facebook', 'fb-phone', null, 'Dad'));

    $this->postJson('/api/v1/auth/social/facebook', socialPayload(['birth_year' => 1980, 'birth_month' => 2]))
        ->assertCreated()
        ->assertJsonPath('data.user.email', null)
        ->assertJsonPath('data.user.display_name', 'Dad');

    $user = SocialAccount::query()->sole()->user;
    expect(SocialAccount::query()->sole()->email)->toBeNull()
        ->and(User::query()->count())->toBe(2)
        ->and($user->isChildAccount())->toBeFalse();

    Sanctum::actingAs($user);
    $this->getJson('/api/v1/me/social-accounts')->assertJsonPath('data.0.email', null);
});

it('defaults display_name to the provider name and falls back to the email', function () {
    googleIdentity(name: null, email: 'kim.lee@gmail.com');

    $this->postJson('/api/v1/auth/social/google', socialPayload(['birth_year' => 1988, 'birth_month' => 4, 'display_name' => '  ']))
        ->assertCreated()
        ->assertJsonPath('data.user.display_name', 'kim.lee');
});

it('signs in a linked account (200) without birth data', function () {
    $account = SocialAccount::factory()->facebook()->create(['provider_user_id' => 'fb-1', 'email' => 'old@example.com']);
    fakeSocialProvider(SocialAccount::FACEBOOK, new SocialIdentity('facebook', 'fb-1', 'new@example.com', 'Dad'));

    $this->postJson('/api/v1/auth/social/facebook', socialPayload())
        ->assertOk()
        ->assertJsonPath('data.user.id', $account->user_id);

    expect(User::query()->count())->toBe(1)
        ->and($account->fresh()?->email)->toBe('new@example.com')
        ->and(Device::query()->where('user_id', $account->user_id)->count())->toBe(1);
});

it('links an existing user with the same verified email and signs in (200)', function () {
    $user = User::factory()->aged(40)->create(['email' => 'mom@gmail.com']);
    googleIdentity(email: 'mom@gmail.com');

    $this->postJson('/api/v1/auth/social/google', socialPayload())
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id);

    expect($user->socialAccounts()->sole()->provider_user_id)->toBe('g-123')
        ->and(User::query()->count())->toBe(1);
});

it('never links a non-adult email login (403)', function () {
    $teen = User::factory()->aged(15)->create(['email' => 'teen@gmail.com']);
    googleIdentity(email: 'teen@gmail.com');

    $this->postJson('/api/v1/auth/social/google', socialPayload())
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');

    expect($teen->socialAccounts()->count())->toBe(0)->and(Device::query()->count())->toBe(0);
});

it('rejects an invalid provider token with social_token_invalid (401)', function () {
    googleIdentity();

    $this->postJson('/api/v1/auth/social/google', socialPayload(['token' => 'bad']))
        ->assertUnauthorized()
        ->assertJsonPath('code', 'social_token_invalid')
        ->assertJsonStructure(['message', 'code'])
        ->assertJsonMissingPath('errors');
});

it('rejects unknown providers and missing fields', function () {
    $this->postJson('/api/v1/auth/social/twitter', socialPayload())->assertNotFound()->assertJsonPath('code', 'not_found');

    $this->postJson('/api/v1/auth/social/google', ['platform' => 'windows'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['token', 'device_name', 'platform']);
});

it('rate limits social sign-in with the auth group (10 per minute)', function () {
    googleIdentity();

    foreach (range(1, 10) as $ignored) {
        $this->postJson('/api/v1/auth/social/google', socialPayload(['token' => 'bad']))->assertUnauthorized();
    }

    $this->postJson('/api/v1/auth/social/google', socialPayload(['token' => 'bad']))
        ->assertStatus(429)
        ->assertJsonPath('code', 'rate_limited');
});

it('lists my linked accounts', function () {
    $user = User::factory()->create();
    SocialAccount::factory()->create(['user_id' => $user->id, 'email' => 'mom@gmail.com', 'linked_at' => '2026-10-11 14:00:00']);
    SocialAccount::factory()->facebook()->create(['user_id' => $user->id]);
    SocialAccount::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me/social-accounts')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.provider', 'facebook')
        ->assertJsonPath('data.1', ['provider' => 'google', 'email' => 'mom@gmail.com', 'linked_at' => '2026-10-11T14:00:00Z']);
});

it('returns an empty list without linked accounts', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/me/social-accounts')->assertOk()->assertExactJson(['data' => []]);
});

it('links a provider to the signed-in adult (201)', function () {
    $user = User::factory()->aged(35)->create();
    googleIdentity(email: 'someone@gmail.com');
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/me/social-accounts/google', ['token' => 'provider-token'])
        ->assertCreated()
        ->assertJsonPath('data.provider', 'google')
        ->assertJsonPath('data.email', 'someone@gmail.com');

    expect($user->socialAccounts()->sole()->provider_user_id)->toBe('g-123');

    // Linking the same account again is idempotent; another Google account replaces the link.
    $this->postJson('/api/v1/me/social-accounts/google', ['token' => 'provider-token'])->assertCreated();
    googleIdentity(sub: 'g-999');
    $this->postJson('/api/v1/me/social-accounts/google', ['token' => 'provider-token'])->assertCreated();

    expect($user->socialAccounts()->sole()->provider_user_id)->toBe('g-999');
});

it('refuses to link an account that belongs to another user (409)', function () {
    SocialAccount::factory()->create(['provider_user_id' => 'g-123']);
    googleIdentity();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/me/social-accounts/google', ['token' => 'provider-token'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'social_account_taken');
});

it('refuses to link for children (403) and invalid tokens (401)', function () {
    googleIdentity();
    $family = familyOwner()->family;
    actingAsMember(addMember($family, 9));

    $this->postJson('/api/v1/me/social-accounts/google', ['token' => 'provider-token'])
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');

    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/me/social-accounts/google', ['token' => 'bad'])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'social_token_invalid');

    expect(SocialAccount::query()->count())->toBe(0);
});

it('unlinks a provider when the user still has a password', function () {
    $user = User::factory()->create();
    SocialAccount::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/me/social-accounts/google')->assertNoContent();

    expect($user->socialAccounts()->count())->toBe(0);
});

it('unlinks a provider when another provider is still linked', function () {
    $user = User::factory()->create(['password' => null]);
    SocialAccount::factory()->create(['user_id' => $user->id]);
    SocialAccount::factory()->facebook()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/me/social-accounts/facebook')->assertNoContent();

    expect($user->socialAccounts()->sole()->provider)->toBe('google');
});

it('refuses to unlink the last sign-in method (409)', function () {
    $user = User::factory()->create(['password' => null]);
    SocialAccount::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/me/social-accounts/google')
        ->assertStatus(409)
        ->assertJsonPath('code', 'last_sign_in_method');

    expect($user->socialAccounts()->count())->toBe(1);
});

it('answers 404 when unlinking a provider that is not linked', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->deleteJson('/api/v1/me/social-accounts/facebook')
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');
});

it('requires authentication for my social accounts', function () {
    $this->getJson('/api/v1/me/social-accounts')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});
