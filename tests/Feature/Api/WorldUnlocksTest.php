<?php

use App\Filament\Resources\ThemeWorlds\Pages\ManageThemeWorlds;
use App\Modules\Billing\Actions\ApplyFamilyTier;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\Actions\EvaluateWorldUnlocks;
use App\Modules\Policy\Models\AgeBand;
use App\Modules\Policy\Models\ThemeWorld;
use App\Modules\Policy\Support\ThemeWorldCatalog;
use Database\Seeders\ThemeWorldSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

/**
 * Theme world unlocks (docs/API.md "Worlds and unlocks (2026-10-23, phase 2)"): catalog, unlock
 * evaluation, endpoints, `world_locked`, `theme_unlocked`, admin catalog editing and query counts.
 */

/**
 * The world rows of a catalog response, keyed by world key.
 *
 * @param  list<array<string, mixed>>  $rows
 * @return array<string, array<string, mixed>>
 */
function worldsByKey(array $rows): array
{
    return collect($rows)->keyBy('key')->all();
}

/**
 * The catalog as a new request would compute it (scoped singletons forgotten).
 *
 * @return array<string, array{key: string, unlock_type: string, required: int|null}>
 */
function freshCatalog(): array
{
    app()->forgetScopedInstances();

    return app(ThemeWorldCatalog::class)->all();
}

/**
 * Number of queries `$run` executes.
 */
function worldQueries(Closure $run): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $run();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

/**
 * Switch the family's tier through Billing's single entry point, from a subscription in `$state`.
 */
function applySubscription(Family $family, string $state): void
{
    $factory = Subscription::factory()->for($family);
    $subscription = match ($state) {
        'active' => $factory->make(),
        'trial' => $factory->make(['period_type' => 'trial']),
        'grace' => $factory->grace()->make(),
        'expired' => $factory->expired()->make(),
    };

    $existing = Subscription::query()->where('family_id', $family->id)->first();

    if ($existing !== null) {
        $existing->fill($subscription->only(['status', 'period_type', 'expires_at', 'grace_expires_at', 'will_renew']));
        $subscription = $existing;
    }

    app(ApplyFamilyTier::class)->handle($family, $subscription, ApplyFamilyTier::SOURCE_SYNC);
}

// ---------------------------------------------------------------------------------------
// Catalog
// ---------------------------------------------------------------------------------------

it('seeds the contract catalog in display order', function () {
    $rows = ThemeWorld::query()->orderBy('sort')->get()
        ->map(fn (ThemeWorld $world) => [$world->key, $world->unlock_type, $world->required, $world->active])->all();

    expect($rows)->toBe([
        ['playful', 'free', null, true],
        ['sleek', 'free', null, true],
        ['modern', 'free', null, true],
        ['classic', 'free', null, true],
        ['jungle', 'points', 50, true],
        ['sticker', 'points', 100, true],
        ['space', 'points', 150, true],
        ['nature', 'points', 200, true],
        ['sea', 'streak', 3, true],
        ['lofi', 'streak', 7, true],
        ['dino', 'family_plan', null, true],
        ['neon', 'family_plan', null, true],
        ['midnight', 'family_plan', null, true],
    ])->and(array_keys(ThemeWorld::DEFAULT_CATALOG))->toEqualCanonicalizing(AgeBand::THEMES);
});

it('re-runs the seeder and the migration without overwriting admin changes', function () {
    ThemeWorld::query()->where('key', 'jungle')->firstOrFail()->update(['required' => 10]);
    ThemeWorld::query()->where('key', 'midnight')->delete();

    $this->seed(ThemeWorldSeeder::class);
    $this->seed(ThemeWorldSeeder::class);
    (require database_path('migrations/2026_10_23_200000_create_theme_worlds_table.php'))->up();

    expect(ThemeWorld::query()->count())->toBe(13)
        ->and(ThemeWorld::query()->where('key', 'jungle')->value('required'))->toBe(10)
        ->and(ThemeWorld::query()->where('key', 'midnight')->value('unlock_type'))->toBe('family_plan');
});

it('serves the catalog from the cache and invalidates it on every write', function () {
    freshCatalog();
    expect(worldQueries(fn () => freshCatalog()))->toBe(0);

    ThemeWorld::query()->where('key', 'jungle')->firstOrFail()->update(['required' => 75]);
    expect(freshCatalog()['jungle']['required'])->toBe(75);

    ThemeWorld::query()->whereIn('key', ['sea', 'lofi'])->update(['active' => false]);
    expect(freshCatalog())->not->toHaveKeys(['sea', 'lofi']);

    ThemeWorld::query()->where('key', 'sea')->update(['active' => true, 'sort' => 1]);
    expect(array_key_first(freshCatalog()))->toBe('sea');
});

