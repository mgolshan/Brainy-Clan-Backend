<?php

use App\Modules\Content\Actions\ChangeContentStatus;
use App\Modules\Content\Actions\ImportContentRow;
use App\Modules\Content\Actions\PreviewContentItem;
use App\Modules\Content\Actions\ResolveCategoryAccess;
use App\Modules\Content\Actions\SaveContentItem;
use App\Modules\Content\Actions\TagContentItems;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Content\Support\CategoryTree;
use App\Modules\Policy\Models\AgeBand;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\ContentSeeder;
use Illuminate\Validation\ValidationException;

it('SaveContentItem validates with the plugin and stamps published_at', function () {
    $category = Category::factory()->create();
    $save = app(SaveContentItem::class);

    expect(fn () => $save->handle(['category_id' => $category->id, 'type_key' => 'multiple_choice', 'difficulty' => 3, 'payload' => ['question' => 'Q'], 'answer' => ['choice_id' => 'a']]))
        ->toThrow(ValidationException::class);

    expect(fn () => $save->handle(['category_id' => $category->id, 'type_key' => 'sudoku', 'difficulty' => 3, 'payload' => [], 'answer' => []]))
        ->toThrow(ValidationException::class);

    $item = $save->handle([
        'category_id' => $category->id, 'type_key' => 'multiple_choice', 'difficulty' => 3, 'status' => 'published',
        'payload' => ['question' => 'Q?', 'choices' => [['id' => 'a', 'text' => 'A'], ['id' => 'b', 'text' => 'B']]],
        'answer' => ['choice_id' => 'b'],
    ]);

    expect($item->exists)->toBeTrue()->and($item->published_at)->not->toBeNull();
});

it('ChangeContentStatus publishes valid items and skips invalid ones', function () {
    $valid = ContentItem::factory()->draft()->create();
    $invalid = ContentItem::factory()->draft()->create(['answer' => ['choice_id' => 'z']]);

    $result = app(ChangeContentStatus::class)->handle([$valid, $invalid], ContentItem::STATUS_PUBLISHED);

    expect($result)->toBe(['changed' => 1, 'skipped' => [$invalid->id]])
        ->and($valid->refresh()->status)->toBe('published')
        ->and($invalid->refresh()->status)->toBe('draft');

    app(ChangeContentStatus::class)->handle([$valid], ContentItem::STATUS_ARCHIVED);
    expect($valid->refresh()->status)->toBe('archived');
});

it('TagContentItems adds or replaces tags', function () {
    $item = ContentItem::factory()->create();

    app(TagContentItems::class)->handle([$item], ['canada', 'easy-read']);
    app(TagContentItems::class)->handle([$item], ['holiday']);
    expect($item->tags()->pluck('name')->sort()->values()->all())->toBe(['canada', 'easy-read', 'holiday']);

    app(TagContentItems::class)->handle([$item], ['holiday'], replace: true);
    expect($item->tags()->pluck('name')->all())->toBe(['holiday']);
});

it('ImportContentRow creates and re-imports by source_ref', function () {
    Category::factory()->create(['slug' => 'capitals-americas']);
    $row = [
        'category_slug' => 'capitals-americas', 'type_key' => 'multiple_choice', 'difficulty' => '4', 'min_age' => '8', 'status' => 'published',
        'tags' => 'americas; capitals', 'source_ref' => 'csv-1', 'question' => 'Capital of Peru?',
        'choice_a' => 'Lima', 'choice_b' => 'Quito', 'choice_c' => 'Bogotá', 'correct' => 'a',
    ];

    $item = app(ImportContentRow::class)->handle($row);
    expect($item->source)->toBe('csv')->and($item->min_age)->toBe(8)->and($item->tags()->count())->toBe(2);

    app(ImportContentRow::class)->handle([...$row, 'question' => 'What is the capital of Peru?']);
    expect(ContentItem::query()->count())->toBe(1)
        ->and($item->refresh()->payload['question'])->toBe('What is the capital of Peru?');

    expect(fn () => app(ImportContentRow::class)->handle([...$row, 'category_slug' => 'nope']))->toThrow(ValidationException::class);
    expect(fn () => app(ImportContentRow::class)->handle([...$row, 'source_ref' => 'csv-2', 'correct' => 'f']))->toThrow(ValidationException::class);
});

it('PreviewContentItem shows the client payload and serving rules per band', function () {
    $item = ContentItem::factory()->difficulty(7)->create(['min_age' => 11]);
    $child = AgeBand::query()->where('code', 'child')->firstOrFail();
    $adult = AgeBand::query()->where('code', 'adult')->firstOrFail();

    $forChild = app(PreviewContentItem::class)->handle($item, $child, 9);
    expect($forChild['served'])->toBeFalse()
        ->and($forChild['reasons'])->toHaveCount(2)
        ->and($forChild['payload'])->not->toHaveKey('answer')
        ->and($forChild['ui_theme'])->toBe('playful');

    expect(app(PreviewContentItem::class)->handle($item, $adult, 30)['served'])->toBeTrue();
});

it('ResolveCategoryAccess checks visibility and the tier lock', function () {
    $roots = collect(range(1, 4))->map(fn ($i) => Category::factory()->create(['sort' => $i]));
    $child = Category::factory()->childOf($roots[3])->create();
    $owner = familyOwner();
    $access = app(ResolveCategoryAccess::class);

    expect($access->lockedRootIds($owner))->toBe([$roots[3]->id])
        ->and($access->canPlay($owner, $roots[0]->id))->toBeTrue()
        ->and($access->canPlay($owner, $child->id))->toBeFalse()
        ->and($access->isVisible($owner, $child->id))->toBeTrue();
});

it('CategoryTree returns subtrees and roots', function () {
    $root = Category::factory()->create();
    $mid = Category::factory()->childOf($root)->create();
    $leaf = Category::factory()->childOf($mid)->create();
    $tree = app(CategoryTree::class);

    expect($tree->subtreeIds($root->id))->toBe([$root->id, $mid->id, $leaf->id])
        ->and($tree->rootOf($leaf->id)?->id)->toBe($root->id);
});

it('seeds all 17 top-level categories and ~50+ items per launch type, idempotently', function () {
    $this->seed([CategorySeeder::class, ChallengeTypeSeeder::class, ContentSeeder::class]);

    $topLevel = array_keys(require database_path('seeders/data/categories.php'));
    sort($topLevel);

    expect($topLevel)->toHaveCount(17)->toContain('brain-games', 'world-geography')
        ->and(Category::query()->whereNull('parent_id')->pluck('slug')->sort()->values()->all())->toBe($topLevel);

    $mc = ContentItem::query()->where('type_key', 'multiple_choice')->count();
    $wc = ContentItem::query()->where('type_key', 'wisdom_card')->count();
    expect($mc)->toBeGreaterThanOrEqual(50)->and($wc)->toBeGreaterThanOrEqual(50);

    $this->seed(ContentSeeder::class);
    expect(ContentItem::query()->count())->toBe($mc + $wc);
});
