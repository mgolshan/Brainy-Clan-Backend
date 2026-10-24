<?php

use App\Modules\ChallengeTypes\Generators\NonogramGenerator;
use App\Modules\ChallengeTypes\Handlers\MemoryMatch;
use App\Modules\ChallengeTypes\Handlers\Nonogram;
use App\Modules\ChallengeTypes\Handlers\PatternSequence;
use App\Modules\ChallengeTypes\Handlers\SpotDifference;
use App\Modules\Content\Models\Category;
use App\Modules\Identity\Models\User;
use App\Modules\Play\Models\PlaySession;
use App\Modules\Play\Models\SessionItem;
use App\Modules\Scoring\Models\PointEntry;
use Illuminate\Support\Facades\DB;

/**
 * Brain games through the API: a Brain Games category whose four leaves generate memory match,
 * pattern sequence, nonogram and spot-the-difference items.
 */
function brainGamesCategory(): Category
{
    $root = Category::factory()->create(['slug' => 'brain-games-test']);

    foreach ([['memory_match', 1], ['pattern_sequence', 1], ['nonogram', 3], ['spot_difference', 1]] as [$type, $min]) {
        Category::factory()->childOf($root)->create([
            'generators' => [['type' => $type, 'options' => [], 'min_difficulty' => $min, 'max_difficulty' => 10]],
        ]);
    }

    return $root;
}

/**
 * The winning response for a served item, worked out from what the app receives (plus the stored
 * solution for nonograms, which the app has to work out itself).
 *
 * @param  array<string, mixed>  $item  as served
 * @return array<string, mixed>
 */
function brainGamesWinningResponse(array $item): array
{
    $payload = $item['payload'];

    return match ($item['type']) {
        'memory_match' => ['attempts' => collect($payload['cards'])->map(fn ($face, $i) => [$face, $i])->groupBy(0)
            ->map(fn ($group) => $group->pluck(1)->all())->values()->all()],
        'pattern_sequence' => ['input' => $payload['sequence']],
        'nonogram' => ['grid' => SessionItem::query()->findOrFail($item['id'])->answer['solution']],
        'spot_difference' => ['taps' => collect($payload['changes'])->map(function (array $change) use ($payload) {
            $object = collect($payload['objects'])->firstWhere('id', $change['object_id']);

            return [$object['x'], $object['y'] * $payload['aspect']];
        })->all()],
    };
}

/**
 * A malformed response per type (422, not a mistake).
 *
 * @return array{0: array<string, mixed>, 1: string}
 */
function brainGamesMalformed(string $type): array
{
    return match ($type) {
        'memory_match' => [['attempts' => [[0, 0]]], 'response.attempts'],
        'pattern_sequence' => [['input' => [99]], 'response.input'],
        'nonogram' => [['grid' => [[1, 0]]], 'response.grid'],
        'spot_difference' => [['taps' => array_fill(0, 61, [0.5, 0.5])], 'response.taps'],
    };
}

it('plays a full brain games session through the API and reaches the target', function () {
    $root = brainGamesCategory();
    $owner = familyOwner(['tier' => 'family']);
    actingAsMember($owner);

    $data = $this->postJson('/api/v1/play/sessions', ['category_id' => $root->id])->assertCreated()->json('data');
    $sessionId = $data['session']['id'];
    $items = collect($data['items']);

    expect($items->pluck('type')->unique()->every(fn ($type) => in_array($type, ['memory_match', 'pattern_sequence', 'nonogram', 'spot_difference'], true)))->toBeTrue()
        ->and(json_encode($data['items']))->not->toContain('solution')->not->toContain('"title"');

    // Points match the item's size; the batch keeps the target reachable within the cap.
    $handlers = ['memory_match' => MemoryMatch::class, 'pattern_sequence' => PatternSequence::class, 'nonogram' => Nonogram::class, 'spot_difference' => SpotDifference::class];

    foreach ($items as $item) {
        $row = SessionItem::query()->findOrFail($item['id']);
        expect($item['points'])->toBe(app($handlers[$item['type']])->points($row->playable()));
    }

    $first = $items->first();
    [$malformed, $field] = brainGamesMalformed($first['type']);
    $this->postJson("/api/v1/play/sessions/{$sessionId}/answers", ['session_item_id' => $first['id'], 'response' => $malformed])
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    $earned = 0;

    foreach ($items as $item) {
        $response = $this->postJson("/api/v1/play/sessions/{$sessionId}/answers", ['session_item_id' => $item['id'], 'response' => brainGamesWinningResponse($item), 'time_ms' => 20000])
            ->assertOk()
            ->assertJsonPath('data.is_correct', true)
            ->assertJsonPath('data.points_awarded', $item['points']);

        $earned += $item['points'];
        $correct = $response->json('data.correct_response');

        match ($item['type']) {
            'memory_match' => expect($correct)->toBe(['attempts' => null, 'cards' => $item['payload']['cards']]),
            'pattern_sequence' => expect($correct)->toBe(['input' => $item['payload']['sequence']]),
            'nonogram' => expect($correct['title'])->toBeString()->not->toBe(''),
            'spot_difference' => expect($correct['changes'])->toBe(array_column($item['payload']['changes'], 'id')),
        };

        if ($response->json('data.session.status') === PlaySession::STATUS_COMPLETED) {
            break;
        }
    }

    expect($earned)->toBeGreaterThanOrEqual(10)
        ->and(PlaySession::query()->findOrFail($sessionId)->status)->toBe(PlaySession::STATUS_COMPLETED)
        ->and((int) PointEntry::query()->where('play_session_id', $sessionId)->sum('amount'))->toBe($earned);
});

