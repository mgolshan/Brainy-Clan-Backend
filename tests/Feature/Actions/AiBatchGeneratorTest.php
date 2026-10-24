<?php

use App\Modules\Content\Actions\GenerateContentBatch;
use App\Modules\Content\Actions\QueueContentBatch;
use App\Modules\Content\Actions\ReviewContentBatchItems;
use App\Modules\Content\Generation\ContentBatchAgent;
use App\Modules\Content\Jobs\GenerateContentBatchJob;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentBatch;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StructuredTextResponse;

/**
 * B7 AI batch generator: queueing, generation with a faked model (Laravel AI SDK fake),
 * validation, de-duplication, failures, and the review decisions.
 */

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function mcItem(string $question, array $overrides = []): array
{
    return [
        'question' => $question,
        'choice_a' => 'Mars', 'choice_b' => 'Venus', 'choice_c' => 'Jupiter', 'choice_d' => 'Saturn',
        'correct' => 'a',
        'explanation' => 'Iron oxide dust makes Mars look red.',
        'difficulty' => 3,
        ...$overrides,
    ];
}

/**
 * @param  list<array<string, mixed>>  $items
 */
function fakeModel(array $items, int $input = 1500, int $output = 4200): void
{
    ContentBatchAgent::fake([
        new StructuredTextResponse(['items' => $items], (string) json_encode(['items' => $items]), new TextUsage($input, $output), new Meta('anthropic', 'claude-sonnet-5-5')),
    ])->preventStrayPrompts();
}

function geographyBatch(array $attributes = []): ContentBatch
{
    $root = Category::factory()->create(['name' => ['en' => 'Science & Nature']]);
    $space = Category::factory()->childOf($root)->create(['name' => ['en' => 'Space']]);

    return ContentBatch::factory()->create(['category_id' => $space->id, 'requested_count' => 5, 'note' => 'Focus on planets.', ...$attributes]);
}

it('stores valid items as drafts, drops invalid ones and skips duplicates', function () {
    $batch = geographyBatch();
    ContentItem::factory()->create(['payload' => ['question' => 'Which planet is the largest?', 'choices' => [['id' => 'a', 'text' => 'Jupiter'], ['id' => 'b', 'text' => 'Mars']], 'image_url' => null]]);

    fakeModel([
        mcItem('Which planet is called the Red Planet?'),
        mcItem('Which planet has the most famous rings?', ['correct' => 'd', 'difficulty' => 9]),
        mcItem('', ['correct' => 'a']),                                // invalid: empty question
        mcItem('Which planet is closest to the Sun?', ['correct' => 'x']), // invalid: answer not a choice
        mcItem('which planet is the LARGEST'),                          // duplicate of an existing item
        mcItem('Which planet is called the red planet!'),              // duplicate inside the batch
    ]);

    $batch = app(GenerateContentBatch::class)->handle($batch);

    expect($batch->status)->toBe(ContentBatch::STATUS_REVIEW)
        ->and($batch->item_count)->toBe(2)
        ->and($batch->input_tokens)->toBe(1500)
        ->and($batch->output_tokens)->toBe(4200)
        ->and($batch->model)->toBe('claude-sonnet-5-5')
        ->and($batch->skipped['invalid'])->toHaveCount(2)
        ->and($batch->skipped['invalid'][1]['item'])->toBe(4)
        ->and($batch->skipped['duplicates'])->toHaveCount(2)
        ->and($batch->prompt)->toContain('Science & Nature → Space')
        ->and($batch->prompt)->toContain('Focus on planets.')
        ->and($batch->prompt)->toContain('public domain')
        ->and($batch->finished_at)->not->toBeNull();

    $items = $batch->items()->orderBy('id')->get();
    expect($items)->toHaveCount(2)
        ->and($items->pluck('status')->unique()->all())->toBe([ContentItem::STATUS_DRAFT])
        ->and($items->pluck('source')->unique()->all())->toBe(['ai'])
        ->and($items[0]->source_ref)->toStartWith('ai:en:')
        ->and($items[0]->min_age)->toBe(8)
        ->and($items[0]->max_age)->toBe(12)
        ->and($items[0]->answer)->toBe(['choice_id' => 'a', 'explanation' => 'Iron oxide dust makes Mars look red.'])
        ->and($items[1]->difficulty)->toBe(4); // clamped into the batch range 2–4

    ContentBatchAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Create 5 items.'));
});

it('lists existing items of the category in the prompt so the model avoids them', function () {
    $batch = geographyBatch();
    ContentItem::factory()->create(['category_id' => $batch->category_id, 'payload' => ['question' => 'How many moons does Mars have?', 'choices' => [['id' => 'a', 'text' => '2'], ['id' => 'b', 'text' => '1']], 'image_url' => null]]);
    fakeModel([mcItem('Which planet is called the Red Planet?')]);

    $batch = app(GenerateContentBatch::class)->handle($batch);

    expect($batch->prompt)->toContain('How many moons does Mars have?');
});

