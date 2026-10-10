<?php

use App\Modules\Family\Models\Invite;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\User;
use Database\Seeders\DemoFamilySeeder;
use Laravel\Sanctum\PersonalAccessToken;

function registerPayload(array $overrides = []): array
{
    return [
        'display_name' => 'Moe',
        'email' => 'Moe@Example.com',
        'password' => 'secret123',
        'birth_year' => 1985,
        'birth_month' => 6,
        'device_name' => 'Pixel 9',
        'platform' => 'android',
        ...$overrides,
    ];
}

it('registers an adult and returns the auth payload', function () {
    $response = $this->postJson('/api/v1/auth/register', registerPayload(), ['X-App-Version' => '1.0.0'])
        ->assertCreated()
        ->assertJsonPath('data.user.email', 'moe@example.com')
        ->assertJsonPath('data.user.birth_year', 1985)
        ->assertJsonPath('data.member', null)
        ->assertJsonPath('data.family', null)
        ->assertJsonPath('data.policy.tier', 'free')
        ->assertJsonPath('data.policy.age_band', 'adult')
        ->assertJsonPath('data.policy.ui_theme', 'modern');

    expect($response->json('data.token'))->toBeString()->toContain('|');

    $device = Device::query()->sole();
    expect($device->platform)->toBe('android')
        ->and($device->app_version)->toBe('1.0.0')
        ->and($device->personal_access_token_id)->not->toBeNull();
});

it('refuses to register a child or teen', function (int $age) {
    $this->postJson('/api/v1/auth/register', registerPayload(['birth_year' => now()->year - $age]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('birth_year');

    expect(User::query()->count())->toBe(0);
})->with([9, 15]);

it('validates register input', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/auth/register', registerPayload(['email' => 'taken@example.com', 'password' => 'short', 'platform' => 'windows']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password', 'platform']);
});

it('logs in with email and password', function () {
    User::factory()->create(['email' => 'mom@example.com']);

    $this->postJson('/api/v1/auth/login', ['email' => 'MOM@example.com', 'password' => 'password', 'device_name' => 'iPhone', 'platform' => 'ios'])
        ->assertOk()
        ->assertJsonStructure(['data' => ['token', 'user', 'member', 'family', 'policy']]);

    expect(Device::query()->count())->toBe(1);
});

it('rejects wrong credentials with invalid_credentials', function () {
    User::factory()->create(['email' => 'mom@example.com']);

    $this->postJson('/api/v1/auth/login', ['email' => 'mom@example.com', 'password' => 'wrong', 'device_name' => 'iPhone', 'platform' => 'ios'])
        ->assertStatus(401)
        ->assertJsonPath('code', 'invalid_credentials');
});

it('logs out by revoking the token and its device', function () {
    $user = User::factory()->create(['email' => 'mom@example.com']);
    $token = $this->postJson('/api/v1/auth/login', ['email' => 'mom@example.com', 'password' => 'password', 'device_name' => 'iPhone', 'platform' => 'ios'])
        ->json('data.token');

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

    expect(PersonalAccessToken::query()->count())->toBe(0)
        ->and(Device::query()->count())->toBe(0);

    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401);
});

it('lets a child join with an invite code (case-insensitive) and no email', function () {
    $owner = familyOwner();
    $invite = Invite::factory()->create(['family_id' => $owner->family_id, 'created_by' => $owner->user_id, 'code' => 'K7QM4XPA', 'nickname' => 'Sam', 'avatar' => 'fox']);

    $response = $this->postJson('/api/v1/auth/join', ['code' => 'k7qm-4xpa', 'device_name' => 'Tablet', 'platform' => 'ios'])
        ->assertCreated()
        ->assertJsonPath('data.user.email', null)
        ->assertJsonPath('data.user.display_name', 'Sam')
        ->assertJsonPath('data.user.avatar', 'fox')
        ->assertJsonPath('data.member.nickname', 'Sam')
        ->assertJsonPath('data.member.role', 'member')
        ->assertJsonPath('data.member.is_child', true)
        ->assertJsonPath('data.family.id', $owner->family_id)
        ->assertJsonPath('data.policy.is_child', true)
        ->assertJsonPath('data.policy.ui_theme', 'playful');

    expect($invite->refresh()->used_at)->not->toBeNull()
        ->and($invite->used_by)->toBe($response->json('data.user.id'));

    $this->withToken($response->json('data.token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.member.nickname', 'Sam');
});

it('refuses email and password for a child invitee', function () {
    $owner = familyOwner();
    Invite::factory()->create(['family_id' => $owner->family_id, 'created_by' => $owner->user_id, 'code' => 'AAAABBBB']);

    $this->postJson('/api/v1/auth/join', ['code' => 'AAAABBBB', 'device_name' => 'Tablet', 'platform' => 'ios', 'email' => 'kid@example.com', 'password' => 'secret123'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('lets an adult invitee set email and password when joining', function () {
    $owner = familyOwner();
    Invite::factory()->adult()->create(['family_id' => $owner->family_id, 'created_by' => $owner->user_id, 'code' => 'DADS2345']);

    $this->postJson('/api/v1/auth/join', ['code' => 'DADS2345', 'device_name' => 'Phone', 'platform' => 'android', 'email' => 'dad@example.com', 'password' => 'secret123'])
        ->assertCreated()
        ->assertJsonPath('data.user.email', 'dad@example.com')
        ->assertJsonPath('data.member.is_child', false);

    $this->postJson('/api/v1/auth/login', ['email' => 'dad@example.com', 'password' => 'secret123', 'device_name' => 'Web', 'platform' => 'web'])->assertOk();
});

it('rejects unknown, used and expired invite codes', function () {
    $owner = familyOwner();
    Invite::factory()->used()->create(['family_id' => $owner->family_id, 'code' => 'USED2345']);
    Invite::factory()->expired()->create(['family_id' => $owner->family_id, 'code' => 'OLDD2345']);

    foreach (['NOPE2345', 'USED2345', 'OLDD2345'] as $code) {
        $this->postJson('/api/v1/auth/join', ['code' => $code, 'device_name' => 'Tablet', 'platform' => 'ios'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'invite_invalid');
    }
});

it('authenticates seeded child devices with a known plain token', function () {
    $this->seed(DemoFamilySeeder::class);

    $this->withToken('demo-sam-child-token')->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.member.nickname', 'Sam')
        ->assertJsonPath('data.policy.age', 9);
});
