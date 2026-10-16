<?php

use App\Modules\Family\Models\SignInCode;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\SocialAccount;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

/**
 * B2b — member sign-in without email (docs/API.md 2026-10-11).
 */
function signInWith(string $code): TestResponse
{
    return test()->postJson('/api/v1/auth/code', ['code' => $code, 'device_name' => "Sam's iPad", 'platform' => 'ios']);
}

/**
 * Sign the user in with a real Sanctum token (so `is_current` and revocation can be checked).
 */
function bearerFor(User $user, string $deviceName = 'Phone'): string
{
    return app(IssueDeviceToken::class)->handle($user, $deviceName, 'android');
}

// ---------------------------------------------------------------------------------------------
// Issuing codes
// ---------------------------------------------------------------------------------------------

it('lets an admin issue a 15-minute sign-in code for a member', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    actingAsMember($owner);

    $response = $this->postJson("/api/v1/family/members/{$kid->id}/sign-in-codes")
        ->assertCreated()
        ->assertJsonPath('data.member_id', $kid->id);

    $code = $response->json('data.code');
    expect($code)->toMatch('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{8}$/')
        ->and(Carbon\Carbon::parse($response->json('data.expires_at'))->diffInMinutes(now(), true))->toBeGreaterThan(14.9)->toBeLessThanOrEqual(15);

    $stored = SignInCode::query()->sole();
    expect($stored->code_hash)->not->toBe($code)
        ->and($stored->code_hash)->toBe(SignInCode::hashCode($code))
        ->and($stored->created_by)->toBe($owner->user_id);
});

it('voids the member\'s older unused codes when a new one is issued', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    actingAsMember($owner);

    $first = $this->postJson("/api/v1/family/members/{$kid->id}/sign-in-codes")->json('data.code');
    $second = $this->postJson("/api/v1/family/members/{$kid->id}/sign-in-codes")->json('data.code');
    app('auth')->forgetGuards();

    signInWith($first)->assertStatus(422)->assertJsonPath('code', 'sign_in_code_invalid');
    signInWith($second)->assertOk();
});

it('refuses sign-in codes for non-admins and for members of another family', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    $other = familyOwner();
    $stranger = addMember($other->family, 10);

    actingAsMember($kid);
    $this->postJson("/api/v1/family/members/{$kid->id}/sign-in-codes")->assertForbidden()->assertJsonPath('code', 'forbidden');

    app('auth')->forgetGuards();
    actingAsMember($owner);
    $this->postJson("/api/v1/family/members/{$stranger->id}/sign-in-codes")->assertNotFound();

    expect(SignInCode::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------------------------
// POST /auth/code
// ---------------------------------------------------------------------------------------------

it('signs a child in on a new device with a code', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    SignInCode::factory()->withCode('K7QM4XPA')->create(['member_id' => $kid->id]);

    $response = signInWith('k7qm-4xpa')
        ->assertOk()
        ->assertJsonPath('data.user.id', $kid->user_id)
        ->assertJsonPath('data.member.id', $kid->id)
        ->assertJsonPath('data.family.id', $owner->family_id)
        ->assertJsonPath('data.policy.email_login_allowed', false);

    $device = Device::query()->sole();
    expect($device->user_id)->toBe($kid->user_id)
        ->and($device->name)->toBe("Sam's iPad")
        ->and(SignInCode::query()->sole()->used_at)->not->toBeNull()
        ->and(SignInCode::query()->sole()->device_id)->toBe($device->id);

    app('auth')->forgetGuards();
    $this->withToken($response->json('data.token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.member.id', $kid->id);
});

it('refuses used, expired, voided and unknown codes', function (string $state) {
    $kid = addMember(familyOwner()->family, 9);
    $factory = SignInCode::factory()->withCode('ABCDEFGH');

    match ($state) {
        'used' => $factory->used()->create(['member_id' => $kid->id]),
        'expired' => $factory->expired()->create(['member_id' => $kid->id]),
        'voided' => $factory->create(['member_id' => $kid->id, 'voided_at' => now()]),
        'unknown' => null,
    };

    signInWith('ABCDEFGH')->assertStatus(422)->assertJsonPath('code', 'sign_in_code_invalid');
    expect(Device::query()->count())->toBe(0);
})->with(['used', 'expired', 'voided', 'unknown']);

it('accepts a code only once', function () {
    $kid = addMember(familyOwner()->family, 9);
    SignInCode::factory()->withCode('ABCDEFGH')->create(['member_id' => $kid->id]);

    signInWith('ABCDEFGH')->assertOk();
    app('auth')->forgetGuards();
    signInWith('ABCDEFGH')->assertStatus(422)->assertJsonPath('code', 'sign_in_code_invalid');
});

it('expires codes after 15 minutes', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    actingAsMember($owner);
    $code = $this->postJson("/api/v1/family/members/{$kid->id}/sign-in-codes")->json('data.code');
    app('auth')->forgetGuards();

    $this->travel(16)->minutes();

    signInWith($code)->assertStatus(422)->assertJsonPath('code', 'sign_in_code_invalid');
});

it('refuses codes of a member who left the family and voids them on removal', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    SignInCode::factory()->withCode('ABCDEFGH')->create(['member_id' => $kid->id]);

    actingAsMember($owner);
    $this->deleteJson("/api/v1/family/members/{$kid->id}")->assertNoContent();
    app('auth')->forgetGuards();

    expect(SignInCode::query()->sole()->voided_at)->not->toBeNull();
    signInWith('ABCDEFGH')->assertStatus(422);
});

