<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Models\ContentBatch;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Review decision on AI drafts of a batch. Approve = publish through ChangeContentStatus (the
 * handler re-validates; reviewed_by set). Reject = archive (kept, so the generator never
 * produces the same item again), reviewed_by set. When no drafts remain the batch is `done`.
 */
class ReviewContentBatchItems
{
    public const string APPROVE = 'approve';

    public const string REJECT = 'reject';

    public function __construct(private readonly ChangeContentStatus $changeStatus) {}

    /**
     * @param  iterable<ContentItem>  $items
     * @return array{changed: int, skipped: list<int>}
     */
    public function handle(ContentBatch $batch, iterable $items, string $decision, User $reviewer): array
    {
        $items = Collection::make($items)
            ->filter(fn (ContentItem $item) => $item->content_batch_id === $batch->id && $item->status === ContentItem::STATUS_DRAFT)
            ->values();

        $result = match ($decision) {
            self::APPROVE => $this->changeStatus->handle($items, ContentItem::STATUS_PUBLISHED, $reviewer),
            self::REJECT => $this->reject($items, $reviewer),
            default => throw new InvalidArgumentException("Unknown review decision [{$decision}]."),
        };

        if ($batch->status === ContentBatch::STATUS_REVIEW && ! $batch->draftItems()->exists()) {
            $batch->forceFill(['status' => ContentBatch::STATUS_DONE])->save();
        }

        return $result;
    }

    /**
     * @param  Collection<int, ContentItem>  $items
     * @return array{changed: int, skipped: list<int>}
     */
    private function reject(Collection $items, User $reviewer): array
    {
        foreach ($items as $item) {
            $item->forceFill(['status' => ContentItem::STATUS_ARCHIVED, 'reviewed_by' => $reviewer->id])->save();
        }

        return ['changed' => $items->count(), 'skipped' => []];
    }
}
