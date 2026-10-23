<?php

use App\Filament\Resources\AgeBands\Pages\EditAgeBand;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\Models\AgeBand;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/**
 * Theme worlds and the sound effects setting (docs/PLAN.md "Theme worlds (2026-10-23, Moe)",
 * docs/API.md Member / Policy).
 */
function soundDefaultForAge(int $age): bool
{
    app()->forgetScopedInstances();

    return app(PolicyResolver::class)->forUser(User::factory()->aged($age)->create())['accessibility_defaults']['sound_effects'];
}

it('accepts every theme world key', function (string $theme) {
    actingAsMember(familyOwner());

    $this->patchJson('/api/v1/me/accessibility', ['theme' => $theme])
        ->assertOk()
        ->assertJsonPath('data.accessibility.theme', $theme);
})->with(['playful', 'sleek', 'modern', 'classic', 'jungle', 'space', 'neon']);

it('rejects an unknown theme key with 422', function (mixed $theme) {
    actingAsMember(familyOwner());

    $this->patchJson('/api/v1/me/accessibility', ['theme' => $theme])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('theme');
})->with(['volcano', 'Jungle', 'candy_land', 7]);

it('keeps the band default themes unchanged', function () {
    expect(AgeBand::query()->pluck('policy', 'code')->map(fn (array $policy) => $policy['ui_theme'])->all())
        ->toEqual(['child' => 'playful', 'teen' => 'sleek', 'adult' => 'modern', 'senior' => 'classic']);
});