it('clears the threshold of free and family plan worlds on save', function () {
    $world = ThemeWorld::query()->where('key', 'dino')->firstOrFail();
    $world->update(['required' => 99]);

    expect($world->refresh()->required)->toBeNull();
});

// ---------------------------------------------------------------------------------------
// GET /me/worlds and unlock rules
// ---------------------------------------------------------------------------------------

it('returns the catalog for a new free member', function () {
    actingAsMember(familyOwner());

    $data = $this->getJson('/api/v1/me/worlds')->assertOk()->json('data');

    expect(array_column($data, 'key'))->toBe(array_keys(ThemeWorld::DEFAULT_CATALOG))
        ->and($data[0])->toBe(['key' => 'playful', 'unlock' => ['type' => 'free'], 'unlocked' => true])
        ->and(worldsByKey($data)['jungle'])->toBe(['key' => 'jungle', 'unlock' => ['type' => 'points', 'required' => 50, 'progress' => 0], 'unlocked' => false])
        ->and(worldsByKey($data)['sea'])->toBe(['key' => 'sea', 'unlock' => ['type' => 'streak', 'required' => 3, 'progress' => 0], 'unlocked' => false])
        ->and(worldsByKey($data)['dino'])->toBe(['key' => 'dino', 'unlock' => ['type' => 'family_plan'], 'unlocked' => false]);
});

it('unlocks points worlds at the threshold of lifetime points', function () {
    $owner = familyOwner();
    actingAsMember($owner);
    earnPoints($owner, 49);

    $worlds = fn () => worldsByKey($this->getJson('/api/v1/me/worlds')->assertOk()->json('data'));

    expect($worlds()['jungle']['unlocked'])->toBeFalse()
        ->and($worlds()['jungle']['unlock']['progress'])->toBe(49);

    earnPoints($owner, 1);

    expect($worlds()['jungle'])->toMatchArray(['unlocked' => true, 'unlock' => ['type' => 'points', 'required' => 50, 'progress' => 50]])
        ->and($worlds()['sticker']['unlocked'])->toBeFalse();

    earnPoints($owner, 150);

    expect(collect($worlds())->only(['jungle', 'sticker', 'space', 'nature'])->pluck('unlocked')->all())->toBe([true, true, true, true]);
});

it('keeps a points world unlocked after a compensating correction (lifetime = earned points)', function () {
    $owner = familyOwner();
    actingAsMember($owner);
    earnPoints($owner, 60);
    earnPoints($owner, -30);

    $jungle = worldsByKey($this->getJson('/api/v1/me/worlds')->assertOk()->json('data'))['jungle'];

    expect($jungle['unlocked'])->toBeTrue()
        ->and($jungle['unlock']['progress'])->toBe(60)
        ->and($this->getJson('/api/v1/me/stats')->json('data.points_total'))->toBe(30)
        ->and(app(EvaluateWorldUnlocks::class)->progress($owner)->lifetimePoints)->toBe(60);
});

it('unlocks streak worlds from the longest streak, not the current one', function () {
    $owner = familyOwner();
    actingAsMember($owner);
    $worlds = fn () => worldsByKey($this->getJson('/api/v1/me/worlds')->assertOk()->json('data'));

    setStreak($owner, longest: 2, current: 2);
    expect($worlds()['sea'])->toMatchArray(['unlocked' => false, 'unlock' => ['type' => 'streak', 'required' => 3, 'progress' => 2]]);

    setStreak($owner, longest: 3, current: 0);
    expect($worlds()['sea']['unlocked'])->toBeTrue()
        ->and($worlds()['lofi'])->toMatchArray(['unlocked' => false, 'unlock' => ['type' => 'streak', 'required' => 7, 'progress' => 3]])
        ->and($this->getJson('/api/v1/me/stats')->json('data.streak.longest'))->toBe(3);

    setStreak($owner, longest: 7);
    expect($worlds()['lofi']['unlocked'])->toBeTrue();
});

