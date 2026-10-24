<?php

use App\Filament\Pages\AiBatchGenerator;
use App\Filament\Pages\AiBatchReview;
use App\Modules\Content\Jobs\GenerateContentBatchJob;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentBatch;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/**
 * B7 admin: AI batch generator page (form, batches table) and the batch review page.
 */
beforeEach(function () {
    $this->staff = User::factory()->staff()->create();
    $this->actingAs($this->staff);
});

/**
 * A batch in review with $drafts draft items of mixed types.
 */
function reviewBatch(int $drafts): ContentBatch
{
    $batch = ContentBatch::factory()->inReview($drafts)->create();

    for ($i = 0; $i < $drafts; $i++) {
        $factory = ContentItem::factory()->draft();
        ($i % 2 === 0 ? $factory : $factory->wisdomCard())->create(['content_batch_id' => $batch->id, 'category_id' => $batch->category_id, 'source' => 'ai']);
    }

    return $batch;
}

function pageQueries(string $url): int
{
    test()->get($url)->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->get($url)->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('shows the not-configured state without an API key', function () {
    $this->get('/admin/ai-batch-generator')
        ->assertOk()
        ->assertSee('AI generation is not configured')
        ->assertSee('ANTHROPIC_API_KEY');
});

it('renders the generator form and batch list when configured', function () {
    config(['ai.providers.anthropic.key' => 'test-key']);
    ContentBatch::factory()->create(['status' => ContentBatch::STATUS_FAILED, 'error' => 'Provider error: overloaded']);
    reviewBatch(2);

    $this->get('/admin/ai-batch-generator')
        ->assertOk()
        ->assertDontSee('AI generation is not configured')
        ->assertSee('Generate drafts')
        ->assertSee('Word puzzle: anagram')
        ->assertSee('review')
        ->assertSee('failed');
});

it('queues a batch from the form', function () {
    config(['ai.providers.anthropic.key' => 'test-key']);
    Queue::fake();
    $category = Category::factory()->create();

    Livewire::test(AiBatchGenerator::class)
        ->fillForm([
            'category_id' => $category->id, 'generation_type' => 'wisdom_card', 'locale' => 'fr',
            'min_difficulty' => 1, 'max_difficulty' => 3, 'min_age' => 6, 'max_age' => 12, 'count' => 12, 'note' => 'Kindness',
        ])
        ->call('generate')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $batch = ContentBatch::query()->sole();
    expect($batch->type_key)->toBe('wisdom_card')
        ->and($batch->locale)->toBe('fr')
        ->and($batch->requested_count)->toBe(12)
        ->and($batch->created_by)->toBe($this->staff->id);
    Queue::assertPushed(GenerateContentBatchJob::class);
});

it('validates the form (max 50 items)', function () {
    config(['ai.providers.anthropic.key' => 'test-key']);
    Queue::fake();

    Livewire::test(AiBatchGenerator::class)
        ->fillForm(['category_id' => Category::factory()->create()->id, 'count' => 51])
        ->call('generate')
        ->assertHasFormErrors(['count']);

    Queue::assertNothingPushed();
});

it('retries a failed batch', function () {
    config(['ai.providers.anthropic.key' => 'test-key']);
    Queue::fake();
    $batch = ContentBatch::factory()->create(['status' => ContentBatch::STATUS_FAILED, 'error' => 'boom']);

    Livewire::test(AiBatchGenerator::class)->callTableAction('retry', $batch);

    expect($batch->refresh()->status)->toBe(ContentBatch::STATUS_QUEUED);
    Queue::assertPushed(GenerateContentBatchJob::class, fn ($job) => $job->batchId === $batch->id);
});

it('renders the review page with a preview per draft', function () {
    $batch = reviewBatch(2);

    $this->get('/admin/ai-batch-review?batch='.$batch->id)
        ->assertOk()
        ->assertSee('Review batch #'.$batch->id)
        ->assertSee('Little strokes fell great oaks.')
        ->assertSee('Green border = correct answer', false)
        ->assertSee('Reviewer checklist')
        ->assertSee('No current politics or politicians');

    $this->get('/admin/ai-batch-review?batch=999999')->assertNotFound();
});

it('approves, rejects and bulk-approves drafts from the review page', function () {
    $batch = reviewBatch(4);
    [$first, $second, $third, $fourth] = $batch->items()->orderBy('id')->get()->all();

    Livewire::test(AiBatchReview::class, ['batch' => $batch->id])
        ->callTableAction('approve', $first)
        ->callTableAction('reject', $second)
        ->callTableBulkAction('approve_selected', [$third, $fourth]);

    expect($first->refresh()->status)->toBe(ContentItem::STATUS_PUBLISHED)
        ->and($first->reviewed_by)->toBe($this->staff->id)
        ->and($second->refresh()->status)->toBe(ContentItem::STATUS_ARCHIVED)
        ->and($fourth->refresh()->status)->toBe(ContentItem::STATUS_PUBLISHED)
        ->and($batch->refresh()->status)->toBe(ContentBatch::STATUS_DONE);
});

it('approves all remaining drafts from the header action', function () {
    $batch = reviewBatch(3);

    Livewire::test(AiBatchReview::class, ['batch' => $batch->id])->callAction('approve_all');

    expect($batch->items()->where('status', ContentItem::STATUS_PUBLISHED)->count())->toBe(3)
        ->and($batch->refresh()->status)->toBe(ContentBatch::STATUS_DONE);
});

it('keeps the batch table at a constant number of queries', function () {
    reviewBatch(1);
    ContentBatch::factory()->create();
    $small = pageQueries('/admin/ai-batch-generator');

    foreach (range(1, 5) as $i) {
        reviewBatch(2);
        ContentBatch::factory()->create(['status' => ContentBatch::STATUS_FAILED]);
    }

    expect(pageQueries('/admin/ai-batch-generator'))->toBe($small);
});

it('keeps the review list at a constant number of queries', function () {
    $small = pageQueries('/admin/ai-batch-review?batch='.reviewBatch(2)->id);
    $large = pageQueries('/admin/ai-batch-review?batch='.reviewBatch(8)->id);

    expect($large)->toBe($small);
});