it('round-trips sound_effects true, false and null', function () {
    $owner = familyOwner();
    actingAsMember($owner);

    $this->getJson('/api/v1/me')->assertJsonPath('data.member.accessibility.sound_effects', null);

    $this->patchJson('/api/v1/me/accessibility', ['sound_effects' => true])
        ->assertOk()
        ->assertJsonPath('data.accessibility.sound_effects', true);
    expect($owner->refresh()->accessibility)->toBe(['sound_effects' => true]);

    $this->patchJson('/api/v1/me/accessibility', ['sound_effects' => false, 'theme' => 'space'])
        ->assertOk()
        ->assertJsonPath('data.accessibility.sound_effects', false)
        ->assertJsonPath('data.accessibility.theme', 'space');
    expect($owner->refresh()->accessibility)->toBe(['sound_effects' => false, 'theme' => 'space']);

    $this->getJson('/api/v1/me')->assertJsonPath('data.member.accessibility.sound_effects', false);

    $this->patchJson('/api/v1/me/accessibility', ['sound_effects' => null])
        ->assertOk()
        ->assertJsonPath('data.accessibility.sound_effects', null)
        ->assertJsonPath('data.accessibility.theme', 'space');

    $this->patchJson('/api/v1/me/accessibility', ['sound_effects' => 'loud'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('sound_effects');
});

it('gives each band its sound effects default in the policy', function (int $age, string $band, bool $sound) {
    $owner = familyOwner([], $age);
    actingAsMember($owner);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.policy.age_band', $band)
        ->assertJsonPath('data.policy.accessibility_defaults.sound_effects', $sound)
        ->assertJsonPath('data.member.accessibility.sound_effects', null);
})->with([
    'child' => [9, 'child', true],
    'teen' => [15, 'teen', false],
    'adult' => [40, 'adult', false],
    'senior' => [70, 'senior', false],
]);

it('lets a family admin set a member theme world and sound effects', function () {
    $owner = familyOwner(['tier' => 'family']);
    $kid = addMember($owner->family, 9);
    actingAsMember($owner);

    $this->patchJson("/api/v1/family/members/{$kid->id}", ['accessibility' => ['theme' => 'jungle', 'sound_effects' => false]])
        ->assertOk()
        ->assertJsonPath('data.accessibility.theme', 'jungle')
        ->assertJsonPath('data.accessibility.sound_effects', false);

    $this->patchJson("/api/v1/family/members/{$kid->id}", ['accessibility' => ['sound_effects' => null]])
        ->assertOk()
        ->assertJsonPath('data.accessibility.sound_effects', null)
        ->assertJsonPath('data.accessibility.theme', 'jungle');

    $this->patchJson("/api/v1/family/members/{$kid->id}", ['accessibility' => ['theme' => 'volcano', 'sound_effects' => 'yes']])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['accessibility.theme', 'accessibility.sound_effects']);
});

it('includes sound_effects in the data export', function () {
    $owner = familyOwner();
    $owner->forceFill(['accessibility' => ['sound_effects' => false, 'theme' => 'neon']])->save();
    actingAsMember($owner);

    $this->getJson('/api/v1/me/export')
        ->assertOk()
        ->assertJsonPath('data.memberships.0.accessibility.sound_effects', false)
        ->assertJsonPath('data.memberships.0.accessibility.theme', 'neon');
});

it('edits the sound default and a world theme in the Filament age band form and invalidates the policy cache', function () {
    $this->actingAs(User::factory()->staff()->create());
    $child = AgeBand::query()->where('code', 'child')->firstOrFail();

    expect(soundDefaultForAge(9))->toBeTrue();

    Livewire::test(EditAgeBand::class, ['record' => $child->getRouteKey()])
        ->assertFormFieldExists('policy.accessibility_defaults.sound_effects')
        ->fillForm(['policy.accessibility_defaults.sound_effects' => false, 'policy.ui_theme' => 'jungle'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($child->refresh()->rule('accessibility_defaults.sound_effects'))->toBeFalse()
        ->and($child->rule('ui_theme'))->toBe('jungle')
        ->and(soundDefaultForAge(9))->toBeFalse();
});

it('rejects an unknown theme in the Filament age band form', function () {
    $this->actingAs(User::factory()->staff()->create());
    $teen = AgeBand::query()->where('code', 'teen')->firstOrFail();

    Livewire::test(EditAgeBand::class, ['record' => $teen->getRouteKey()])
        ->fillForm(['policy.ui_theme' => 'volcano'])
        ->call('save')
        ->assertHasFormErrors(['policy.ui_theme']);
});

it('backfills sound_effects on existing age bands and keeps admin choices', function () {
    $migration = require database_path('migrations/2026_10_23_100000_add_sound_effects_to_age_band_accessibility_defaults.php');

    foreach (AgeBand::query()->get() as $band) {
        $policy = $band->policy;
        unset($policy['accessibility_defaults']['sound_effects']);
        DB::table('age_bands')->where('id', $band->id)->update(['policy' => json_encode($policy)]);
    }
    $custom = AgeBand::factory()->create(['min_age' => 200, 'policy' => [
        'ui_theme' => 'neon', 'is_child' => false, 'accessibility_defaults' => ['text_scale' => 1.0, 'sound_effects' => true],
    ]]);

    $migration->up();
    $migration->up();

    $sound = fn () => AgeBand::query()->get()->mapWithKeys(fn (AgeBand $band) => [$band->code => $band->rule('accessibility_defaults.sound_effects')])->all();

    expect($sound())->toMatchArray(['child' => true, 'teen' => false, 'adult' => false, 'senior' => false, $custom->code => true])
        ->and(AgeBand::query()->where('code', 'senior')->firstOrFail()->rule('accessibility_defaults.calm_timers'))->toBeTrue();
});

it('documents sound_effects and the theme keys in the API spec', function () {
    Gate::define('viewApiDocs', fn ($user = null): bool => true);

    $spec = $this->getJson('/docs/api.json')->assertOk()->json();
    $body = json_encode($spec['paths']['/me/accessibility']['patch']);

    expect($body)->toContain('sound_effects')->toContain('jungle')->toContain('neon')
        ->and(json_encode($spec))->toContain('sound_effects');
});
