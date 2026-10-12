<?php

use App\Modules\ChallengeTypes\Handlers\MathSprint;
use App\Modules\ChallengeTypes\Support\Rational;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Content\Support\CategoryTree;
use App\Modules\Play\Actions\AddSessionItems;
use App\Modules\Play\Actions\PickContentItems;
use App\Modules\Play\Models\SessionItem;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\DB;

/**
 * Bugs found by the app against the live B7 backend (2026-10-11).
 */
it('falls back to the nearest difficulty below the range without unsigned underflow', function () {
    // Teen range 3–8, default 5: only easier items exist, so the "nearest difficulty" query runs
    // with difficulty < default (MySQL: `difficulty - 5` on an UNSIGNED column overflowed → 500).
    $category = Category::factory()->create();
    $easy = ContentItem::factory()->create(['category_id' => $category->id, 'difficulty' => 1]);
    $closer = ContentItem::factory()->create(['category_id' => $category->id, 'difficulty' => 2]);
    ContentItem::factory()->create(['category_id' => $category->id, 'difficulty' => 10]);

    $owner = familyOwner(['tier' => 'family']);
    $teen = addMember($owner->family, 14);

    $picked = app(PickContentItems::class)->handle($teen, $category->id, 2);

    expect($picked->pluck('id')->all())->toBe([$closer->id, $easy->id]);

    actingAsMember($teen);
    $this->postJson('/api/v1/play/sessions', ['category_id' => $category->id])->assertCreated();
});

it('computes the difficulty distance without subtracting a larger value from the column', function () {
    expect(PickContentItems::DIFFICULTY_DISTANCE_SQL)->not->toContain('ABS(');

    $category = Category::factory()->create();
    foreach ([1, 5, 9] as $difficulty) {
        ContentItem::factory()->create(['category_id' => $category->id, 'difficulty' => $difficulty]);
    }

    $distances = DB::table('content_items')
        ->selectRaw(PickContentItems::DIFFICULTY_DISTANCE_SQL.' as distance', PickContentItems::difficultyDistanceBindings(5))
        ->orderBy('difficulty')
        ->pluck('distance')
        ->map(fn ($d) => (int) $d)
        ->all();

    expect($distances)->toBe([4, 0, 4]);
});

it('never repeats a generated question within a session', function () {
    // times tables at difficulty 1: only 25 different questions, so a 13-item session would
    // almost surely contain a duplicate without de-duplication.
    $root = Category::factory()->create();
    Category::factory()->childOf($root)->create([
        'generators' => [['type' => 'math_sprint', 'options' => ['mode' => 'times_tables'], 'min_difficulty' => 1, 'max_difficulty' => 1]],
    ]);
    actingAsMember(familyOwner(['tier' => 'family']));

    for ($run = 0; $run < 3; $run++) {
        $items = $this->postJson('/api/v1/play/sessions', ['category_id' => $root->id])->assertCreated()->json('data.items');
        $questions = collect($items)->map(fn (array $item) => str_replace(' ', '', $item['payload']['question']));

        expect($questions->count())->toBeGreaterThan(5)
            ->and($questions->unique()->count())->toBe($questions->count());
    }
});

it('normalizes question keys for de-duplication', function () {
    expect(AddSessionItems::questionKey(['question' => '1/3 + 1/3']))->toBe(AddSessionItems::questionKey(['question' => '1/3+1/3']))
        ->and(AddSessionItems::questionKey(['question' => 'Capital of Peru?']))->toBe(AddSessionItems::questionKey(['question' => 'capital of  peru?']))
        ->and(AddSessionItems::questionKey(['size' => 4, 'grid' => [[1]]]))->toStartWith('p:')
        ->and(AddSessionItems::questionKey([]))->toBeNull();
});

it('avoids a generated question that equals a content question in the same session', function () {
    $root = Category::factory()->create();
    Category::factory()->childOf($root)->create([
        'generators' => [['type' => 'math_sprint', 'options' => ['mode' => 'times_tables'], 'min_difficulty' => 1, 'max_difficulty' => 1]],
    ]);
    // Content items whose questions cover most of the 25 generated ones.
    foreach (range(1, 5) as $a) {
        foreach (range(1, 4) as $b) {
            ContentItem::factory()->create(['category_id' => $root->id, 'payload' => [
                'question' => "{$a} × {$b}", 'choices' => [['id' => 'a', 'text' => (string) ($a * $b)], ['id' => 'b', 'text' => '0']], 'image_url' => null,
            ]]);
        }
    }
    actingAsMember(familyOwner(['tier' => 'family']));

    $session = $this->postJson('/api/v1/play/sessions', ['category_id' => $root->id])->assertCreated()->json('data.session.id');
    $items = SessionItem::query()->where('play_session_id', $session)->with('contentItem')->get();
    $questions = $items->map(fn (SessionItem $item) => ($item->payload ?? $item->contentItem?->payload)['question']);

    expect($questions->unique()->count())->toBe($questions->count());
});

it('accepts only the fully reduced fraction for simplify questions', function () {
    $handler = app(MathSprint::class);
    $seed = 0;

    do {
        $built = $handler->generate('simplify-'.$seed++, 3, ['mode' => 'fractions']);
    } while (! str_starts_with($built['payload']['question'], 'Simplify ') && $seed < 200);

    expect($built['payload']['question'])->toStartWith('Simplify ')
        ->and($built['answer']['reduced_only'])->toBeTrue()
        ->and($built['answer']['allow_decimal'])->toBeFalse();

    $item = (new ContentItem)->forceFill(['type_key' => 'math_sprint', 'payload' => $built['payload'], 'answer' => $built['answer']]);
    $reduced = Rational::parse($built['answer']['value'])['value'];
    $unreduced = ($reduced->numerator * 2).'/'.($reduced->denominator * 2);

    expect($handler->check($item, ['value' => $built['answer']['value']]))->toBeTrue()
        ->and($handler->check($item, ['value' => $unreduced]))->toBeFalse()
        ->and($handler->check($item, ['value' => (string) ($reduced->numerator / $reduced->denominator)]))->toBeFalse()
        ->and($handler->validate($built['payload'], $built['answer']))->toBe([]);

    // Other fraction questions still accept equivalent forms.
    $sum = (new ContentItem)->forceFill(['type_key' => 'math_sprint', 'payload' => ['question' => '1/4 + 1/4', 'answer_format' => 'fraction'], 'answer' => ['value' => '1/2', 'allow_decimal' => true]]);
    expect($handler->check($sum, ['value' => '2/4']))->toBeTrue();
});

it('reports whether a typed fraction is reduced', function () {
    expect(Rational::parse('7/8')['reduced'])->toBeTrue()
        ->and(Rational::parse('14/16')['reduced'])->toBeFalse()
        ->and(Rational::parse('1 1/2')['reduced'])->toBeTrue()
        ->and(Rational::parse('1 2/4')['reduced'])->toBeFalse()
        ->and(Rational::parse('3')['reduced'])->toBeTrue();
});

it('keeps 9x9 sudoku for ages 10 and up', function () {
    $this->seed(CategorySeeder::class);
    CategoryTree::flush();
    $nineByNine = Category::query()->where('slug', 'sudoku-9x9-easy')->value('id');

    expect(app(CategoryTree::class)->isVisible($nineByNine, 9))->toBeFalse()
        ->and(app(CategoryTree::class)->isVisible($nineByNine, 10))->toBeTrue();
});
