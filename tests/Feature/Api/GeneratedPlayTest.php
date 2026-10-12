<?php

use App\Modules\ChallengeTypes\Generators\WordSearchBuilder;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Play\Models\SessionItem;
use App\Modules\Scoring\Models\PointEntry;

/**
 * B7: sessions with generated items (Sudoku, Math Sprint) and word puzzles, through the API.
 */

/**
 * A top-level category with one sub-scope that declares a generator.
 *
 * @param  array<string, mixed>  $options
 * @return array{0: Category, 1: Category}
 */
function generatorCategory(string $type, array $options, int $min = 1, int $max = 10, array $childAttributes = []): array
{
    $root = Category::factory()->create();
    $child = Category::factory()->childOf($root)->create([
        'generators' => [['type' => $type, 'options' => $options, 'min_difficulty' => $min, 'max_difficulty' => $max]],
        ...$childAttributes,
    ]);

    return [$root, $child];
}

function sessionItemRow(int $id): SessionItem
{
    return SessionItem::query()->findOrFail($id);
}

function answerItem(int $sessionId, int $itemId, array $response)
{
    return test()->postJson("/api/v1/play/sessions/{$sessionId}/answers", ['session_item_id' => $itemId, 'response' => $response, 'time_ms' => 5000]);
}

it('plays a full sudoku session with generated items worth 3 points each', function () {
    [$root, $child] = generatorCategory('sudoku', ['size' => 4], 1, 3);
    $owner = familyOwner(['tier' => 'family']);
    actingAsMember($owner);

    $data = $this->postJson('/api/v1/play/sessions', ['category_id' => $root->id])->assertCreated()->json('data');

    // 3-point items, cap 3: 3 may be missed and 4 × 3 ≥ 10 remain → 7 items, not 13.
    expect($data['items'])->toHaveCount(7)
        ->and(collect($data['items'])->pluck('type')->unique()->all())->toBe(['sudoku'])
        ->and(collect($data['items'])->pluck('points')->unique()->all())->toBe([3])
        ->and($data['items'][0]['payload'])->toHaveKeys(['size', 'box', 'grid'])
        ->and($data['items'][0]['payload']['size'])->toBe(4)
        ->and(json_encode($data['items']))->not->toContain('solution');

    $row = sessionItemRow($data['items'][0]['id']);
    expect($row->content_item_id)->toBeNull()
        ->and($row->seed)->not->toBeNull()
        ->and($row->category_id)->toBe($child->id)
        ->and($row->answer['solution'])->toHaveCount(4);

    // A wrong (incomplete) grid is a mistake; a malformed one is a 422 and no mistake.
    answerItem($data['session']['id'], $row->id, ['grid' => [[1]]])->assertUnprocessable()->assertJsonValidationErrors('response.grid');
    answerItem($data['session']['id'], $row->id, ['grid' => $row->payload['grid']])
        ->assertOk()->assertJsonPath('data.is_correct', false)->assertJsonPath('data.points_awarded', 0)
        ->assertJsonPath('data.correct_response.grid', $row->answer['solution'])
        ->assertJsonPath('data.session.mistakes', 1);

    foreach (array_slice($data['items'], 1, 4) as $index => $item) {
        $solution = sessionItemRow($item['id'])->answer['solution'];
        $response = answerItem($data['session']['id'], $item['id'], ['grid' => $solution])
            ->assertOk()->assertJsonPath('data.is_correct', true)->assertJsonPath('data.points_awarded', 3);
    }

    $response->assertJsonPath('data.session.points', 12)->assertJsonPath('data.session.status', 'completed');

    $entries = PointEntry::query()->where('play_session_id', $data['session']['id'])->get();
    expect($entries)->toHaveCount(4)
        ->and($entries->pluck('amount')->unique()->all())->toBe([3])
        ->and($entries->pluck('category_id')->unique()->all())->toBe([$child->id]);

    $this->getJson('/api/v1/me/stats')->assertOk()->assertJsonPath('data.points_total', 12);
});

