<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Generation\BatchPromptBuilder;
use App\Modules\Content\Generation\ContentBatchAgent;
use App\Modules\Content\Generation\ContentFingerprint;
use App\Modules\Content\Generation\GenerationTypes;
use App\Modules\Content\Models\ContentBatch;
use App\Modules\Content\Models\ContentItem;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Runs one AI batch: builds the prompt, asks the model for structured output, validates each
 * item through its challenge type handler (invalid items are dropped), skips duplicates of
 * existing items (normalized question / card text / word, and `source_ref`), and stores the rest
 * as `draft` items of the batch. Status: queued → running → review, or failed (provider error,
 * unusable answer, nothing usable). Called by GenerateContentBatchJob on Horizon.
 */
class GenerateContentBatch
{
    public function __construct(
        private readonly BatchPromptBuilder $prompts,
        private readonly GenerationTypes $types,
        private readonly SaveContentItem $save,
    ) {}

    public function handle(ContentBatch $batch): ContentBatch
    {
        if (! in_array($batch->status, [ContentBatch::STATUS_QUEUED, ContentBatch::STATUS_FAILED], true)) {
            return $batch;
        }

        $key = GenerationTypes::key($batch->type_key, $batch->variant);
        $instructions = $this->prompts->instructions();
        $prompt = $this->prompts->prompt($batch);

        $batch->fill([
            'status' => ContentBatch::STATUS_RUNNING,
            'prompt' => $instructions."\n\n---\n\n".$prompt,
            'model' => (string) config('content_generator.model'),
            'error' => null,
            'started_at' => now(),
            'finished_at' => null,
        ])->save();

        try {
            $response = (new ContentBatchAgent($key, $instructions, $this->types))->prompt($prompt);
        } catch (Throwable $e) {
            return $this->fail($batch, 'Provider error: '.$e->getMessage());
        }

        $batch->input_tokens = $response->usage->inputTokens;
        $batch->output_tokens = $response->usage->outputTokens;
        $batch->model = $response->meta->model ?? $batch->model;

        $items = $response instanceof StructuredAgentResponse ? ($response->structured['items'] ?? null) : null;

        if (! is_array($items)) {
            return $this->fail($batch, 'The model did not return the expected JSON ({"items": [...]}).');
        }

        $result = $this->store($batch, $key, array_values($items));

        $batch->fill([
            'item_count' => $result['stored'],
            'skipped' => ['invalid' => $result['invalid'], 'duplicates' => $result['duplicates']],
        ]);

        if ($result['stored'] === 0) {
            return $this->fail($batch, 'No usable items: '.count($result['invalid']).' invalid, '.count($result['duplicates']).' duplicates.');
        }

        $batch->fill(['status' => ContentBatch::STATUS_REVIEW, 'finished_at' => now()])->save();

        return $batch;
    }

    /**
     * @param  list<mixed>  $items
     * @return array{stored: int, invalid: list<array{item: int, errors: list<string>}>, duplicates: list<string>}
     */
    private function store(ContentBatch $batch, string $key, array $items): array
    {
        $known = $this->existingFingerprints($batch);
        $minDifficulty = $batch->min_difficulty ?? 1;
        $maxDifficulty = max($minDifficulty, $batch->max_difficulty ?? 10);
        $limit = $batch->requested_count ?? count($items);
        $stored = 0;
        $invalid = [];
        $duplicates = [];

        foreach ($items as $index => $raw) {
            if ($stored >= $limit) {
                break;
            }

            if (! is_array($raw)) {
                $invalid[] = ['item' => $index + 1, 'errors' => ['Not an object.']];

                continue;
            }

            $difficulty = is_numeric($raw['difficulty'] ?? null) ? (int) $raw['difficulty'] : $minDifficulty;
            $difficulty = max($minDifficulty, min($maxDifficulty, $difficulty));
            $built = $this->types->build($key, $raw, $difficulty);

            if (array_is_list($built)) {
                $invalid[] = ['item' => $index + 1, 'errors' => $built];

                continue;
            }

            $fingerprint = ContentFingerprint::of($batch->type_key, $built['payload'], $built['answer']);

            if ($fingerprint === null) {
                $invalid[] = ['item' => $index + 1, 'errors' => ['Empty question / text.']];

                continue;
            }

            if (isset($known[$fingerprint])) {
                $duplicates[] = mb_strimwidth(ContentFingerprint::text($batch->type_key, $built['payload'], $built['answer']), 0, 120, '…');

                continue;
            }

            try {
                $this->save->handle([
                    'category_id' => $batch->category_id,
                    'type_key' => $batch->type_key,
                    'locale' => $batch->locale,
                    'difficulty' => $difficulty,
                    'min_age' => $batch->min_age,
                    'max_age' => $batch->max_age,
                    'payload' => $built['payload'],
                    'answer' => $built['answer'],
                    'status' => ContentItem::STATUS_DRAFT,
                    'source' => 'ai',
                    'source_ref' => ContentFingerprint::sourceRef($fingerprint, $batch->locale),
                    'content_batch_id' => $batch->id,
                ]);
            } catch (ValidationException $e) {
                $invalid[] = ['item' => $index + 1, 'errors' => array_values(array_map('strval', $e->validator->errors()->all()))];

                continue;
            } catch (UniqueConstraintViolationException) {
                $duplicates[] = mb_strimwidth(ContentFingerprint::text($batch->type_key, $built['payload'], $built['answer']), 0, 120, '…');

                continue;
            }

            $known[$fingerprint] = true;
            $stored++;
        }

        return ['stored' => $stored, 'invalid' => $invalid, 'duplicates' => $duplicates];
    }

    /**
     * Fingerprints of every item of this type and locale (any category, any status, so
     * rejected items are not generated again).
     *
     * @return array<string, true>
     */
    private function existingFingerprints(ContentBatch $batch): array
    {
        $known = [];

        ContentItem::query()
            ->select(['id', 'type_key', 'payload', 'answer'])
            ->where('type_key', $batch->type_key)
            ->where('locale', $batch->locale)
            ->lazyById(500)
            ->each(function (ContentItem $item) use (&$known): void {
                $fingerprint = ContentFingerprint::of($item->type_key, $item->payload, $item->answer);

                if ($fingerprint !== null) {
                    $known[$fingerprint] = true;
                }
            });

        return $known;
    }

    private function fail(ContentBatch $batch, string $error): ContentBatch
    {
        $batch->fill([
            'status' => ContentBatch::STATUS_FAILED,
            'error' => mb_substr($error, 0, 2000),
            'finished_at' => now(),
        ])->save();

        return $batch;
    }
}
