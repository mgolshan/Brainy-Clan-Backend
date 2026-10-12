<?php

namespace App\Modules\Content\Jobs;

use App\Modules\Content\Actions\GenerateContentBatch;
use App\Modules\Content\Models\ContentBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs one AI batch on Horizon (`ai` queue, long timeout, never retried automatically: a
 * retry would pay for the model call twice). Re-run a failed batch from the admin instead.
 */
class GenerateContentBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 420;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $batchId)
    {
        $this->onConnection((string) config('content_generator.queue_connection', 'redis-ai'));
        $this->onQueue((string) config('content_generator.queue', 'ai'));
    }

    public function handle(GenerateContentBatch $generate): void
    {
        $batch = ContentBatch::query()->find($this->batchId);

        if ($batch !== null) {
            $generate->handle($batch);
        }
    }

    /**
     * The worker was killed (timeout, crash): mark the batch failed so it does not stay "running".
     */
    public function failed(?Throwable $exception): void
    {
        ContentBatch::query()
            ->whereKey($this->batchId)
            ->whereIn('status', [ContentBatch::STATUS_QUEUED, ContentBatch::STATUS_RUNNING])
            ->update([
                'status' => ContentBatch::STATUS_FAILED,
                'error' => mb_substr('Job failed: '.($exception?->getMessage() ?? 'unknown error'), 0, 2000),
                'finished_at' => now(),
            ]);
    }
}
