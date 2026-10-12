<?php

use App\Modules\Content\Models\Category;
use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Actions\IssueSignInCode;
use App\Modules\Family\Actions\RemoveMember;
use App\Modules\Family\Actions\SignInWithCode;
use App\Modules\Family\Actions\UpdateMember;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\SignInCode;
use App\Modules\Goals\Actions\LoadGoalPlans;
use App\Modules\Goals\Models\GoalPeriod;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Actions\RevokeDevice;
use App\Modules\Identity\Actions\SetPassword;
use App\Modules\Identity\Models\Device;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

it('issues a code, stores only its hash and voids the member\'s older unused codes', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    $old = SignInCode::factory()->create(['member_id' => $kid->id]);
    $otherMembersCode = SignInCode::factory()->create(['member_id' => $owner->id]);

    $issued = app(IssueSignInCode::class)->handle($kid, $owner->user);

    expect($issued['sign_in_code']->code_hash)->toBe(SignInCode::hashCode($issued['code']))
        ->and($issued['sign_in_code']->expires_at->isFuture())->toBeTrue()
        ->and($old->fresh()->voided_at)->not->toBeNull()
        ->and($otherMembersCode->fresh()->voided_at)->toBeNull()
        ->and(SignInCode::query()->usable()->where('member_id', $kid->id)->count())->toBe(1);
});

it('refuses to issue codes for removed members', function () {
    $kid = addMember(familyOwner()->family, 9);
    $kid->forceFill(['status' => FamilyMember::STATUS_REMOVED])->save();

    app(IssueSignInCode::class)->handle($kid);
})->throws(ApiException::class);

it('redeems a code into a device-bound token and records the device', function () {
    $kid = addMember(familyOwner()->family, 9);
    $issued = app(IssueSignInCode::class)->handle($kid);

    $result = app(SignInWithCode::class)->handle(strtolower($issued['code']), 'Tablet', 'android', '1.2.0');

    $device = Device::query()->sole();
    expect($result['user']->id)->toBe($kid->user_id)
        ->and($device->app_version)->toBe('1.2.0')
        ->and($issued['sign_in_code']->fresh()->device_id)->toBe($device->id)
        ->and(PersonalAccessToken::findToken($result['token'])?->getKey())->toBe($device->personal_access_token_id);

    expect(fn () => app(SignInWithCode::class)->handle($issued['code'], 'Again', 'ios'))
        ->toThrow(fn (ApiException $e) => expect($e->errorCode)->toBe('sign_in_code_invalid'));
});

it('revokes a device token and row', function () {
    $owner = familyOwner();
    app(IssueDeviceToken::class)->handle($owner->user, 'Phone', 'ios');
    $device = Device::query()->sole();

    app(RevokeDevice::class)->handle($device);

    expect(Device::query()->count())->toBe(0)->and(PersonalAccessToken::query()->count())->toBe(0);
});

it('sets and changes a password only with an email login', function () {
    $owner = familyOwner();
    $teen = addMember($owner->family, 14);

    expect(fn () => app(SetPassword::class)->handle($teen->user, 'password1'))->toThrow(ApiException::class);

    app(UpdateMember::class)->handle($teen, ['email' => 'teen@example.com'], $owner);
    app(SetPassword::class)->handle($teen->user->fresh(), 'password1');

    expect(fn () => app(SetPassword::class)->handle($teen->user->fresh(), 'password2', 'wrong'))->toThrow(ApiException::class);
    app(SetPassword::class)->handle($teen->user->fresh(), 'password2', 'password1');
    expect(Hash::check('password2', $teen->user->fresh()->password))->toBeTrue();
});

it('removes a member who leaves the family from goal plans and open periods, keeping history', function () {
    $owner = familyOwner(['tier' => 'family']);
    $sam = addMember($owner->family, 9);
    $category = Category::factory()->create();
    $plan = GoalPlan::factory()->create(['family_id' => $owner->family_id]);
    $plan->categories()->attach($category->id);
    $plan->planMembers()->createMany([['member_id' => $owner->id], ['member_id' => $sam->id, 'target_override' => 10]]);
    $closed = GoalPeriod::query()->create([
        'goal_plan_id' => $plan->id, 'starts_at' => now()->subWeeks(2), 'ends_at' => now()->subWeek(),
        'status' => GoalPeriod::STATUS_CLOSED, 'target_points' => 50,
        'member_targets' => [(string) $owner->id => 50, (string) $sam->id => 10],
        'result' => ['team_total' => 0, 'reached' => false, 'winner_member_ids' => [], 'entries' => []],
    ]);
    $loaded = app(LoadGoalPlans::class)->forFamily($owner->family);
    $open = $loaded->first()->getRelation('currentPeriod');
    expect($open->member_targets)->toHaveKey($sam->id);

    app(RemoveMember::class)->handle($sam->load('family', 'user'));

    expect($plan->planMembers()->pluck('member_id')->all())->toBe([$owner->id])
        ->and($open->fresh()->member_targets)->toBe([$owner->id => 50])
        ->and($closed->fresh()->member_targets)->toHaveKey($sam->id);

    $progress = app(LoadGoalPlans::class)->forFamily($owner->family)->first()->getRelation('currentPeriod')->progress;
    expect(array_column($progress['entries'], 'member_id'))->toBe([$owner->id]);
});