it('sends the mission and the content neutrality rules with every batch', function () {
    $batch = geographyBatch();
    fakeModel([mcItem('Which planet is called the Red Planet?')]);

    $batch = app(GenerateContentBatch::class)->handle($batch);

    expect($batch->prompt)
        ->toContain('learn and grow rather than waste their time and mind')
        ->toContain('no ideological or activist messaging from any side')
        ->toContain('No politics')
        ->toContain('No religion')
        ->toContain('sexuality or gender identity')
        ->toContain('No hate')
        ->toContain('No misinformation')
        ->toContain('ignore that part of the note');
});

it('fails the batch on a provider error', function () {
    $batch = geographyBatch();
    ContentBatchAgent::fake(fn () => throw new RuntimeException('Overloaded'));

    $batch = app(GenerateContentBatch::class)->handle($batch);

    expect($batch->status)->toBe(ContentBatch::STATUS_FAILED)
        ->and($batch->error)->toContain('Overloaded')
        ->and($batch->items()->count())->toBe(0);
});

it('fails the batch when nothing usable comes back', function () {
    $batch = geographyBatch();
    fakeModel([mcItem('', [])]);

    $batch = app(GenerateContentBatch::class)->handle($batch);

    expect($batch->status)->toBe(ContentBatch::STATUS_FAILED)
        ->and($batch->error)->toContain('No usable items')
        ->and($batch->skipped['invalid'])->toHaveCount(1);
});

it('fails the batch when the answer is not the expected JSON', function () {
    $batch = geographyBatch();
    ContentBatchAgent::fake([new StructuredTextResponse(['something' => 'else'], '{}', new TextUsage(10, 5), new Meta('anthropic', 'claude-sonnet-5-5'))]);

    expect(app(GenerateContentBatch::class)->handle($batch)->status)->toBe(ContentBatch::STATUS_FAILED);
});

it('keeps original wisdom cards and old quotes but drops modern quotes', function () {
    $batch = geographyBatch(['type_key' => 'wisdom_card']);
    $card = fn (array $overrides) => [
        'origin' => 'original', 'card_text' => 'Small steps every day add up to a long journey.', 'card_author' => null, 'card_source' => null,
        'author_death_year' => null, 'question' => 'What is the main idea?',
        'choice_a' => 'Keep going a little at a time', 'choice_b' => 'Only big steps count', 'choice_c' => 'Journeys are boring',
        'correct' => 'a', 'explanation' => 'Steady effort compounds.', 'difficulty' => 2, ...$overrides,
    ];

    fakeModel([
        $card([]),
        $card(['origin' => 'public_domain_quote', 'card_text' => 'We suffer more often in imagination than in reality.', 'card_author' => 'Seneca', 'card_source' => 'Letters to Lucilius', 'author_death_year' => 65]),
        $card(['origin' => 'public_domain_quote', 'card_text' => 'A modern line.', 'card_author' => 'Recent Author', 'card_source' => 'A 1990s book', 'author_death_year' => 2001]),
        $card(['origin' => 'public_domain_quote', 'card_text' => 'No source given.', 'card_author' => 'Plato', 'card_source' => null, 'author_death_year' => -347]),
    ]);

    $batch = app(GenerateContentBatch::class)->handle($batch);
    $items = $batch->items()->orderBy('id')->get();

    expect($items)->toHaveCount(2)
        ->and($items[0]->payload['card']['author'])->toBeNull()
        ->and($items[1]->payload['card']['author'])->toBe('Seneca')
        ->and($batch->skipped['invalid'])->toHaveCount(2)
        ->and($batch->skipped['invalid'][0]['errors'][0])->toContain('not clearly public domain');
});

it('builds word puzzles through the handler', function (string $variant, array $raw, Closure $check) {
    $batch = geographyBatch(['type_key' => 'word_puzzle', 'variant' => $variant]);
    fakeModel([[...$raw, 'explanation' => 'A fun word.', 'difficulty' => 3]]);

    $batch = app(GenerateContentBatch::class)->handle($batch);
    $item = $batch->items()->firstOrFail();

    expect($batch->status)->toBe(ContentBatch::STATUS_REVIEW)
        ->and($item->payload['variant'])->toBe($variant);
    $check($item);
})->with([
    'anagram' => ['anagram', ['word' => 'planet', 'hint' => 'Earth is one', 'also_accepted' => ['platen']], fn (ContentItem $item) => expect($item->answer['words'])->toBe(['PLANET', 'PLATEN'])->and($item->payload['letters'])->toHaveCount(6)],
    'missing letter' => ['missing_letter', ['word' => 'comet', 'hint' => 'Icy visitor with a tail'], fn (ContentItem $item) => expect($item->answer['word'])->toBe('COMET')->and($item->payload['pattern'])->toContain('_')],
    'word search' => ['word_search', ['theme' => 'Space', 'words' => ['MOON', 'STAR', 'SUN', 'COMET', 'ORBIT']], fn (ContentItem $item) => expect($item->payload['words'])->toHaveCount(5)->and($item->payload['grid'])->toHaveCount(6)],
]);

