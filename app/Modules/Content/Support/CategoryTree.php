<?php

namespace App\Modules\Content\Support;

use App\Modules\Content\Models\Category;
use App\Modules\Core\Support\ReferenceCache;
use Illuminate\Database\Eloquent\Collection;

/**
 * The whole category tree, loaded once per request/job and cached (ReferenceCache::CATEGORY_TREE)
 * until any category row is written (a few hundred rows at most).
 */
class CategoryTree
{
    /** @var Collection<int, Category>|null */
    private ?Collection $categories = null;

    public static function flush(): void
    {
        ReferenceCache::flush(ReferenceCache::CATEGORY_TREE);

        if (app()->resolved(self::class)) {
            app(self::class)->categories = null;
        }
    }

    /**
     * @return Collection<int, Category> keyed by id, ordered by sort
     */
    public function all(): Collection
    {
        if ($this->categories !== null) {
            return $this->categories;
        }

        /** @var list<array<string, mixed>> $raw */
        $raw = ReferenceCache::remember(ReferenceCache::CATEGORY_TREE, fn (): array => Category::query()
            ->orderBy('sort')->orderBy('id')->get()
            ->map(fn (Category $category) => $category->getAttributes())
            ->all());

        return $this->categories = Category::hydrate($raw)->keyBy('id');
    }

    public function find(int $id): ?Category
    {
        return $this->all()->get($id);
    }

    /**
     * @return Collection<int, Category>
     */
    public function childrenOf(?int $parentId): Collection
    {
        return $this->all()->filter(fn (Category $c) => $c->parent_id === $parentId)->values();
    }

    /**
     * The category id plus all of its descendants.
     *
     * @return list<int>
     */
    public function subtreeIds(int $id): array
    {
        $ids = [$id];

        foreach ($this->childrenOf($id) as $child) {
            array_push($ids, ...$this->subtreeIds($child->id));
        }

        return $ids;
    }

    /**
     * Names from the root down to this category, e.g. ["World Geography", "Capitals", "Europe"].
     *
     * @return list<string>
     */
    public function pathNames(int $id, string $locale = 'en'): array
    {
        $names = [];
        $node = $this->find($id);
        $guard = 0;

        while ($node !== null && $guard++ < 50) {
            array_unshift($names, $node->localizedName($locale));
            $node = $node->parent_id !== null ? $this->find($node->parent_id) : null;
        }

        return $names;
    }

    public function rootOf(int $id): ?Category
    {
        $node = $this->find($id);
        $guard = 0;

        while ($node !== null && $node->parent_id !== null && $guard++ < 50) {
            $node = $this->find($node->parent_id);
        }

        return $node;
    }

    /**
     * Active and age-appropriate, including every ancestor.
     */
    public function isVisible(int $id, ?int $age): bool
    {
        $node = $this->find($id);
        $guard = 0;

        while ($node !== null && $guard++ < 50) {
            if (! $node->is_active || ! $node->allowsAge($age)) {
                return false;
            }

            if ($node->parent_id === null) {
                return true;
            }

            $node = $this->find($node->parent_id);
        }

        return false;
    }
}
