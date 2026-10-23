<?php

use App\Modules\Identity\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns the signed-in user without a family', function () {
    Sanctum::actingAs(User::factory()->aged(30)->create());

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.member', null)
        ->assertJsonPath('data.family', null)
        ->assertJsonPath('data.policy.tier', 'free')
        ->assertJsonPath('data.policy.limits.max_members', 2)
        ->assertJsonPath('data.policy.limits.max_categories', 3)
        ->assertJsonPath('data.policy.ads_allowed', true)
        ->assertJsonPath('data.policy.second_chance.free_per_day', 0);
});

it('returns user, member, family and policy for a family member', function () {
    $owner = familyOwner(['tier' => 'family']);
    actingAsMember($owner);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.member.id', $owner->id)
        ->assertJsonPath('data.member.role', 'admin')
        ->assertJsonPath('data.family.member_count', 1)
        ->assertJsonPath('data.policy.tier', 'family')
        ->assertJsonPath('data.policy.ads_allowed', false)
        ->assertJsonPath('data.policy.second_chance.free_per_day', 3);
});

it('updates the profile', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me', ['display_name' => 'Mama', 'avatar' => 'koala', 'locale' => 'fr'])
        ->assertOk()
        ->assertJsonPath('data.display_name', 'Mama')
        ->assertJsonPath('data.avatar', 'koala')
        ->assertJsonPath('data.locale', 'fr');

    $this->patchJson('/api/v1/me', ['avatar' => 'spaceship'])->assertStatus(422)->assertJsonValidationErrors('avatar');
});

it('accepts every Clan avatar key and nothing else', function () {
    $species = ['fox', 'owl', 'panda', 'lion', 'koala', 'penguin', 'tiger', 'rabbit', 'bear', 'frog', 'cat',
        'dog', 'unicorn', 'dragon', 'turtle', 'octopus', 'elephant', 'giraffe', 'hedgehog', 'otter', 'raccoon', 'monkey'];
    $expected = [];

    foreach ($species as $name) {
        foreach (['', '-teen', '-mom', '-dad', '-grandma', '-grandpa'] as $variant) {
            $expected[] = $name.$variant;
        }
    }

    expect(User::AVATARS)->toBe($expected)->toHaveCount(132);
    expect(max(array_map('strlen', User::AVATARS)))->toBeLessThanOrEqual(30);

    Sanctum::actingAs(User::factory()->create());

    $this->patchJson('/api/v1/me', ['avatar' => 'tiger-mom'])->assertOk()->assertJsonPath('data.avatar', 'tiger-mom');
    $this->patchJson('/api/v1/me', ['avatar' => 'hedgehog-grandpa'])->assertOk()->assertJsonPath('data.avatar', 'hedgehog-grandpa');
    $this->patchJson('/api/v1/me', ['avatar' => 'tiger-aunt'])->assertStatus(422)->assertJsonValidationErrors('avatar');
});

it('updates accessibility settings and resets the theme with null', function () {
    $owner = familyOwner();
    actingAsMember($owner);

    $this->patchJson('/api/v1/me/accessibility', ['text_scale' => 1.4, 'calm_timers' => true, 'theme' => 'classic'])
        ->assertOk()
        ->assertJsonPath('data.accessibility.text_scale', 1.4)
        ->assertJsonPath('data.accessibility.calm_timers', true)
        ->assertJsonPath('data.accessibility.reduced_motion', false)
        ->assertJsonPath('data.accessibility.theme', 'classic');

    $this->patchJson('/api/v1/me/accessibility', ['theme' => null])
        ->assertOk()
        ->assertJsonPath('data.accessibility.theme', null)
        ->assertJsonPath('data.accessibility.text_scale', 1.4);

    $this->patchJson('/api/v1/me/accessibility', ['text_scale' => 3, 'theme' => 'volcano'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['text_scale', 'theme']);
});

it('needs a family for accessibility settings', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->patchJson('/api/v1/me/accessibility', ['calm_timers' => true])
        ->assertStatus(409)
        ->assertJsonPath('code', 'no_family');
});

it('applies senior accessibility defaults and the classic theme', function () {
    $owner = familyOwner([], 70);
    actingAsMember($owner);

    $this->getJson('/api/v1/me')
        ->assertJsonPath('data.policy.age_band', 'senior')
        ->assertJsonPath('data.policy.ui_theme', 'classic')
        ->assertJsonPath('data.policy.accessibility_defaults.text_scale', 1)
        ->assertJsonPath('data.member.default_ui_theme', 'classic')
        ->assertJsonPath('data.policy.accessibility_defaults.calm_timers', true)
        ->assertJsonPath('data.member.accessibility.calm_timers', true);
});