it('gives children small sudokus and adults bigger ones from the same category', function () {
    $root = Category::factory()->create();
    $four = Category::factory()->childOf($root)->create(['max_age' => 12, 'generators' => [['type' => 'sudoku', 'options' => ['size' => 4], 'min_difficulty' => 1, 'max_difficulty' => 3]]]);
    Category::factory()->childOf($root)->create(['generators' => [['type' => 'sudoku', 'options' => ['size' => 6], 'min_difficulty' => 3, 'max_difficulty' => 6]]]);
    Category::factory()->childOf($root)->create(['min_age' => 9, 'generators' => [['type' => 'sudoku', 'options' => ['size' => 9], 'min_difficulty' => 5, 'max_difficulty' => 6]]]);

    $owner = familyOwner(['tier' => 'family']);
    actingAsMember(addMember($owner->family, 9));
    $kid = $this->postJson('/api/v1/play/sessions', ['category_id' => $root->id])->assertCreated()->json('data.items');

    app('auth')->forgetGuards();
    actingAsMember($owner);
    $adult = $this->postJson('/api/v1/play/sessions', ['category_id' => $root->id])->assertCreated()->json('data.items');

    expect(collect($kid)->pluck('payload.size')->unique()->sort()->values()->all())->each->toBeIn([4, 6])
        ->and(collect($adult)->pluck('payload.size')->unique()->all())->each->toBeIn([6, 9]);
});

it('plays math sprint items with equivalent answers and advisory timers', function () {
    [$root] = generatorCategory('math_sprint', ['mode' => 'fractions']);
    actingAsMember(familyOwner(['tier' => 'family']));

    $data = $this->postJson('/api/v1/play/sessions', ['category_id' => $root->id])->assertCreated()->json('data');

    expect($data['items'])->toHaveCount(13)
        ->and($data['items'][0]['type'])->toBe('math_sprint')
        ->and($data['items'][0]['points'])->toBe(1)
        ->and($data['items'][0]['payload'])->toHaveKeys(['question', 'answer_format', 'time_limit_ms']);

    $sessionId = $data['session']['id'];
    $first = sessionItemRow($data['items'][0]['id']);

    answerItem($sessionId, $first->id, ['value' => 'banana'])->assertUnprocessable()->assertJsonValidationErrors('response.value');

    // Answer with an unreduced equivalent of the expected value.
    $expected = $first->answer['value'];
    $equivalent = str_contains($expected, '/') ? implode('/', array_map(fn ($n) => (int) $n * 3, explode('/', ltrim($expected, '-')))) : $expected;
    $equivalent = str_starts_with($expected, '-') ? '-'.$equivalent : $equivalent;

    answerItem($sessionId, $first->id, ['value' => $equivalent])
        ->assertOk()->assertJsonPath('data.is_correct', true)->assertJsonPath('data.points_awarded', 1)
        ->assertJsonPath('data.correct_response', ['value' => $expected]);

    $second = sessionItemRow($data['items'][1]['id']);
    answerItem($sessionId, $second->id, ['value' => '99999'])->assertOk()->assertJsonPath('data.is_correct', false);

    expect(PointEntry::query()->where('play_session_id', $sessionId)->sum('amount'))->toBe(1);
});

it('mixes content items and generated items when a category has both', function () {
    [$root, $child] = generatorCategory('math_sprint', ['mode' => 'times_tables']);
    ContentItem::factory()->count(20)->create(['category_id' => $child->id]);
    actingAsMember(familyOwner(['tier' => 'family']));

    $types = collect(range(1, 3))->flatMap(fn () => $this->postJson('/api/v1/play/sessions', ['category_id' => $root->id])->assertCreated()->json('data.items'))->pluck('type')->unique()->sort()->values()->all();

    expect($types)->toBe(['math_sprint', 'multiple_choice']);
});

it('plays word search content worth 3 points and lists word puzzle types', function () {
    $category = Category::factory()->create();
    $built = (new WordSearchBuilder)->build(['CAT', 'DOG', 'COW'], 6, WordSearchBuilder::LEVELS['easy'], 'api');
    ContentItem::factory()->count(6)->create([
        'category_id' => $category->id,
        'type_key' => 'word_puzzle',
        'payload' => ['variant' => 'word_search', 'grid' => $built['grid'], 'words' => array_column($built['found'], 'word')],
        'answer' => ['found' => $built['found']],
    ]);
    actingAsMember(familyOwner(['tier' => 'family']));

    $data = $this->postJson('/api/v1/play/sessions', ['category_id' => $category->id])->assertCreated()->json('data');

    expect($data['items'][0]['type'])->toBe('word_puzzle')
        ->and($data['items'][0]['points'])->toBe(3)
        ->and($data['items'][0]['payload'])->toBe(['variant' => 'word_search', 'grid' => $built['grid'], 'words' => array_column($built['found'], 'word')]);

    answerItem($data['session']['id'], $data['items'][0]['id'], ['found' => $built['found']])
        ->assertOk()->assertJsonPath('data.is_correct', true)->assertJsonPath('data.points_awarded', 3)
        ->assertJsonPath('data.correct_response.found', $built['found']);

    expect(PointEntry::query()->where('play_session_id', $data['session']['id'])->value('amount'))->toBe(3);
});

