<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Models\ContentItem;
use App\Modules\Content\Models\Tag;

/**
 * Bulk retag: adds tags (by name, created when missing) to items, optionally replacing
 * their existing tags.
 */
class TagContentItems
{
    /**
     * @param  iterable<ContentItem>  $items
     * @param  list<string>  $tagNames
     */
    public function handle(iterable $items, array $tagNames, bool $replace = false): void
    {
        $tagIds = collect($tagNames)
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->map(fn (string $name) => Tag::query()->firstOrCreate(['name' => $name])->id)
            ->all();

        foreach ($items as $item) {
            $replace ? $item->tags()->sync($tagIds) : $item->tags()->syncWithoutDetaching($tagIds);
        }
    }
}
