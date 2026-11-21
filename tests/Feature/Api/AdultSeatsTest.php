<?php

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Actions\UpdateMember;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\Invite;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\Models\Setting;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/**
 * B12 step 3a (docs/API.md "Classroom tools" → "Adult seats"): policy `limits.max_adults` (tier
 * setting; classroom 2, other tiers unlimited), 403 `adult_limit_reached` on adult invites, and
 * admins must be adults on every tier (422 on `role`, checked on change only).
 */

/**
 * Invite someone of this age (birth month January) as the acting admin.
 */
function seatInvite(int $age, string $nickname = 'Someone'): TestResponse
{
    return test()->postJson('/api/v1/family/invites', [
        'nickname' => $nickname,
        'birth_year' => now()->year - $age,
        'birth_month' => 1,
        'parental_consent' => true,
    ]);
}

// ---------------------------------------------------------------------------------------------
// Policy limits.max_adults
// ---------------------------------------------------------------------------------------------

it('exposes limits.max_adults per tier in the policy', function (string $tier, ?int $maxAdults) {
    $owner = familyOwner(['tier' => $tier]);
    actingAsMember($owner);

    expect(app(PolicyResolver::class)->maxAdultsFor($tier))->toBe($maxAdults);

    $this->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.policy.limits.max_adults', $maxAdults);
})->with([
    'free' => ['free', null],
    'family' => ['family', null],
    'big_clan' => ['big_clan', null],
    'classroom' => ['classroom', 2],
]);

it('gives users without a family the free tier max_adults (null)', function () {
    Sanctum::actingAs(User::factory()->aged(30)->create());

    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.policy.limits.max_adults', null);
});

it('treats adults and seniors as adults, and children and teens not', function () {
    $policy = app(PolicyResolver::class);

    expect($policy->adultMinAge())->toBe(18)
        ->and($policy->isAdultBand($policy->bandForAge(18)))->toBeTrue()
        ->and($policy->isAdultBand($policy->bandForAge(70)))->toBeTrue()
        ->and($policy->isAdultBand($policy->bandForAge(17)))->toBeFalse()
        ->and($policy->isAdultBand($policy->bandForAge(8)))->toBeFalse();
});

it('adds the max_adults tier settings to an existing database, keeping admin edits', function () {
    Setting::query()->where('key', 'limits.max_adults')->delete();
    Setting::query()->create(['key' => 'limits.max_adults', 'scope' => 'tier', 'scope_id' => 'classroom', 'value' => 3, 'description' => 'Admin edit']);

    $migration = require database_path('migrations/2026_11_20_300000_add_max_adults_tier_settings.php');
    $migration->up();
    $migration->up(); // twice changes nothing

    $rows = Setting::query()->where('key', 'limits.max_adults')->where('scope', 'tier')->get()->keyBy('scope_id');

    expect($rows)->toHaveCount(4)
        ->and($rows['free']->value)->toBeNull()
        ->and($rows['family']->value)->toBeNull()
        ->and($rows['big_clan']->value)->toBeNull()
        ->and($rows['classroom']->value)->toBe(3);
});

// ---------------------------------------------------------------------------------------------
// Adult invites
// ---------------------------------------------------------------------------------------------

it('refuses a third adult in a classroom, counting pending adult invites', function () {
    $teacher = familyOwner(['tier' => Family::TIER_CLASSROOM]);
    actingAsMember($teacher);

    seatInvite(35, 'Co-teacher')->assertCreated();

    seatInvite(30, 'Third adult')->assertStatus(403)
        ->assertJsonPath('code', 'adult_limit_reached')
        ->assertJsonPath('message', 'Your plan does not allow more adults in this group.');
    seatInvite(70, 'Senior')->assertStatus(403)->assertJsonPath('code', 'adult_limit_reached');

    expect(Invite::query()->count())->toBe(1);

    // Students (children and teens) still join.
    seatInvite(9, 'Ava')->assertCreated();
    seatInvite(15, 'Teen')->assertCreated();
});

it('counts active adult members and seniors', function () {
    $teacher = familyOwner(['tier' => Family::TIER_CLASSROOM]);
    addMember($teacher->family, 68);
    addMember($teacher->family, 10);
    actingAsMember($teacher);

    seatInvite(40)->assertStatus(403)->assertJsonPath('code', 'adult_limit_reached');
    seatInvite(10)->assertCreated();
});

it('ignores removed members and used or expired invites', function () {
    $teacher = familyOwner(['tier' => Family::TIER_CLASSROOM]);
    addMember($teacher->family, 40)->forceFill(['status' => FamilyMember::STATUS_REMOVED])->save();
    Invite::factory()->adult()->expired()->create(['family_id' => $teacher->family_id]);
    Invite::factory()->adult()->used()->create(['family_id' => $teacher->family_id]);
    actingAsMember($teacher);

    seatInvite(40)->assertCreated();
    seatInvite(41)->assertStatus(403)->assertJsonPath('code', 'adult_limit_reached');
});

it('counts someone who turns 18 this month as an adult, and not a 17-year-old', function () {
    $teacher = familyOwner(['tier' => Family::TIER_CLASSROOM]);
    actingAsMember($teacher);

    $this->postJson('/api/v1/family/invites', ['nickname' => 'Almost', 'birth_year' => now()->year - 18, 'birth_month' => min(12, now()->month + 1), 'parental_consent' => true])->assertCreated();

    $this->postJson('/api/v1/family/invites', ['nickname' => 'Birthday', 'birth_year' => now()->year - 18, 'birth_month' => now()->month])->assertCreated();
    seatInvite(30)->assertStatus(403)->assertJsonPath('code', 'adult_limit_reached');
})->skip(fn () => now()->month === 12, 'Needs a later month in the same year.');