it('validates the device fields on /auth/code', function () {
    $this->postJson('/api/v1/auth/code', ['code' => 'ABCDEFGH'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['device_name', 'platform']);
});

// ---------------------------------------------------------------------------------------------
// Devices
// ---------------------------------------------------------------------------------------------

it('lists a member\'s devices for admins and for the member themself', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    $sibling = addMember($owner->family, 11);
    $kidToken = bearerFor($kid->user, 'Kid tablet');
    bearerFor($kid->user, 'Old phone');
    $ownerToken = bearerFor($owner->user);

    $this->withToken($ownerToken)->getJson("/api/v1/family/members/{$kid->id}/devices")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'platform', 'app_version', 'last_seen_at', 'is_current']]])
        ->assertJsonPath('data.0.is_current', false);

    app('auth')->forgetGuards();
    $own = $this->withToken($kidToken)->getJson("/api/v1/family/members/{$kid->id}/devices")->assertOk();
    expect(collect($own->json('data'))->firstWhere('name', 'Kid tablet')['is_current'])->toBeTrue()
        ->and(collect($own->json('data'))->firstWhere('name', 'Old phone')['is_current'])->toBeFalse();

    app('auth')->forgetGuards();
    $this->withToken($kidToken)->getJson("/api/v1/family/members/{$sibling->id}/devices")
        ->assertForbidden()->assertJsonPath('code', 'forbidden');
});

it('signs a device out by revoking its token', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    $lostTablet = bearerFor($kid->user, 'Lost tablet');
    $device = Device::query()->where('name', 'Lost tablet')->sole();
    actingAsMember($owner);

    $this->deleteJson("/api/v1/family/members/{$kid->id}/devices/{$device->id}")->assertNoContent();

    expect(Device::query()->find($device->id))->toBeNull()
        ->and(PersonalAccessToken::query()->find($device->personal_access_token_id))->toBeNull();

    app('auth')->forgetGuards();
    $this->withToken($lostTablet)->getJson('/api/v1/me')->assertUnauthorized();
});

it('lets members sign out their own devices but not other members\' devices', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    $kidToken = bearerFor($kid->user, 'Tablet');
    bearerFor($owner->user, 'Mom phone');
    $momDevice = Device::query()->where('name', 'Mom phone')->sole();
    $kidDevice = Device::query()->where('name', 'Tablet')->sole();

    $this->withToken($kidToken)->deleteJson("/api/v1/family/members/{$owner->id}/devices/{$momDevice->id}")->assertForbidden();
    // A device id that belongs to someone else is not found under this member.
    $this->withToken($kidToken)->deleteJson("/api/v1/family/members/{$kid->id}/devices/{$momDevice->id}")->assertNotFound();
    $this->withToken($kidToken)->deleteJson("/api/v1/family/members/{$kid->id}/devices/{$kidDevice->id}")->assertNoContent();

    expect(Device::query()->pluck('name')->all())->toBe(['Mom phone']);
});

it('returns 404 for devices of a member in another family', function () {
    $owner = familyOwner();
    $stranger = addMember(familyOwner()->family, 9);
    actingAsMember($owner);

    $this->getJson("/api/v1/family/members/{$stranger->id}/devices")->assertNotFound();
});

// ---------------------------------------------------------------------------------------------
// Admin-managed email login
// ---------------------------------------------------------------------------------------------