it('tops up generated items after a second chance', function () {
    [$root] = generatorCategory('sudoku', ['size' => 9], 1, 10);
    actingAsMember(familyOwner(['tier' => 'family']));

    $data = $this->postJson('/api/v1/play/sessions', ['category_id' => $root->id])->assertCreated()->json('data');
    // 8-point items: 3 spare + 2 × 8 ≥ 10 → 5 items.
    expect($data['items'])->toHaveCount(5);

    foreach (array_slice($data['items'], 0, 3) as $item) {
        $last = answerItem($data['session']['id'], $item['id'], ['grid' => sessionItemRow($item['id'])->payload['grid']])->assertOk();
    }

    $last->assertJsonPath('data.session.status', 'failed');

    $after = $this->postJson("/api/v1/play/sessions/{$data['session']['id']}/second-chance", ['method' => 'free'])->assertOk()->json('data');

    expect($after['session']['status'])->toBe('active')
        ->and(collect($after['items'])->where('answered', false)->count())->toBe(5);
});

it('runs a redemption round with generated items', function () {
    [$root] = generatorCategory('math_sprint', ['mode' => 'mental']);
    actingAsMember(familyOwner(['tier' => 'free']));

    $data = $this->postJson('/api/v1/play/sessions', ['category_id' => $root->id])->assertCreated()->json('data');

    foreach (array_slice($data['items'], 0, 3) as $item) {
        answerItem($data['session']['id'], $item['id'], ['value' => '-99999'])->assertOk();
    }

    $round = $this->postJson("/api/v1/play/sessions/{$data['session']['id']}/redemption")->assertOk()->json('data');
    $redemption = collect($round['items'])->where('is_redemption', true)->values();

    expect($redemption)->toHaveCount(3);

    foreach ($redemption as $item) {
        $last = answerItem($data['session']['id'], $item['id'], ['value' => sessionItemRow($item['id'])->answer['value']])
            ->assertOk()->assertJsonPath('data.points_awarded', 0);
    }

    $last->assertJsonPath('data.session.status', 'active');
});

it('lists generated types in the category tree', function () {
    [$root, $child] = generatorCategory('sudoku', ['size' => 4]);
    actingAsMember(familyOwner(['tier' => 'family']));

    $node = collect($this->getJson('/api/v1/categories')->assertOk()->json('data'))->firstWhere('id', $root->id);

    expect($node['challenge_types'])->toBe(['sudoku'])
        ->and($node['children'][0]['challenge_types'])->toBe(['sudoku']);
});

it('keeps a generated item payload stable for the whole session', function () {
    [$root] = generatorCategory('sudoku', ['size' => 6], 1, 10);
    actingAsMember(familyOwner(['tier' => 'family']));

    $started = $this->postJson('/api/v1/play/sessions', ['category_id' => $root->id])->assertCreated()->json('data.items');
    $sessionId = SessionItem::query()->findOrFail($started[0]['id'])->play_session_id;

    $first = $this->getJson("/api/v1/play/sessions/{$sessionId}")->assertOk()->json('data.items');
    answerItem($sessionId, $started[0]['id'], ['grid' => sessionItemRow($started[0]['id'])->payload['grid']])->assertOk();
    $second = $this->getJson("/api/v1/play/sessions/{$sessionId}")->assertOk()->json('data.items');

    expect(collect($first)->pluck('payload', 'id')->all())->toBe(collect($started)->pluck('payload', 'id')->all())
        ->and(collect($second)->pluck('payload', 'id')->all())->toBe(collect($started)->pluck('payload', 'id')->all());
});
