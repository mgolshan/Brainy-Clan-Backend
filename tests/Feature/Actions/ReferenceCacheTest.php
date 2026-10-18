<?php

use App\Filament\Resources\AgeBands\Pages\EditAgeBand;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\ChallengeTypes\Pages\ManageChallengeTypes;
use App\Filament\Resources\Settings\Pages\ManageSettings;
use App\Modules\ChallengeTypes\ChallengeTypeRegistry;
use App\Modules\ChallengeTypes\Handlers\WisdomCard;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ChallengeType;
use App\Modules\Content\Support\CategoryTree;
use App\Modules\Core\Support\ReferenceCache;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\Models\AgeBand;
use App\Modules\Policy\Models\Setting;
use App\Modules\Policy\PolicyResolver;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\PolicySeeder;
use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

/**
 * Reference caches (docs/PLAN.md "Caching"): served from the cache on the next request, and
 * invalidated by every write path — Filament pages, mass updates, seeders, transactions.
 */

/**
 * Run `$read` as a new request would (scoped singletons forgotten) and count its queries.
 */
function refQueries(Closure $read): int
{
    app()->forgetScopedInstances();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $read();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

/**
 * The value a new request sees.
 */
function freshRead(Closure $read): mixed
{
    app()->forgetScopedInstances();

    return $read();
}

function minAppVersion(): string
{
    return app(PolicyResolver::class)->minAppVersion();
}

function staffMember(): User
{
    $staff = User::factory()->staff()->create();
    test()->actingAs($staff);

    return $staff;
}

// ---------------------------------------------------------------------------------------
// ReferenceCache itself
// ---------------------------------------------------------------------------------------

it('computes once and serves the stored value until flushed', function () {
    $calls = 0;
    $compute = function () use (&$calls): array {
        $calls++;

        return ['n' => $calls];
    };

    expect(ReferenceCache::remember('test.v1', $compute))->toBe(['n' => 1])
        ->and(ReferenceCache::remember('test.v1', $compute))->toBe(['n' => 1]);

    ReferenceCache::flush('test.v1');

    expect(ReferenceCache::remember('test.v1', $compute))->toBe(['n' => 2])
        ->and($calls)->toBe(2);
});

it('invalidates only after the transaction commits and not at all on rollback', function () {
    $before = ReferenceCache::key(ReferenceCache::POLICY);

    DB::transaction(function () use ($before): void {
        Setting::query()->where('key', 'min_app_version')->firstOrFail()->update(['value' => '4.0.0']);

        expect(ReferenceCache::key(ReferenceCache::POLICY))->toBe($before);
    });

    $afterCommit = ReferenceCache::key(ReferenceCache::POLICY);
    expect($afterCommit)->not->toBe($before);

    try {
        DB::transaction(function (): void {
            Setting::query()->where('key', 'min_app_version')->firstOrFail()->update(['value' => '5.0.0']);

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect(ReferenceCache::key(ReferenceCache::POLICY))->toBe($afterCommit)
        ->and(freshRead(fn () => minAppVersion()))->toBe('4.0.0');
});

it('never stores a value computed inside an open transaction', function () {
    DB::transaction(function (): void {
        ReferenceCache::remember('test.v1', fn (): array => ['inside']);
    });

    expect(ReferenceCache::remember('test.v1', fn (): array => ['outside']))->toBe(['outside']);
});

it('ignores a stale value written under the old key by a slow reader', function () {
    expect(freshRead(fn () => minAppVersion()))->toBe('1.0.0');
    $oldKey = ReferenceCache::key(ReferenceCache::POLICY);
    $stale = Cache::get($oldKey);

    Setting::query()->where('key', 'min_app_version')->firstOrFail()->update(['value' => '2.5.0']);
    Cache::put($oldKey, $stale);   // a reader that started before the write stores late

    expect(freshRead(fn () => minAppVersion()))->toBe('2.5.0');
});

it('starts over with a new version when the version key is lost', function () {
    freshRead(fn () => minAppVersion());
    Cache::forget('ref:'.ReferenceCache::POLICY.':version');

    expect(refQueries(fn () => minAppVersion()))->toBeGreaterThan(0)
        ->and(refQueries(fn () => minAppVersion()))->toBe(0);
});

it('flushes every reference cache from the console and after migrations', function () {
    $keys = fn (): array => array_map(fn (string $name) => ReferenceCache::key($name), ReferenceCache::ALL);
    $before = $keys();

    $this->artisan('reference-cache:flush')->assertSuccessful();
    $afterCommand = $keys();
    Event::dispatch(new MigrationsEnded('up'));

    expect(array_intersect($afterCommand, $before))->toBe([])
        ->and(array_intersect($keys(), $afterCommand))->toBe([]);
});

// ---------------------------------------------------------------------------------------
// Policy snapshot (age bands + settings)
// ---------------------------------------------------------------------------------------

it('serves age bands and settings from the cache on the next request', function () {
    expect(refQueries(fn () => minAppVersion()))->toBe(2)
        ->and(refQueries(fn () => app(PolicyResolver::class)->bandForAge(10)?->code))->toBe(0);
});

it('invalidates the policy on a Setting save, mass update, quiet save and delete', function () {
    $setting = Setting::query()->where('key', 'min_app_version')->firstOrFail();
    expect(freshRead(fn () => minAppVersion()))->toBe('1.0.0');

    $setting->update(['value' => '1.1.0']);
    expect(freshRead(fn () => minAppVersion()))->toBe('1.1.0');

    Setting::query()->whereKey($setting->id)->update(['value' => json_encode('1.2.0')]);
    expect(freshRead(fn () => minAppVersion()))->toBe('1.2.0');

    Model::withoutEvents(fn () => $setting->refresh()->forceFill(['value' => '1.3.0'])->save());
    expect(freshRead(fn () => minAppVersion()))->toBe('1.3.0');

    Setting::query()->insert(['key' => 'min_app_version', 'scope' => Setting::SCOPE_TIER, 'scope_id' => 'family', 'value' => json_encode('9.0.0')]);
    expect(freshRead(fn () => app(PolicyResolver::class)->setting('min_app_version', 'family')))->toBe('9.0.0');

    $setting->delete();
    expect(freshRead(fn () => minAppVersion()))->toBe('1.0.0');   // built-in default
});

it('invalidates the policy when the admin deletes a setting in Filament', function () {
    staffMember();
    $setting = Setting::query()->where('key', 'limits.max_members')->where('scope_id', 'free')->firstOrFail();
    $setting->update(['value' => 5]);
    expect(freshRead(fn () => app(PolicyResolver::class)->setting('limits.max_members', 'free')))->toBe(5);

    Livewire::test(ManageSettings::class)->callTableAction(DeleteAction::class, $setting);

    expect(Setting::query()->whereKey($setting->id)->exists())->toBeFalse()
        ->and(freshRead(fn () => app(PolicyResolver::class)->setting('limits.max_members', 'free')))->toBe(2);
});

it('invalidates the policy when the admin edits an age band in Filament', function () {
    staffMember();
    $teen = AgeBand::query()->where('code', 'teen')->firstOrFail();
    $rating = fn () => app(PolicyResolver::class)->adRequest(app(PolicyResolver::class)->bandForAge(15))['max_ad_content_rating'];
    expect(freshRead($rating))->toBe('G');

    Livewire::test(EditAgeBand::class, ['record' => $teen->getRouteKey()])
        ->fillForm(['policy.ad_request.max_ad_content_rating' => 'PG'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(freshRead($rating))->toBe('PG');
});

it('invalidates the policy when the PolicySeeder re-runs', function () {
    AgeBand::query()->where('code', 'child')->update(['name' => 'Kids']);
    expect(freshRead(fn () => app(PolicyResolver::class)->bandForAge(8)?->name))->toBe('Kids');

    $this->seed(PolicySeeder::class);

    expect(freshRead(fn () => app(PolicyResolver::class)->bandForAge(8)?->name))->not->toBe('Kids');
});

// ---------------------------------------------------------------------------------------
// Category tree
// ---------------------------------------------------------------------------------------

it('serves the category tree from the cache on the next request', function () {
    Category::factory()->count(3)->create();

    expect(refQueries(fn () => app(CategoryTree::class)->all()))->toBe(1)
        ->and(refQueries(fn () => app(CategoryTree::class)->all()))->toBe(0);
});

it('invalidates the category tree when the admin reorders categories (a mass CASE update)', function () {
    staffMember();
    [$a, $b, $c] = Category::factory()->count(3)->sequence(['sort' => 1], ['sort' => 2], ['sort' => 3])->create()->all();
    $order = fn (): array => freshRead(fn () => app(CategoryTree::class)->childrenOf(null)->pluck('id')->all());
    expect($order())->toBe([$a->id, $b->id, $c->id]);

    Livewire::test(ListCategories::class)->call('reorderTable', [(string) $c->id, (string) $a->id, (string) $b->id]);

    expect($order())->toBe([$c->id, $a->id, $b->id]);
});

it('invalidates the category tree on the Filament active toggle and the edit form', function () {
    staffMember();
    $category = Category::factory()->create(['min_age' => null]);
    expect(freshRead(fn () => app(CategoryTree::class)->isVisible($category->id, 8)))->toBeTrue();

    Livewire::test(ListCategories::class)->call('updateTableColumnState', 'is_active', (string) $category->id, false);
    expect(freshRead(fn () => app(CategoryTree::class)->isVisible($category->id, 8)))->toBeFalse();

    $category->refresh()->update(['is_active' => true]);
    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->fillForm(['min_age' => 10])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(freshRead(fn () => app(CategoryTree::class)->isVisible($category->id, 8)))->toBeFalse()
        ->and(freshRead(fn () => app(CategoryTree::class)->isVisible($category->id, 11)))->toBeTrue();
});

it('invalidates the category tree on seeding, mass updates and deletes', function () {
    $category = Category::factory()->create();
    expect(freshRead(fn () => app(CategoryTree::class)->all()))->toHaveCount(1);

    $this->seed(CategorySeeder::class);
    $seeded = Category::query()->count();
    expect($seeded)->toBeGreaterThan(1)
        ->and(freshRead(fn () => app(CategoryTree::class)->all()))->toHaveCount($seeded);

    Category::query()->whereKey($category->id)->update(['is_active' => false]);
    expect(freshRead(fn () => app(CategoryTree::class)->find($category->id)?->is_active))->toBeFalse();

    $category->delete();
    expect(freshRead(fn () => app(CategoryTree::class)->find($category->id)))->toBeNull();
});

// ---------------------------------------------------------------------------------------
// Challenge type records
// ---------------------------------------------------------------------------------------

it('serves challenge type records from the cache on the next request', function () {
    expect(refQueries(fn () => app(ChallengeTypeRegistry::class)->activeKeys()))->toBe(1)
        ->and(refQueries(fn () => app(ChallengeTypeRegistry::class)->activeKeys()))->toBe(0);
});

it('invalidates challenge types on the Filament toggle, seeder and mass update', function () {
    staffMember();
    $this->seed(ChallengeTypeSeeder::class);
    $active = fn (): bool => freshRead(fn () => app(ChallengeTypeRegistry::class)->isActive('wisdom_card'));
    expect($active())->toBeTrue();

    $wisdom = ChallengeType::query()->where('key', 'wisdom_card')->firstOrFail();
    Livewire::test(ManageChallengeTypes::class)->call('updateTableColumnState', 'is_active', (string) $wisdom->id, false);
    expect($active())->toBeFalse();

    ChallengeType::query()->where('key', 'wisdom_card')->update(['is_active' => true]);
    expect($active())->toBeTrue();

    ChallengeType::query()->where('key', 'wisdom_card')->delete();
    ChallengeType::query()->create(['key' => 'wisdom_card', 'name' => 'Wisdom', 'handler_class' => WisdomCard::class, 'is_active' => false]);
    expect($active())->toBeFalse();
});
