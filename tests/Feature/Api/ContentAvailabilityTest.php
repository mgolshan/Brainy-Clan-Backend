<?php

use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Modules\Content\Actions\ImportContentRow;
use App\Modules\Content\Actions\ReviewContentBatchItems;
use App\Modules\Content\Actions\SaveContentItem;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentBatch;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Content\Support\ContentAvailability;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\ContentSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

/**
 * `challenge_types` per category in GET /categories comes from the cached ContentAvailability
 * (docs/PLAN.md "Caching"): same answer as the SQL it replaced, and every content write path
 * invalidates it.
 */

/**
 * Challenge types of one category as a new request sees them.
 *
 * @return list<string>
 */
function availableTypes(FamilyMember $member, int $categoryId): array
{
    app()->forgetScopedInstances();
    app('auth')->forgetGuards();
    actingAsMember($member);
    $find = function (array $nodes) use (&$find, $categoryId): ?array {
        foreach ($nodes as $node) {
            if ($node['id'] === $categoryId) {
                return $node;
            }

            if (($child = $find($node['children'])) !== null) {
                return $child;
            }
        }

        return null;
    };

    return $find(test()->getJson('/api/v1/categories')->assertOk()->json('data'))['challenge_types'] ?? [];
}

it('matches the SQL availability query for every age, locale and type filter', function () {
    $categories = Category::factory()->count(3)->create();
    $variants = [
        ['locale' => 'en', 'cross_locale' => true], ['locale' => 'en', 'cross_locale' => false], ['locale' => 'fr', 'cross_locale' => true],
    ];
    $ages = [[null, null], [null, 8], [6, 12], [13, null]];
    $i = 0;

    foreach ($categories as $category) {
        foreach (['multiple_choice', 'wisdom_card'] as $type) {
            foreach ($variants as $variant) {
                [$min, $max] = $ages[$i++ % count($ages)];
                ContentItem::factory()->create([
                    'category_id' => $category->id, 'type_key' => $type, ...$variant, 'min_age' => $min, 'max_age' => $max,
                    'status' => $i % 5 === 0 ? ContentItem::STATUS_DRAFT : ContentItem::STATUS_PUBLISHED,
                ]);
            }
        }
    }

    foreach ([null, 5, 9, 14, 40] as $age) {
        foreach (['en', 'fr'] as $locale) {
            foreach ([['multiple_choice', 'wisdom_card'], ['multiple_choice']] as $types) {
                $expected = [];
                ContentItem::whereAvailableInLocale(ContentItem::query(), $locale, dedupe: false)
                    ->published()->forAge($age)->whereIn('type_key', $types)
                    ->select(['category_id', 'type_key'])->distinct()->get()
                    ->each(function (ContentItem $row) use (&$expected): void {
                        $expected[$row->category_id][] = $row->type_key;
                    });

                $actual = app(ContentAvailability::class)->typesByCategory($age, $locale, $types);
                $normalize = fn (array $map): array => collect($map)->map(fn ($t) => collect($t)->sort()->values()->all())->sortKeys()->all();

                expect($normalize($actual))->toBe($normalize($expected), "age {$age}, {$locale}, ".implode('+', $types));
            }
        }
    }
});

it('serves content availability from the cache on the next request', function () {
    ContentItem::factory()->count(3)->create();
    $count = function (): int {
        app()->forgetScopedInstances();
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(ContentAvailability::class)->typesByCategory(9, 'en', ['multiple_choice']);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    expect($count())->toBe(1)->and($count())->toBe(0);
});

it('shows a type after publishing through SaveContentItem and hides it after a Filament bulk archive', function () {
    $owner = familyOwner(['tier' => 'family']);
    $category = Category::factory()->create();
    $mc = ContentItem::factory()->create(['category_id' => $category->id]);
    expect(availableTypes($owner, $category->id))->toBe(['multiple_choice']);

    $wisdom = app(SaveContentItem::class)->handle([
        ...ContentItem::factory()->wisdomCard()->make(['category_id' => $category->id])->only(['category_id', 'type_key', 'locale', 'difficulty', 'payload', 'answer', 'source']),
        'status' => ContentItem::STATUS_PUBLISHED,
    ]);
    expect(availableTypes($owner, $category->id))->toBe(['multiple_choice', 'wisdom_card']);

    $this->actingAs(User::factory()->staff()->create());
    Livewire::test(ListContentItems::class)->callTableBulkAction('archive', [$mc, $wisdom]);

    expect($mc->refresh()->status)->toBe(ContentItem::STATUS_ARCHIVED)
        ->and(availableTypes($owner, $category->id))->toBe([]);
});

it('invalidates availability on CSV import, AI batch approval, mass updates and deletes', function () {
    $owner = familyOwner(['tier' => 'family']);
    $category = Category::factory()->create(['slug' => 'capitals-americas']);
    expect(availableTypes($owner, $category->id))->toBe([]);

    $imported = app(ImportContentRow::class)->handle([
        'category_slug' => 'capitals-americas', 'type_key' => 'multiple_choice', 'status' => 'published', 'source_ref' => 'csv-1',
        'question' => 'Capital of Peru?', 'choice_a' => 'Lima', 'choice_b' => 'Quito', 'correct' => 'a',
    ]);
    expect(availableTypes($owner, $category->id))->toBe(['multiple_choice']);

    $batch = ContentBatch::factory()->inReview(1)->create(['category_id' => $category->id]);
    $draft = ContentItem::factory()->draft()->wisdomCard()->create(['content_batch_id' => $batch->id, 'category_id' => $category->id, 'source' => 'ai']);
    expect(availableTypes($owner, $category->id))->toBe(['multiple_choice']);

    app(ReviewContentBatchItems::class)->handle($batch, [$draft], ReviewContentBatchItems::APPROVE, User::factory()->staff()->create());
    expect(availableTypes($owner, $category->id))->toBe(['multiple_choice', 'wisdom_card']);

    ContentItem::query()->whereKey($draft->id)->update(['min_age' => 50]);
    expect(availableTypes($owner, $category->id))->toBe(['multiple_choice']);

    $imported->delete();
    expect(availableTypes($owner, $category->id))->toBe([]);
});

it('invalidates availability when the ContentSeeder runs under withoutEvents', function () {
    $this->seed([CategorySeeder::class, ChallengeTypeSeeder::class]);
    $owner = familyOwner(['tier' => 'family']);
    $flags = Category::query()->where('slug', 'flags')->firstOrFail();
    expect(availableTypes($owner, $flags->id))->toBe([]);

    $dir = sys_get_temp_dir().'/availability-bank-'.uniqid();
    File::ensureDirectoryExists($dir);
    file_put_contents($dir.'/geo.php', '<?php return '.var_export([[
        'id' => 'flags-901', 'category' => 'flags', 'difficulty' => 2,
        'en' => ['question' => 'Which flag has a maple leaf?', 'answer' => 'Canada', 'wrong' => ['Japan', 'Peru', 'Chile'], 'explanation' => null],
    ]], true).';');

    $seeder = app(ContentSeeder::class);
    $seeder->questionsPath = $dir;
    $seeder->runQuestionBank();
    File::deleteDirectory($dir);

    expect(availableTypes($owner, $flags->id))->toBe(['multiple_choice']);
});