it('lets an admin add an email login for a teen, and the teen set a password', function () {
    $owner = familyOwner();
    $teen = addMember($owner->family, 14);
    actingAsMember($owner);

    $this->patchJson("/api/v1/family/members/{$teen->id}", ['email' => ' Alex@Example.com '])
        ->assertOk()
        ->assertJsonPath('data.email', 'alex@example.com')
        ->assertJsonPath('data.has_password', false);

    app('auth')->forgetGuards();
    actingAsMember($teen);
    $this->getJson('/api/v1/me')->assertJsonPath('data.member.email', 'alex@example.com')->assertJsonPath('data.policy.email_login_allowed', true);

    $this->putJson('/api/v1/me/password', ['password' => 'secret-pass', 'password_confirmation' => 'secret-pass'])->assertNoContent();
    expect(Hash::check('secret-pass', $teen->user->fresh()->password))->toBeTrue();

    app('auth')->forgetGuards();
    $this->postJson('/api/v1/auth/login', ['email' => 'alex@example.com', 'password' => 'secret-pass', 'device_name' => 'Laptop', 'platform' => 'web'])
        ->assertOk()->assertJsonPath('data.member.id', $teen->id);
});

it('refuses an email login for a child', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    actingAsMember($owner);

    $this->patchJson("/api/v1/family/members/{$kid->id}", ['email' => 'kid@example.com'])
        ->assertStatus(422)->assertJsonValidationErrors(['email']);

    expect($kid->user->fresh()->email)->toBeNull();
});

it('checks the email login against the birth data sent in the same request', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 12);
    actingAsMember($owner);

    $this->patchJson("/api/v1/family/members/{$kid->id}", ['birth_year' => now()->year - 13, 'birth_month' => 1, 'email' => 'now13@example.com'])
        ->assertOk()->assertJsonPath('data.age_band', 'teen')->assertJsonPath('data.email', 'now13@example.com');
});

it('refuses an email that is already taken and validates the format', function () {
    $owner = familyOwner();
    $teen = addMember($owner->family, 15);
    User::factory()->create(['email' => 'taken@example.com']);
    actingAsMember($owner);

    $this->patchJson("/api/v1/family/members/{$teen->id}", ['email' => 'TAKEN@example.com'])->assertStatus(422)->assertJsonValidationErrors(['email']);
    $this->patchJson("/api/v1/family/members/{$teen->id}", ['email' => 'not-an-email'])->assertStatus(422)->assertJsonValidationErrors(['email']);
});

it('removes the email login and clears the password with email null', function () {
    $owner = familyOwner(['tier' => 'family']);
    $adult = addMember($owner->family, 30);
    actingAsMember($owner);

    expect($adult->user->password)->not->toBeNull();

    $this->patchJson("/api/v1/family/members/{$adult->id}", ['email' => null])
        ->assertOk()->assertJsonPath('data.email', null)->assertJsonPath('data.has_password', false);

    $user = $adult->user->fresh();
    expect($user->email)->toBeNull()->and($user->password)->toBeNull();
});

it('only lets the owner change the owner\'s email login', function () {
    $owner = familyOwner(['tier' => 'family']);
    $coAdmin = addMember($owner->family, 40, 'admin');
    actingAsMember($coAdmin);

    $this->patchJson("/api/v1/family/members/{$owner->id}", ['email' => null])->assertForbidden();

    app('auth')->forgetGuards();
    actingAsMember($owner);
    $this->patchJson("/api/v1/family/members/{$owner->id}", ['email' => 'new-owner@example.com'])->assertOk()->assertJsonPath('data.email', 'new-owner@example.com');
});

it('shows email, has_password and email_login_allowed only to the member themself and admins', function () {
    $owner = familyOwner(['tier' => 'family']);
    $kid = addMember($owner->family, 9);
    $teen = addMember($owner->family, 15);

    actingAsMember($kid);
    $members = collect($this->getJson('/api/v1/family')->json('data.members'))->keyBy('id');
    expect($members[$kid->id])->toHaveKeys(['email', 'has_password', 'email_login_allowed'])
        ->and($members[$kid->id]['email_login_allowed'])->toBeFalse()
        ->and($members[$teen->id])->not->toHaveKey('email')
        ->and($members[$teen->id])->not->toHaveKey('email_login_allowed')
        ->and($members[$owner->id])->not->toHaveKey('has_password');

    app('auth')->forgetGuards();
    actingAsMember($owner);
    $members = collect($this->getJson('/api/v1/family')->json('data.members'))->keyBy('id');
    expect($members[$teen->id]['email'])->toBeNull()
        ->and($members[$teen->id]['email_login_allowed'])->toBeTrue()
        ->and($members[$kid->id]['email_login_allowed'])->toBeFalse()
        ->and($members[$owner->id]['has_password'])->toBeTrue()
        ->and($members[$owner->id]['email_login_allowed'])->toBeTrue();

    $this->getJson('/api/v1/me')->assertJsonPath('data.member.email_login_allowed', true);
    $this->patchJson("/api/v1/family/members/{$kid->id}", ['nickname' => 'Sammy'])->assertJsonPath('data.email_login_allowed', false);
});

