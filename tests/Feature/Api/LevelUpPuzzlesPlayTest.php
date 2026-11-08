<?php

use App\Filament\Pages\AiBatchGenerator;
use App\Modules\ChallengeTypes\Generators\MazeGenerator;
use App\Modules\ChallengeTypes\Handlers\CodeBreaker;
use App\Modules\ChallengeTypes\Handlers\CrossMath;
use App\Modules\ChallengeTypes\Handlers\Maze;
use App\Modules\Content\Actions\QueueContentBatch;
use App\Modules\Content\Jobs\GenerateContentBatchJob;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ChallengeType;
use App\Modules\Identity\Models\User;
use App\Modules\Play\Models\PlaySession;
use App\Modules\Play\Models\SessionItem;
use App\Modules\Scoring\Models\PointEntry;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ChallengeTypeSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

/**
 * Level-up puzzles through the API (docs/API.md "Level-up puzzles"): the seeded categories
 * Cross Math, Mazes and Code Breaker, their levels, sessions, answers and points.
 */
const LEVEL_UP_TYPES = ['cross-math' => 'cross_math', 'mazes' => 'maze', 'code-breaker' => 'code_breaker'];

/**
 * The winning response for a served item: Cross Math from the stored solution (the member works
 * it out), mazes from the payload alone, the code is in the payload.
 *
 * @param  array<string, mixed>  $item  as served
 * @return array<string, mixed>
 */
function levelUpWinningResponse(array $item): array
{
    $payload = $item['payload'];

    return match ($item['type']) {
        'cross_math' => ['fills' => SessionItem::query()->findOrFail($item['id'])->answer['fills']],
        'maze' => ['path' => MazeGenerator::shortestTour($payload['cells'], $payload['start'], $payload['goal'], $payload['stars'])],
        // A first miss (the code reversed, unless that is the code), then the code.
        'code_breaker' => ['guesses' => array_reverse($payload['code']) === $payload['code'] ? [$payload['code']] : [array_reverse($payload['code']), $payload['code']]],
    };
}

/**
 * @return array{0: array<string, mixed>, 1: string}
 */
function levelUpMalformed(array $item): array
{
    return match ($item['type']) {
        'cross_math' => [['fills' => [[1, 1, 3]]], 'response.fills'],
        'maze' => [['path' => [[0, 0], [5, 5]]], 'response.path'],
        'code_breaker' => [['guesses' => [[0]]], 'response.guesses'],
    };
}

/**
 * @return array<string, mixed>
 */
function levelUpWrong(array $item): array
{
    return match ($item['type']) {
        'cross_math' => ['fills' => []],
        'maze' => ['path' => [$item['payload']['start']]],
        'code_breaker' => ['guesses' => []],
    };
}

/**
 * The difficulty a generated item was built at, read back from what it holds.
 */
function levelUpLevel(SessionItem $row): int
{
    return match ($row->type_key) {
        'cross_math' => $row->answer['difficulty'],
        'maze' => (int) array_search($row->payload['rows'], Maze::SIZES, true),
        'code_breaker' => (int) array_search([$row->payload['length'], count($row->payload['pegs']), $row->payload['repeats'], $row->payload['max_guesses']], CodeBreaker::LEVELS, true),
    };
}

beforeEach(function () {
    $this->seed([CategorySeeder::class, ChallengeTypeSeeder::class]);
});

it('lists 20 top-level categories in order, the three level-up categories last with four levels', function () {
    actingAsMember(familyOwner(['tier' => 'family']));
    $tree = $this->getJson('/api/v1/categories')->assertOk()->json('data');
    $slugs = array_keys(require database_path('seeders/data/categories.php'));

    expect(array_column($tree, 'slug'))->toBe($slugs)->toHaveCount(20)
        ->and(array_slice(array_column($tree, 'slug'), -3))->toBe(['cross-math', 'mazes', 'code-breaker'])
        ->and(array_slice(array_column($tree, 'icon'), -3))->toBe(['crossmath', 'maze', 'lock']);

    foreach (LEVEL_UP_TYPES as $slug => $type) {
        $node = collect($tree)->firstWhere('slug', $slug);

        expect($node['challenge_types'])->toBe([$type])
            ->and(array_column($node['children'], 'slug'))->toBe(["{$slug}-starter", "{$slug}-explorer", "{$slug}-expert", "{$slug}-master"])
            ->and(array_column($node['children'], 'min_age'))->toBe([null, null, 8, 10]);
    }

    // The free tier still unlocks the first three (unchanged).
    actingAsMember(familyOwner(['tier' => 'free']));
    $free = $this->getJson('/api/v1/categories')->assertOk()->json('data');
    expect(collect($free)->where('is_locked', false)->pluck('slug')->all())->toBe(['world-geography', 'science-nature', 'wisdom-cards']);
});