it('unlocks family plan worlds while the plan is active, in a trial or in grace', function (string $state, bool $unlocked) {
    $owner = familyOwner();
    applySubscription($owner->family, $state);
    actingAsMember($owner);

    $worlds = worldsByKey($this->getJson('/api/v1/me/worlds')->assertOk()->json('data'));

    expect(collect($worlds)->only(['dino', 'neon', 'midnight'])->pluck('unlocked')->unique()->all())->toBe([$unlocked]);
})->with([
    'active' => ['active', true],
    'trial' => ['trial', true],
    'grace' => ['grace', true],
    'expired' => ['expired', false],
]);

it('locks family plan worlds again when the plan ends but keeps the stored theme', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    applySubscription($owner->family, 'active');
    actingAsMember($owner);

    $this->patchJson('/api/v1/me/accessibility', ['theme' => 'neon'])->assertOk()->assertJsonPath('data.theme_unlocked', true);
    $this->patchJson("/api/v1/family/members/{$kid->id}", ['accessibility' => ['theme' => 'dino']])->assertOk()
        ->assertJsonPath('data.theme_unlocked', true);

    applySubscription($owner->family->refresh(), 'expired');

    $this->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.member.accessibility.theme', 'neon')
        ->assertJsonPath('data.member.theme_unlocked', false)
        ->assertJsonPath('data.member.default_ui_theme', 'modern');

    $members = collect($this->getJson('/api/v1/family')->assertOk()->json('data.members'))->keyBy('id');
    expect($members[$kid->id]['accessibility']['theme'])->toBe('dino')
        ->and($members[$kid->id]['theme_unlocked'])->toBeFalse()
        ->and($members[$owner->id]['theme_unlocked'])->toBeFalse();

    $this->patchJson('/api/v1/me/accessibility', ['theme' => 'neon'])->assertStatus(403)->assertJsonPath('code', 'world_locked');
    $this->patchJson('/api/v1/me/accessibility', ['text_scale' => 1.2])->assertOk()->assertJsonPath('data.theme_unlocked', false);

    applySubscription($owner->family->refresh(), 'active');

    $this->getJson('/api/v1/me')->assertJsonPath('data.member.theme_unlocked', true);
});

it('reports theme_unlocked true for a null, free or reached theme and false for a points world not reached', function () {
    $owner = familyOwner();
    actingAsMember($owner);

    $this->getJson('/api/v1/me')->assertJsonPath('data.member.theme_unlocked', true);

    $owner->forceFill(['accessibility' => ['theme' => 'classic']])->save();
    $this->getJson('/api/v1/me')->assertJsonPath('data.member.theme_unlocked', true);

    $owner->forceFill(['accessibility' => ['theme' => 'space']])->save();
    $this->getJson('/api/v1/me')->assertJsonPath('data.member.theme_unlocked', false);

    earnPoints($owner, 150);
    $this->getJson('/api/v1/me')->assertJsonPath('data.member.theme_unlocked', true);
});

// ---------------------------------------------------------------------------------------
// Picking a theme: 403 world_locked
// ---------------------------------------------------------------------------------------

it('rejects a locked theme with 403 world_locked and always allows null', function (string $theme) {
    $owner = familyOwner();
    $owner->forceFill(['accessibility' => ['theme' => 'classic']])->save();
    actingAsMember($owner);

    $this->patchJson('/api/v1/me/accessibility', ['theme' => $theme, 'text_scale' => 1.4])
        ->assertStatus(403)
        ->assertJsonPath('code', 'world_locked');
    expect($owner->refresh()->accessibility)->toBe(['theme' => 'classic']);

    $this->patchJson('/api/v1/me/accessibility', ['theme' => null])->assertOk()
        ->assertJsonPath('data.accessibility.theme', null)
        ->assertJsonPath('data.theme_unlocked', true);
})->with(['jungle', 'sea', 'dino']);

it('still answers 422 for an unknown theme key', function () {
    actingAsMember(familyOwner());

    $this->patchJson('/api/v1/me/accessibility', ['theme' => 'volcano'])->assertStatus(422)->assertJsonPath('code', 'validation_failed');
});

it('treats an inactive world as locked and leaves it out of the catalog', function () {
    $owner = familyOwner();
    actingAsMember($owner);
    ThemeWorld::query()->where('key', 'sleek')->update(['active' => false]);

    expect(array_column($this->getJson('/api/v1/me/worlds')->assertOk()->json('data'), 'key'))->not->toContain('sleek');
    $this->patchJson('/api/v1/me/accessibility', ['theme' => 'sleek'])->assertStatus(403)->assertJsonPath('code', 'world_locked');
});