it('shows has_own_login (email login or linked social account) only to the member themself and admins', function () {
    $owner = familyOwner(['tier' => 'family']);
    $kid = addMember($owner->family, 9);
    $socialOnly = addMember($owner->family, 30);
    $socialOnly->user->forceFill(['email' => null, 'password' => null])->save();
    SocialAccount::factory()->apple()->create(['user_id' => $socialOnly->user_id]);

    actingAsMember($owner);
    $members = collect($this->getJson('/api/v1/family')->json('data.members'))->keyBy('id');
    expect($members[$owner->id]['has_own_login'])->toBeTrue()
        ->and($members[$kid->id]['has_own_login'])->toBeFalse()
        ->and($members[$socialOnly->id]['has_own_login'])->toBeTrue();

    $this->getJson('/api/v1/me')->assertJsonPath('data.member.has_own_login', true);
    $this->patchJson("/api/v1/family/members/{$kid->id}", ['nickname' => 'Sammy'])->assertJsonPath('data.has_own_login', false);

    app('auth')->forgetGuards();
    actingAsMember($kid);
    $members = collect($this->getJson('/api/v1/family')->json('data.members'))->keyBy('id');
    expect($members[$kid->id]['has_own_login'])->toBeFalse()
        ->and($members[$owner->id])->not->toHaveKey('has_own_login')
        ->and($members[$socialOnly->id])->not->toHaveKey('has_own_login');
    $this->getJson('/api/v1/me')->assertJsonPath('data.member.has_own_login', false);
});

// ---------------------------------------------------------------------------------------------
// PUT /me/password
// ---------------------------------------------------------------------------------------------

it('requires the current password to change an existing one', function () {
    $owner = familyOwner();
    actingAsMember($owner);

    $this->putJson('/api/v1/me/password', ['password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertStatus(422)->assertJsonValidationErrors(['current_password']);

    $this->putJson('/api/v1/me/password', ['password' => 'new-password', 'password_confirmation' => 'new-password', 'current_password' => 'wrong'])
        ->assertStatus(422)->assertJsonValidationErrors(['current_password']);

    $this->putJson('/api/v1/me/password', ['password' => 'new-password', 'password_confirmation' => 'new-password', 'current_password' => 'password'])
        ->assertNoContent();

    expect(Hash::check('new-password', $owner->user->fresh()->password))->toBeTrue();
});

it('refuses a password without an email login and validates confirmation and length', function () {
    $kid = addMember(familyOwner()->family, 9);
    actingAsMember($kid);

    $this->putJson('/api/v1/me/password', ['password' => 'long-enough', 'password_confirmation' => 'long-enough'])
        ->assertStatus(422)->assertJsonValidationErrors(['password']);

    $this->putJson('/api/v1/me/password', ['password' => 'short', 'password_confirmation' => 'other'])
        ->assertStatus(422)->assertJsonValidationErrors(['password']);

    expect($kid->user->fresh()->password)->toBeNull();
});

it('requires authentication for the new endpoints', function (string $method, string $url) {
    $this->json($method, $url)->assertUnauthorized();
})->with([
    ['POST', '/api/v1/family/members/1/sign-in-codes'],
    ['GET', '/api/v1/family/members/1/devices'],
    ['DELETE', '/api/v1/family/members/1/devices/1'],
    ['PUT', '/api/v1/me/password'],
]);

it('documents the new endpoints in the OpenAPI spec', function () {
    Gate::define('viewApiDocs', fn ($user = null): bool => true);

    $paths = $this->getJson('/docs/api.json')->assertOk()->json('paths');

    expect($paths)->toHaveKeys([
        '/auth/code',
        '/family/members/{member}/sign-in-codes',
        '/family/members/{member}/devices',
        '/family/members/{member}/devices/{device}',
        '/me/password',
    ]);
});

it('exposes email_login_allowed in the policy per band', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    actingAsMember($kid);
    $this->getJson('/api/v1/me')->assertJsonPath('data.policy.email_login_allowed', false)->assertJsonPath('data.policy.collect_email', false);

    app('auth')->forgetGuards();
    Sanctum::actingAs($owner->user);
    $this->getJson('/api/v1/me')->assertJsonPath('data.policy.email_login_allowed', true);
});