it('lets families have any number of adults up to the member limit', function (string $tier) {
    $owner = familyOwner(['tier' => $tier]);
    actingAsMember($owner);

    foreach (range(1, app(PolicyResolver::class)->maxMembersFor($tier) - 1) as $i) {
        seatInvite(30 + $i, "Adult {$i}")->assertCreated();
    }

    seatInvite(30)->assertStatus(403)->assertJsonPath('code', 'member_limit_reached');
})->with(['free', 'family', 'big_clan']);

it('keeps the seats of adults over the limit after a plan change', function () {
    $owner = familyOwner(['tier' => Family::TIER_BIG_CLAN]);
    $dad = addMember($owner->family, 42, FamilyMember::ROLE_ADMIN);
    $grandpa = addMember($owner->family, 70);
    $owner->family->forceFill(['tier' => Family::TIER_CLASSROOM])->save();
    actingAsMember($owner);

    $this->getJson('/api/v1/family')->assertOk();
    expect($owner->family->members()->count())->toBe(3);

    $this->patchJson("/api/v1/family/members/{$grandpa->id}", ['nickname' => 'Grandpa'])->assertOk();
    $this->patchJson("/api/v1/family/members/{$dad->id}", ['role' => 'admin'])->assertOk();

    seatInvite(35)->assertStatus(403)->assertJsonPath('code', 'adult_limit_reached');
    seatInvite(9)->assertCreated();
});

it('counts adult seats with a constant number of queries', function () {
    $queries = function (int $adults): int {
        $teacher = familyOwner(['tier' => Family::TIER_CLASSROOM]);
        Setting::query()->where('key', 'limits.max_adults')->where('scope_id', 'classroom')->firstOrFail()->update(['value' => 50]);

        for ($i = 0; $i < $adults; $i++) {
            addMember($teacher->family, 30 + $i);
            Invite::factory()->adult()->create(['family_id' => $teacher->family_id]);
        }

        app('auth')->forgetGuards();
        actingAsMember($teacher);
        seatInvite(9, 'Warm-up')->assertCreated();

        DB::flushQueryLog();
        DB::enableQueryLog();
        seatInvite(40)->assertCreated();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    expect($queries(2))->toBe($queries(8));
});

// ---------------------------------------------------------------------------------------------
// Admins must be adults
// ---------------------------------------------------------------------------------------------

it('refuses to make a child or teen an admin on every tier', function (string $tier, int $age) {
    $owner = familyOwner(['tier' => $tier]);
    $kid = addMember($owner->family, $age);
    actingAsMember($owner);

    $this->patchJson("/api/v1/family/members/{$kid->id}", ['role' => 'admin', 'nickname' => 'Boss'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonPath('errors.role.0', 'Only adults can be family admins.');

    expect($kid->refresh()->role)->toBe(FamilyMember::ROLE_MEMBER)
        ->and($kid->nickname)->not->toBe('Boss');
})->with([
    'free child' => ['free', 9],
    'family teen' => ['family', 16],
    'big_clan child' => ['big_clan', 7],
    'classroom teen' => ['classroom', 14],
]);

it('makes an adult or senior an admin', function (int $age) {
    $owner = familyOwner(['tier' => Family::TIER_FAMILY]);
    $adult = addMember($owner->family, $age);
    actingAsMember($owner);

    $this->patchJson("/api/v1/family/members/{$adult->id}", ['role' => 'admin'])
        ->assertOk()->assertJsonPath('data.role', 'admin');
})->with([18, 40, 75]);

it('checks the birth data sent in the same request', function () {
    $owner = familyOwner(['tier' => Family::TIER_FAMILY]);
    $kid = addMember($owner->family, 9);
    $dad = addMember($owner->family, 40);
    actingAsMember($owner);

    // A typo fixed in the same request: the corrected age is an adult.
    $this->patchJson("/api/v1/family/members/{$kid->id}", ['role' => 'admin', 'birth_year' => now()->year - 39])
        ->assertOk()->assertJsonPath('data.role', 'admin');

    $this->patchJson("/api/v1/family/members/{$dad->id}", ['role' => 'admin', 'birth_year' => now()->year - 12])
        ->assertStatus(422)->assertJsonPath('errors.role.0', 'Only adults can be family admins.');
    expect($dad->refresh()->role)->toBe(FamilyMember::ROLE_MEMBER);
});

it('leaves existing child admins as they are', function () {
    $owner = familyOwner(['tier' => Family::TIER_FAMILY]);
    $teenAdmin = addMember($owner->family, 15, FamilyMember::ROLE_ADMIN);
    actingAsMember($owner);

    $this->patchJson("/api/v1/family/members/{$teenAdmin->id}", ['role' => 'admin', 'nickname' => 'Captain'])
        ->assertOk()->assertJsonPath('data.role', 'admin')->assertJsonPath('data.nickname', 'Captain');

    $this->patchJson("/api/v1/family/members/{$teenAdmin->id}", ['role' => 'member'])
        ->assertOk()->assertJsonPath('data.role', 'member');
    $this->patchJson("/api/v1/family/members/{$teenAdmin->id}", ['role' => 'admin'])
        ->assertStatus(422)->assertJsonPath('errors.role.0', 'Only adults can be family admins.');
});

it('applies the same rule in the UpdateMember action', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);

    expect(fn () => app(UpdateMember::class)->handle($kid, ['role' => FamilyMember::ROLE_ADMIN]))
        ->toThrow(ApiException::class, 'Only adults can be family admins.');
});