it('checks the admin member PATCH against the target member unlocks', function () {
    $owner = unlockAllWorlds(familyOwner());
    $kid = addMember($owner->family, 9);
    actingAsMember($owner);

    $this->patchJson("/api/v1/family/members/{$kid->id}", ['accessibility' => ['theme' => 'jungle']])
        ->assertStatus(403)
        ->assertJsonPath('code', 'world_locked');

    $this->patchJson("/api/v1/family/members/{$kid->id}", ['accessibility' => ['theme' => 'neon']])->assertOk()
        ->assertJsonPath('data.accessibility.theme', 'neon');

    earnPoints($kid, 50);
    $this->patchJson("/api/v1/family/members/{$kid->id}", ['accessibility' => ['theme' => 'jungle']])->assertOk()
        ->assertJsonPath('data.accessibility.theme', 'jungle')
        ->assertJsonPath('data.theme_unlocked', true);

    $this->patchJson("/api/v1/family/members/{$kid->id}", ['accessibility' => ['theme' => null]])->assertOk();
});

// ---------------------------------------------------------------------------------------
// GET /family/members/{member}/worlds (admin)
// ---------------------------------------------------------------------------------------

it('returns a member catalog to a family admin', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    earnPoints($kid, 120);
    setStreak($kid, 4);
    actingAsMember($owner);

    $worlds = worldsByKey($this->getJson("/api/v1/family/members/{$kid->id}/worlds")->assertOk()->json('data'));

    expect($worlds['sticker'])->toMatchArray(['unlocked' => true, 'unlock' => ['type' => 'points', 'required' => 100, 'progress' => 120]])
        ->and($worlds['space']['unlocked'])->toBeFalse()
        ->and($worlds['sea']['unlocked'])->toBeTrue()
        ->and($worlds['lofi']['unlock']['progress'])->toBe(4);
});

it('keeps the member worlds endpoint admin-only and family-scoped', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    $other = familyOwner();

    actingAsMember($kid);
    $this->getJson("/api/v1/family/members/{$owner->id}/worlds")->assertStatus(403)->assertJsonPath('code', 'forbidden');

    app('auth')->forgetGuards();
    actingAsMember($other);
    $this->getJson("/api/v1/family/members/{$kid->id}/worlds")->assertStatus(404)->assertJsonPath('code', 'not_found');

    $this->getJson('/api/v1/family/members/999999/worlds')->assertStatus(404);
});

it('needs a family for /me/worlds', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/me/worlds')->assertStatus(409)->assertJsonPath('code', 'no_family');
});

// ---------------------------------------------------------------------------------------
// Admin (Filament)
// ---------------------------------------------------------------------------------------

it('edits a world in Filament and invalidates the cached catalog', function () {
    $this->actingAs(User::factory()->staff()->create());
    $jungle = ThemeWorld::query()->where('key', 'jungle')->firstOrFail();
    $owner = familyOwner();
    earnPoints($owner, 20);

    expect(freshCatalog()['jungle']['required'])->toBe(50)
        ->and(app(EvaluateWorldUnlocks::class)->isUnlocked($owner, 'jungle'))->toBeFalse();

    Livewire::test(ManageThemeWorlds::class)
        ->callTableAction(EditAction::class, $jungle, data: ['required' => 20])
        ->assertHasNoTableActionErrors();

    expect($jungle->refresh()->required)->toBe(20)
        ->and(freshCatalog()['jungle']['required'])->toBe(20)
        ->and(app(EvaluateWorldUnlocks::class)->isUnlocked($owner, 'jungle'))->toBeTrue();

    Livewire::test(ManageThemeWorlds::class)->call('updateTableColumnState', 'active', (string) $jungle->id, false);
    expect(freshCatalog())->not->toHaveKey('jungle');

    $sea = ThemeWorld::query()->where('key', 'sea')->firstOrFail();
    Livewire::test(ManageThemeWorlds::class)->callTableAction(EditAction::class, $sea, data: ['unlock_type' => 'family_plan']);
    expect(freshCatalog()['sea'])->toBe(['key' => 'sea', 'unlock_type' => 'family_plan', 'required' => null]);
});