it('queues a batch and dispatches the job on the ai queue', function () {
    config(['ai.providers.anthropic.key' => 'test-key']);
    Queue::fake();
    $category = Category::factory()->create();
    $staff = User::factory()->staff()->create();

    $batch = app(QueueContentBatch::class)->handle([
        'category_id' => $category->id, 'generation_type' => 'word_puzzle:anagram', 'locale' => 'en',
        'min_difficulty' => 2, 'max_difficulty' => 5, 'min_age' => 8, 'max_age' => null, 'count' => 50, 'note' => ' Animals only ',
    ], $staff);

    expect($batch->status)->toBe(ContentBatch::STATUS_QUEUED)
        ->and($batch->type_key)->toBe('word_puzzle')
        ->and($batch->variant)->toBe('anagram')
        ->and($batch->note)->toBe('Animals only')
        ->and($batch->created_by)->toBe($staff->id);

    Queue::assertPushed(GenerateContentBatchJob::class, fn (GenerateContentBatchJob $job) => $job->batchId === $batch->id && $job->queue === 'ai');
});

it('rejects invalid requests and refuses without an API key', function () {
    $category = Category::factory()->create();
    $data = ['category_id' => $category->id, 'generation_type' => 'multiple_choice', 'locale' => 'en', 'min_difficulty' => 2, 'max_difficulty' => 5, 'count' => 10];

    expect(fn () => app(QueueContentBatch::class)->handle($data))->toThrow(ValidationException::class, 'not configured');

    config(['ai.providers.anthropic.key' => 'test-key']);
    Queue::fake();

    foreach ([['count' => 51], ['generation_type' => 'sudoku'], ['max_difficulty' => 1], ['category_id' => 999999]] as $bad) {
        expect(fn () => app(QueueContentBatch::class)->handle([...$data, ...$bad]))->toThrow(ValidationException::class);
    }

    Queue::assertNothingPushed();
    expect(ContentBatch::query()->count())->toBe(0);
});

it('runs the job end to end', function () {
    $batch = geographyBatch();
    fakeModel([mcItem('Which planet is called the Red Planet?')]);

    (new GenerateContentBatchJob($batch->id))->handle(app(GenerateContentBatch::class));

    expect($batch->refresh()->status)->toBe(ContentBatch::STATUS_REVIEW);
});

it('marks the batch failed when the job dies', function () {
    $batch = geographyBatch(['status' => ContentBatch::STATUS_RUNNING]);

    (new GenerateContentBatchJob($batch->id))->failed(new RuntimeException('Timed out'));

    expect($batch->refresh()->status)->toBe(ContentBatch::STATUS_FAILED)
        ->and($batch->error)->toContain('Timed out');
});

it('approves drafts by publishing them and rejects by archiving', function () {
    $reviewer = User::factory()->staff()->create();
    $batch = ContentBatch::factory()->inReview(3)->create();
    $items = ContentItem::factory()->draft()->count(3)->create(['content_batch_id' => $batch->id, 'source' => 'ai']);
    $review = app(ReviewContentBatchItems::class);

    expect($review->handle($batch, [$items[0], $items[1]], ReviewContentBatchItems::APPROVE, $reviewer))->toBe(['changed' => 2, 'skipped' => []]);

    $items[0]->refresh();
    expect($items[0]->status)->toBe(ContentItem::STATUS_PUBLISHED)
        ->and($items[0]->reviewed_by)->toBe($reviewer->id)
        ->and($items[0]->published_at)->not->toBeNull()
        ->and($batch->refresh()->status)->toBe(ContentBatch::STATUS_REVIEW);

    $review->handle($batch, [$items[2]], ReviewContentBatchItems::REJECT, $reviewer);

    expect($items[2]->refresh()->status)->toBe(ContentItem::STATUS_ARCHIVED)
        ->and($items[2]->reviewed_by)->toBe($reviewer->id)
        ->and($batch->refresh()->status)->toBe(ContentBatch::STATUS_DONE);
});

it('skips invalid drafts on approve and ignores items of other batches', function () {
    $reviewer = User::factory()->staff()->create();
    $batch = ContentBatch::factory()->inReview(1)->create();
    $broken = ContentItem::factory()->draft()->create(['content_batch_id' => $batch->id, 'answer' => ['choice_id' => 'z']]);
    $foreign = ContentItem::factory()->draft()->create();

    $result = app(ReviewContentBatchItems::class)->handle($batch, [$broken, $foreign], ReviewContentBatchItems::APPROVE, $reviewer);

    expect($result)->toBe(['changed' => 0, 'skipped' => [$broken->id]])
        ->and($broken->refresh()->status)->toBe(ContentItem::STATUS_DRAFT)
        ->and($foreign->refresh()->status)->toBe(ContentItem::STATUS_DRAFT);
});