it('counts wrong brain game answers as mistakes', function (string $type, array $wrong) {
    $root = Category::factory()->create();
    Category::factory()->childOf($root)->create(['generators' => [['type' => $type, 'options' => [], 'min_difficulty' => 3, 'max_difficulty' => 10]]]);
    actingAsMember(familyOwner(['tier' => 'family']));

    $data = $this->postJson('/api/v1/play/sessions', ['category_id' => $root->id])->assertCreated()->json('data');
    $item = $data['items'][0];
    $response = $type === 'nonogram' ? ['grid' => array_fill(0, $item['payload']['rows'], array_fill(0, $item['payload']['cols'], 0))] : $wrong;

    $this->postJson("/api/v1/play/sessions/{$data['session']['id']}/answers", ['session_item_id' => $item['id'], 'response' => $response])
        ->assertOk()->assertJsonPath('data.is_correct', false)->assertJsonPath('data.points_awarded', 0)
        ->assertJsonPath('data.session.mistakes', 1);
})->with([
    'memory match, no attempts' => ['memory_match', ['attempts' => []]],
    'pattern, too short' => ['pattern_sequence', ['input' => [0]]],
    'nonogram, empty grid' => ['nonogram', []],
    'spot the difference, only misses' => ['spot_difference', ['taps' => []]],
]);

it('reveals the nonogram title in the member language', function () {
    $root = Category::factory()->create();
    Category::factory()->childOf($root)->create(['generators' => [['type' => 'nonogram', 'options' => [], 'min_difficulty' => 3, 'max_difficulty' => 5]]]);
    $owner = familyOwner(['tier' => 'family']);
    $owner->user->forceFill(['locale' => 'fr'])->save();
    actingAsMember($owner);

    $data = $this->postJson('/api/v1/play/sessions', ['category_id' => $root->id])->assertCreated()->json('data');
    $item = $data['items'][0];
    $row = SessionItem::query()->findOrFail($item['id']);
    $picture = NonogramGenerator::library()[5][$row->answer['picture']];

    expect($item['points'])->toBe(3)->and($item['payload']['rows'])->toBe(5);

    $this->postJson("/api/v1/play/sessions/{$data['session']['id']}/answers", ['session_item_id' => $item['id'], 'response' => ['grid' => $row->answer['solution']]])
        ->assertOk()->assertJsonPath('data.is_correct', true)->assertJsonPath('data.points_awarded', 3)
        ->assertJsonPath('data.correct_response.title', $picture['fr']);
});

it('returns a brain games session payload with a constant number of queries', function () {
    $root = brainGamesCategory();
    $counts = [];

    foreach ([2, 8] as $rows) {
        $owner = familyOwner(['tier' => 'family']);
        $session = PlaySession::query()->create([
            'family_id' => $owner->family_id, 'member_id' => $owner->id, 'category_id' => $root->id,
            'target_points' => 10, 'blunder_cap' => 3, 'status' => PlaySession::STATUS_ACTIVE, 'started_at' => now(),
        ]);
        $types = ['memory_match' => MemoryMatch::class, 'pattern_sequence' => PatternSequence::class, 'nonogram' => Nonogram::class, 'spot_difference' => SpotDifference::class];

        for ($position = 1; $position <= $rows; $position++) {
            $type = array_keys($types)[$position % 4];
            $generated = app($types[$type])->generate("qc-{$position}", 5, ['locale' => 'en']);
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

    expect($counts[2])->toBe($counts[8]);
});

it('previews brain game samples in the admin', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get('/admin/content-preview?sample=memory_match&difficulty=9&seed=x')->assertOk()->assertSee('generated sample')->assertSee('3 points')->assertSee('attempts');
    $this->actingAs($staff)->get('/admin/content-preview?sample=pattern_sequence&difficulty=10')->assertOk()->assertSee('3 points')->assertSee('500 ms');
    $this->actingAs($staff)->get('/admin/content-preview?sample=nonogram&difficulty=6&seed=y')->assertOk()->assertSee('5 points')->assertSee('row_clues');
    $this->actingAs($staff)->get('/admin/content-preview?sample=spot_difference&difficulty=8')->assertOk()->assertSee('5 points')->assertSee('max 5 misses', false);
});
