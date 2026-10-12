<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Generation\ContentBatchAgent;
use App\Modules\Content\Generation\GenerationTypes;
use App\Modules\Content\Jobs\GenerateContentBatchJob;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentBatch;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Records an AI batch request (status `queued`) and dispatches the generation job to Horizon.
 */
class QueueContentBatch
{
    public function __construct(private readonly GenerationTypes $types) {}

    /**
     * @param  array<string, mixed>  $data  category_id, generation_type, locale, min_difficulty, max_difficulty, min_age, max_age, count, note
     *
     * @throws ValidationException
     */
    public function handle(array $data, ?User $creator = null): ContentBatch
    {
        if (! ContentBatchAgent::isConfigured()) {
            throw ValidationException::withMessages(['generation_type' => 'AI generation is not configured (no provider API key).']);
        }

        /** @var array{category_id: int, generation_type: string, locale: string, min_difficulty: int, max_difficulty: int, min_age: int|null, max_age: int|null, count: int, note: string|null} $valid */
        $valid = Validator::make($data, [
            'category_id' => ['required', 'integer', Rule::exists(Category::class, 'id')],
            'generation_type' => ['required', 'string', Rule::in(array_keys($this->types->options()))],
            'locale' => ['required', 'string', Rule::in(['en', 'fr'])],
            'min_difficulty' => ['required', 'integer', 'between:1,10'],
            'max_difficulty' => ['required', 'integer', 'between:1,10', 'gte:min_difficulty'],
            'min_age' => ['nullable', 'integer', 'between:0,120'],
            'max_age' => ['nullable', 'integer', 'between:0,120', 'gte:min_age'],
            'count' => ['required', 'integer', 'between:1,'.(int) config('content_generator.max_items', 50)],
            'note' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        [$typeKey, $variant] = GenerationTypes::split($valid['generation_type']);

        $batch = ContentBatch::query()->create([
            'category_id' => (int) $valid['category_id'],
            'type_key' => $typeKey,
            'variant' => $variant,
            'locale' => $valid['locale'],
            'min_difficulty' => (int) $valid['min_difficulty'],
            'max_difficulty' => (int) $valid['max_difficulty'],
            'min_age' => $valid['min_age'] ?? null,
            'max_age' => $valid['max_age'] ?? null,
            'requested_count' => (int) $valid['count'],
            'note' => filled($valid['note'] ?? null) ? trim((string) $valid['note']) : null,
            'model' => (string) config('content_generator.model'),
            'status' => ContentBatch::STATUS_QUEUED,
            'created_by' => $creator?->id,
        ]);

        GenerateContentBatchJob::dispatch($batch->id);

        return $batch;
    }
}