it('validates the Filament world form', function () {
    $this->actingAs(User::factory()->staff()->create());
    $jungle = ThemeWorld::query()->where('key', 'jungle')->firstOrFail();

    Livewire::test(ManageThemeWorlds::class)
        ->callTableAction(EditAction::class, $jungle, data: ['required' => null])
        ->assertHasTableActionErrors(['required' => 'required']);

    Livewire::test(ManageThemeWorlds::class)
        ->callTableAction(EditAction::class, $jungle, data: ['required' => 0])
        ->assertHasTableActionErrors(['required']);

    Livewire::test(ManageThemeWorlds::class)
        ->callAction(CreateAction::class, data: ['key' => 'sea', 'sort' => 5, 'unlock_type' => 'free', 'active' => true])
        ->assertHasActionErrors(['key' => 'unique']);

    Livewire::test(ManageThemeWorlds::class)
        ->callAction(CreateAction::class, data: ['key' => 'volcano', 'sort' => 5, 'unlock_type' => 'free'])
        ->assertHasActionErrors(['key']);

    Livewire::test(ManageThemeWorlds::class)
        ->callAction(CreateAction::class, data: ['key' => 'playful', 'sort' => 5, 'unlock_type' => 'gems'])
        ->assertHasActionErrors(['unlock_type']);

    expect($jungle->refresh()->required)->toBe(50);
});

it('lists the catalog in Filament with a constant number of queries', function () {
    $this->actingAs(User::factory()->staff()->create());

    $few = worldQueries(fn () => Livewire::test(ManageThemeWorlds::class)->assertOk());
    ThemeWorld::query()->whereIn('key', ['playful', 'sleek'])->delete();
    $fewer = worldQueries(fn () => Livewire::test(ManageThemeWorlds::class)->assertOk());

    expect($fewer)->toBe($few);
});

// ---------------------------------------------------------------------------------------
// Query counts (no N+1)
// ---------------------------------------------------------------------------------------

it('evaluates a member in two queries whatever the catalog size', function () {
    $owner = familyOwner();
    $owner->load('family');
    earnPoints($owner, 70);
    freshCatalog();
    $full = worldQueries(fn () => app(EvaluateWorldUnlocks::class)->handle($owner));

    ThemeWorld::query()->whereNotIn('key', ['playful', 'jungle'])->update(['active' => false]);
    freshCatalog();
    $small = worldQueries(fn () => app(EvaluateWorldUnlocks::class)->handle($owner));

    expect($full)->toBe(2)->and($small)->toBe(2)
        ->and(count(app(EvaluateWorldUnlocks::class)->handle($owner)))->toBe(2);
});

it('lists the family with theme_unlocked in a constant number of queries', function () {
    $family = function (int $members): FamilyMember {
        $owner = familyOwner();
        $themes = ['jungle', 'sea', 'neon', 'classic', null];

        for ($i = 0; $i < $members; $i++) {
            $member = addMember($owner->family, $i % 2 === 0 ? 9 : 30);
            $member->forceFill(['accessibility' => ['theme' => $themes[$i % count($themes)]]])->save();
            earnPoints($member, 30 * $i);
            setStreak($member, $i);
        }

        return $owner;
    };

    $queries = function (FamilyMember $owner): int {
        app('auth')->forgetGuards();
        actingAsMember($owner);
        test()->getJson('/api/v1/family')->assertOk();

        return worldQueries(fn () => test()->getJson('/api/v1/family')->assertOk());
    };

    $small = $family(2);
    $large = $family(7);

    expect($queries($small))->toBe($queries($large));

    // Members i = 0..6 get themes jungle, sea, neon, classic, null, jungle, sea with 30*i points and an i-day streak.
    $unlocked = collect(test()->getJson('/api/v1/family')->json('data.members'))->skip(1)->pluck('theme_unlocked')->values()->all();
    expect($unlocked)->toBe([false, false, false, true, true, true, true]);
});

it('documents the worlds endpoints and world_locked in the API spec', function () {
    Gate::define('viewApiDocs', fn ($user = null): bool => true);

    $spec = $this->getJson('/docs/api.json')->assertOk()->json();

    expect($spec['paths'])->toHaveKeys(['/me/worlds', '/family/members/{member}/worlds'])
        ->and(json_encode($spec['paths']['/me/accessibility']['patch']))->toContain('world_locked')
        ->and(json_encode($spec['paths']['/family/members/{member}']['patch']))->toContain('world_locked')
        ->and(json_encode($spec))->toContain('theme_unlocked');
});
