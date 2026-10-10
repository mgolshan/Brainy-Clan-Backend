<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Models\ContentItem;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Bulk publish / archive / send back to draft. Publishing re-validates each item with its
 * challenge type plugin; invalid items are skipped and reported.
 */
class ChangeContentStatus
{
    public function __construct(private readonly SaveContentItem $save) {}

    /**
     * @param  iterable<ContentItem>  $items
     * @return array{changed: int, skipped: list<int>}
     */
    public function handle(iterable $items, string $status, ?User $reviewer = null): array
    {
        $changed = 0;
        $skipped = [];

        foreach (Collection::make($items) as $item) {
            /** @var ContentItem $item */
            if ($status === ContentItem::STATUS_PUBLISHED) {
                try {
                    $this->save->assertValid($item);
                } catch (ValidationException) {
                    $skipped[] = $item->id;

                    continue;
                }

                $item->published_at ??= now();
                $item->reviewed_by = $reviewer !== null ? $reviewer->id : $item->reviewed_by;
            }

            $item->status = $status;
            $item->save();
            $changed++;
        }

        return ['changed' => $changed, 'skipped' => $skipped];
    }
}