it('seeds the level-up categories and types idempotently', function () {
    $this->seed([CategorySeeder::class, ChallengeTypeSeeder::class]);

    expect(Category::query()->whereIn('slug', ['cross-math', 'mazes', 'code-breaker'])->count())->toBe(3)
        ->and(Category::query()->where('slug', 'like', 'mazes-%')->count())->toBe(4)
        ->and(Category::query()->whereNull('parent_id')->count())->toBe(20)
        ->and(ChallengeType::query()->whereIn('key', ['cross_math', 'maze', 'code_breaker'])->pluck('name', 'key')->all())
        ->toEqual(['code_breaker' => 'Code Breaker', 'cross_math' => 'Cross Math', 'maze' => 'Maze']);

    $master = Category::query()->where('slug', 'code-breaker-master')->firstOrFail();
    expect($master->min_age)->toBe(10)->and($master->icon)->toBe('lock')
        ->and($master->generators)->toBe([['type' => 'code_breaker', 'options' => [], 'min_difficulty' => 9, 'max_difficulty' => 10]]);
});

it('plays a full session in each level-up category and reaches the target', function (string $slug) {
    $type = LEVEL_UP_TYPES[$slug];
    $category = Category::query()->where('slug', $slug)->firstOrFail();
    actingAsMember(familyOwner(['tier' => 'family']));

    $data = $this->postJson('/api/v1/play/sessions', ['category_id' => $category->id])->assertCreated()->json('data');
    $sessionId = $data['session']['id'];
    $items = collect($data['items']);
    $handler = app(['cross_math' => CrossMath::class, 'maze' => Maze::class, 'code_breaker' => CodeBreaker::class][$type]);

    expect($items->pluck('type')->unique()->values()->all())->toBe([$type])
        ->and(json_encode($data['items']))->not->toContain('"fills"')->not->toContain('"path"')->not->toContain('"difficulty"');

    foreach ($items as $item) {
        $row = SessionItem::query()->findOrFail($item['id']);
        expect($item['points'])->toBe($handler->points($row->playable()))
            ->and($handler->validate($item['payload'], $row->answer))->toBe([]);
    }

    $first = $items->first();
    [$malformed, $field] = levelUpMalformed($first);
    $this->postJson("/api/v1/play/sessions/{$sessionId}/answers", ['session_item_id' => $first['id'], 'response' => $malformed])
        ->assertUnprocessable()->assertJsonPath('code', 'validation_failed')->assertJsonValidationErrors($field);

    $earned = 0;

    foreach ($items as $item) {
        $response = $this->postJson("/api/v1/play/sessions/{$sessionId}/answers", ['session_item_id' => $item['id'], 'response' => levelUpWinningResponse($item), 'time_ms' => 30000])
            ->assertOk()
            ->assertJsonPath('data.is_correct', true)
            ->assertJsonPath('data.points_awarded', $item['points'])
            ->assertJsonPath('data.session.mistakes', 0);

        $earned += $item['points'];
        $correct = $response->json('data.correct_response');

        match ($type) {
            'cross_math' => expect($correct['fills'])->toHaveCount(count($item['payload']['bank'])),
            'maze' => expect($correct['path'][0])->toBe($item['payload']['start'])->and(end($correct['path']))->toBe($item['payload']['goal']),
            'code_breaker' => expect($correct)->toBe(['guesses' => null, 'code' => $item['payload']['code']]),
        };

        if ($response->json('data.session.status') === PlaySession::STATUS_COMPLETED) {
            break;
        }
    }

    expect($earned)->toBeGreaterThanOrEqual(10)
        ->and(PlaySession::query()->findOrFail($sessionId)->status)->toBe(PlaySession::STATUS_COMPLETED)
        ->and((int) PointEntry::query()->where('play_session_id', $sessionId)->sum('amount'))->toBe($earned);
})->with(array_keys(LEVEL_UP_TYPES));

it('counts wrong level-up answers as mistakes', function (string $slug) {
    actingAsMember(familyOwner(['tier' => 'family']));
    $data = $this->postJson('/api/v1/play/sessions', ['category_id' => Category::query()->where('slug', $slug)->value('id')])->assertCreated()->json('data');
    $item = $data['items'][0];

    $this->postJson("/api/v1/play/sessions/{$data['session']['id']}/answers", ['session_item_id' => $item['id'], 'response' => levelUpWrong($item)])
        ->assertOk()->assertJsonPath('data.is_correct', false)->assertJsonPath('data.points_awarded', 0)
        ->assertJsonPath('data.session.mistakes', 1);
})->with(array_keys(LEVEL_UP_TYPES));

