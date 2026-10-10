<?php

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Actions\CreateFamily;
use App\Modules\Family\Actions\CreateInvite;
use App\Modules\Family\Actions\GenerateInviteCode;
use App\Modules\Family\Actions\JoinFamilyWithInvite;
use App\Modules\Family\Actions\RemoveMember;
use App\Modules\Family\Actions\RevokeInvite;
use App\Modules\Family\Actions\UpdateAccessibility;
use App\Modules\Family\Actions\UpdateFamily;
use App\Modules\Family\Actions\UpdateMember;
use App\Modules\Family\Models\Invite;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Actions\LoginWithPassword;
use App\Modules\Identity\Actions\RegisterAdult;
use App\Modules\Identity\Actions\RevokeCurrentDevice;
use App\Modules\Identity\Actions\UpdateProfile;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

it('IssueDeviceToken creates a token and a bound device', function () {
    $user = User::factory()->create();

    $plain = app(IssueDeviceToken::class)->handle($user, 'iPhone', 'ios', '1.0.3');

    $token = PersonalAccessToken::findToken($plain);
    expect($token?->tokenable_id)->toBe($user->id)
        ->and(Device::query()->sole()->personal_access_token_id)->toBe($token?->id)
        ->and(Device::query()->sole()->app_version)->toBe('1.0.3');
});

it('RegisterAdult refuses minors and creates adults', function () {
    $action = app(RegisterAdult::class);

    expect(fn () => $action->handle(['display_name' => 'Kid', 'email' => 'k@x.com', 'password' => 'secret123', 'birth_year' => now()->year - 10, 'birth_month' => 1], 'd', 'web'))
        ->toThrow(ApiException::class);

    $result = $action->handle(['display_name' => 'Mom', 'email' => 'MOM@x.com', 'password' => 'secret123', 'birth_year' => 1990, 'birth_month' => 1], 'd', 'web');
    expect($result['user']->email)->toBe('mom@x.com')->and($result['token'])->toBeString();
});

it('LoginWithPassword rejects child accounts without a password', function () {
    User::factory()->child()->create();

    expect(fn () => app(LoginWithPassword::class)->handle('nobody@x.com', 'password', 'd', 'web'))
        ->toThrow(fn (ApiException $e) => expect($e->errorCode)->toBe('invalid_credentials'));
});

it('RevokeCurrentDevice deletes the token and device', function () {
    $user = User::factory()->create();
    $plain = app(IssueDeviceToken::class)->handle($user, 'iPhone', 'ios');
    $user->withAccessToken(PersonalAccessToken::findToken($plain));

    app(RevokeCurrentDevice::class)->handle($user);

    expect(PersonalAccessToken::query()->count())->toBe(0)->and(Device::query()->count())->toBe(0);
});

it('UpdateProfile only touches profile fields', function () {
    $user = User::factory()->create(['is_staff' => false]);

    app(UpdateProfile::class)->handle($user, ['display_name' => 'New', 'is_staff' => true]);

    expect($user->refresh()->display_name)->toBe('New')->and($user->is_staff)->toBeFalse();
});

it('CreateFamily makes the owner an admin and refuses a second family', function () {
    $user = User::factory()->create();
    $family = app(CreateFamily::class)->handle($user, ['name' => 'Fam', 'timezone' => 'UTC', 'country_code' => 'us']);

    expect($family->country_code)->toBe('US')
        ->and($user->refresh()->activeMembership()?->isAdmin())->toBeTrue();

    expect(fn () => app(CreateFamily::class)->handle($user, ['name' => 'Again', 'timezone' => 'UTC', 'country_code' => 'US']))
        ->toThrow(fn (ApiException $e) => expect($e->errorCode)->toBe('already_in_family'));
});

it('UpdateFamily changes name and timezone only', function () {
    $owner = familyOwner();

    app(UpdateFamily::class)->handle($owner->family, ['name' => 'Renamed', 'timezone' => 'Asia/Tokyo', 'tier' => 'family']);

    expect($owner->family->refresh()->name)->toBe('Renamed')->and($owner->family->tier)->toBe('free');
});

it('UpdateMember protects the owner role and merges accessibility', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);

    expect(fn () => app(UpdateMember::class)->handle($owner, ['role' => 'member']))->toThrow(ApiException::class);

    app(UpdateMember::class)->handle($kid, ['accessibility' => ['text_scale' => 1.2]]);
    app(UpdateMember::class)->handle($kid, ['accessibility' => ['calm_timers' => true], 'avatar' => 'cat']);

    expect($kid->refresh()->accessibility)->toBe(['text_scale' => 1.2, 'calm_timers' => true])
        ->and($kid->user->avatar)->toBe('cat');
});

it('UpdateAccessibility stores normalized values', function () {
    $owner = familyOwner();

    app(UpdateAccessibility::class)->handle($owner, ['text_scale' => '1.333', 'reduced_motion' => 1, 'unknown' => 'x']);

    expect($owner->refresh()->accessibility)->toBe(['text_scale' => 1.33, 'reduced_motion' => true]);
});

it('RemoveMember soft-removes and revokes child tokens', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    app(IssueDeviceToken::class)->handle($kid->user, 'tablet', 'ios');

    app(RemoveMember::class)->handle($kid);

    expect($kid->refresh()->status)->toBe('removed')
        ->and($kid->user->tokens()->count())->toBe(0)
        ->and(fn () => app(RemoveMember::class)->handle($owner))->toThrow(ApiException::class);
});

it('GenerateInviteCode uses the unambiguous alphabet', function () {
    foreach (range(1, 20) as $ignored) {
        expect(app(GenerateInviteCode::class)->handle())->toMatch('/^[A-HJ-NP-Z2-9]{8}$/');
    }
});

it('CreateInvite and RevokeInvite respect the member limit', function () {
    $owner = familyOwner();

    $invite = app(CreateInvite::class)->handle($owner, ['nickname' => 'Sam', 'birth_year' => 2017, 'birth_month' => 3]);
    expect(fn () => app(CreateInvite::class)->handle($owner, ['nickname' => 'Two', 'birth_year' => 2017, 'birth_month' => 3]))
        ->toThrow(fn (ApiException $e) => expect($e->errorCode)->toBe('member_limit_reached'));

    app(RevokeInvite::class)->handle($invite);
    expect(app(CreateInvite::class)->handle($owner, ['nickname' => 'Two', 'birth_year' => 2017, 'birth_month' => 3]))->toBeInstanceOf(Invite::class);
});

it('JoinFamilyWithInvite creates the member and consumes the code once', function () {
    $owner = familyOwner();
    Invite::factory()->create(['family_id' => $owner->family_id, 'code' => 'JOIN2345', 'nickname' => 'Sam']);

    $result = app(JoinFamilyWithInvite::class)->handle('join2345', 'Tablet', 'ios');

    expect($result['user']->activeMembership()?->family_id)->toBe($owner->family_id)
        ->and($result['user']->email)->toBeNull();

    expect(fn () => app(JoinFamilyWithInvite::class)->handle('JOIN2345', 'Tablet', 'ios'))
        ->toThrow(fn (ApiException $e) => expect($e->errorCode)->toBe('invite_invalid'));
});
