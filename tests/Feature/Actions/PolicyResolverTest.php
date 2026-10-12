<?php

use App\Modules\Policy\Models\AgeBand;
use App\Modules\Policy\Models\Setting;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Support\Carbon;

function resolver(): PolicyResolver
{
    return app(PolicyResolver::class);
}

it('computes age from birth year and month', function () {
    $at = Carbon::create(2026, 10, 10);

    expect(resolver()->age(2013, 12, $at))->toBe(12) // December 2013: still 12 for most of 2026
        ->and(resolver()->age(2013, 10, $at))->toBe(13) // birthday month counts as passed
        ->and(resolver()->age(2013, 3, $at))->toBe(13)
        ->and(resolver()->age(null, null, $at))->toBeNull();
});

it('maps ages to the seeded bands', function (int $age, string $band) {
    expect(resolver()->bandForAge($age)?->code)->toBe($band);
})->with([[0, 'child'], [12, 'child'], [13, 'teen'], [17, 'teen'], [18, 'adult'], [64, 'adult'], [65, 'senior'], [99, 'senior']]);

it('prefers a country-specific band', function () {
    AgeBand::factory()->create(['code' => 'teen', 'country_code' => 'DE', 'min_age' => 13, 'max_age' => 15, 'policy' => ['collect_email' => false]]);

    expect(resolver()->bandForAge(14, 'DE')?->country_code)->toBe('DE')
        ->and(resolver()->bandForAge(14, 'CA')?->country_code)->toBeNull()
        ->and(resolver()->bandForAge(16, 'DE')?->country_code)->toBeNull();
});

it('resolves settings age_band > tier > global > default', function () {
    Setting::factory()->create(['key' => 'play.target_points', 'scope' => 'tier', 'scope_id' => 'family', 'value' => 12]);
    Setting::factory()->create(['key' => 'play.target_points', 'scope' => 'age_band', 'scope_id' => 'child', 'value' => 6]);

    expect(resolver()->setting('play.target_points'))->toBe(10)
        ->and(resolver()->setting('play.target_points', 'family'))->toBe(12)
        ->and(resolver()->setting('play.target_points', 'family', 'child'))->toBe(6)
        ->and(resolver()->setting('not.there', null, null, 'fallback'))->toBe('fallback');
});

it('picks up setting changes immediately (cache flushed on save)', function () {
    expect(resolver()->minAppVersion())->toBe('1.0.0');

    Setting::query()->where('key', 'min_app_version')->firstOrFail()->update(['value' => '1.5.0']);

    expect(resolver()->minAppVersion())->toBe('1.5.0');
});

it('builds the child policy: no ads, no purchases, redemption only', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);

    $policy = resolver()->forMember($kid->load('family', 'user'));

    expect($policy)->toMatchArray([
        'age_band' => 'child',
        'age' => 9,
        'is_child' => true,
        'ui_theme' => 'playful',
        'tier' => 'free',
        'ads_allowed' => false,
        'purchases_allowed' => false,
        'collect_email' => false,
        'email_login_allowed' => false,
        'difficulty' => ['min' => 1, 'max' => 4, 'default' => 2],
    ])->and($policy['second_chance'])->toBe(['free_per_day' => 0, 'free_used_today' => 0, 'ad_allowed' => false, 'redemption_allowed' => true]);
});

it('allows rewarded ads for teens and adults on the free tier only', function () {
    $free = familyOwner();
    $teen = addMember($free->family, 14);
    $paid = familyOwner(['tier' => 'family']);

    expect(resolver()->forMember($teen)['ads_allowed'])->toBeTrue()
        ->and(resolver()->forMember($free)['ads_allowed'])->toBeTrue()
        ->and(resolver()->forMember($paid)['ads_allowed'])->toBeFalse()
        ->and(resolver()->forMember($paid)['second_chance']['free_per_day'])->toBe(3);
});

it('centres the difficulty range on a member override', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 11);
    $kid->update(['difficulty_override' => 7]);

    expect(resolver()->difficultyForMember($kid))->toBe(['min' => 5, 'max' => 9, 'default' => 7]);

    $kid->update(['difficulty_override' => 1]);
    expect(resolver()->difficultyForMember($kid))->toBe(['min' => 1, 'max' => 3, 'default' => 1]);
});

it('derives play rules from settings', function () {
    $owner = familyOwner();

    expect(resolver()->playRules($owner))->toBe(['target_points' => 10, 'blunder_cap' => 3, 'redemption_items' => 3]);
});