it('keeps a young child on the easy levels (member range ∩ level range)', function (string $slug) {
    $owner = familyOwner(['tier' => 'family']);
    actingAsMember(addMember($owner->family, 7));

    $data = $this->postJson('/api/v1/play/sessions', ['category_id' => Category::query()->where('slug', $slug)->value('id')])->assertCreated()->json('data');
    $rows = SessionItem::query()->whereIn('id', array_column($data['items'], 'id'))->get();

    expect($rows->map(levelUpLevel(...))->max())->toBeLessThanOrEqual(4)
        ->and($rows->pluck('category_id')->unique()->diff(Category::query()->whereIn('slug', ["{$slug}-starter", "{$slug}-explorer"])->pluck('id'))->all())->toBe([]);

    // The Master level alone is out of a child's range: the nearest level (9) is used, like Sudoku.
    $master = Category::query()->where('slug', "{$slug}-master")->firstOrFail();
    $master->update(['min_age' => null]);
    $data = $this->postJson('/api/v1/play/sessions', ['category_id' => $master->id])->assertCreated()->json('data');
    $rows = SessionItem::query()->whereIn('id', array_column($data['items'], 'id'))->get();

    expect($rows->map(levelUpLevel(...))->unique()->values()->all())->toBe([9]);
})->with(array_keys(LEVEL_UP_TYPES));

it('returns a level-up session payload with a constant number of queries', function () {
    $root = Category::query()->where('slug', 'cross-math')->firstOrFail();
    $types = ['cross_math' => CrossMath::class, 'maze' => Maze::class, 'code_breaker' => CodeBreaker::class];
    $counts = [];

    foreach ([2, 9] as $rows) {
        $owner = familyOwner(['tier' => 'family']);
        $session = PlaySession::query()->create([
            'family_id' => $owner->family_id, 'member_id' => $owner->id, 'category_id' => $root->id,
            'target_points' => 10, 'blunder_cap' => 3, 'status' => PlaySession::STATUS_ACTIVE, 'started_at' => now(),
        ]);

        for ($position = 1; $position <= $rows; $position++) {
            $type = array_keys($types)[$position % 3];
            $generated = app($types[$type])->generate("qc-{$position}", 6, ['locale' => 'en']);
            SessionItem::query()->create([
                'play_session_id' => $session->id, 'type_key' => $type, 'category_id' => $root->id, 'seed' => "qc-{$position}",
                'position' => $position, 'points' => 2, 'payload' => $generated['payload'], 'answer' => $generated['answer'],
            ]);
        }

        app('auth')->forgetGuards();
        actingAsMember($owner);
        $this->getJson("/api/v1/play/sessions/{$session->id}")->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson("/api/v1/play/sessions/{$session->id}")->assertOk()->assertJsonCount($rows, 'data.items');
        $counts[$rows] = count(DB::getQueryLog());
        DB::disableQueryLog();
    }

    expect($counts[2])->toBe($counts[9]);
});

it('previews level-up samples in the admin', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get('/admin/content-preview?sample=cross_math&difficulty=9&seed=x')->assertOk()->assertSee('generated sample')->assertSee('8 points')->assertSee('Bank:');
    $this->actingAs($staff)->get('/admin/content-preview?sample=maze&difficulty=10&seed=y')->assertOk()->assertSee('5 points')->assertSee('16×16 · 4 stars', false);
    $this->actingAs($staff)->get('/admin/content-preview?sample=code_breaker&difficulty=6')->assertOk()->assertSee('3 points')->assertSee('repeats allowed');
});

it('keeps generated-only categories out of the AI batch generator', function () {
    config(['ai.providers.anthropic.key' => 'test-key']);
    Queue::fake();
    $options = AiBatchGenerator::categoryOptions();
    $ids = fn (array $slugs) => Category::query()->whereIn('slug', $slugs)->pluck('id')->all();

    expect(array_intersect(array_keys($options), $ids(['cross-math', 'mazes-master', 'code-breaker-starter', 'brain-games', 'sudoku', 'sudoku-9x9-hard', 'math-sprint'])))->toBe([])
        ->and(array_intersect(array_keys($options), $ids(['world-geography', 'capitals-europe', 'word-puzzles', 'life-skills'])))->toHaveCount(4);

    $data = ['generation_type' => 'multiple_choice', 'locale' => 'en', 'min_difficulty' => 2, 'max_difficulty' => 5, 'count' => 10];

    expect(fn () => app(QueueContentBatch::class)->handle([...$data, 'category_id' => Category::query()->where('slug', 'mazes')->value('id')]))
        ->toThrow(ValidationException::class, 'generated puzzles');

    app(QueueContentBatch::class)->handle([...$data, 'category_id' => Category::query()->where('slug', 'life-skills')->value('id')]);
    Queue::assertPushed(GenerateContentBatchJob::class, 1);
});
