<?php

namespace App\Modules\Content\Actions;

use App\Modules\ChallengeTypes\ChallengeTypeRegistry;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Content\Support\CategoryTree;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Policy\PolicyResolver;

/**
 * The category tree visible to a member (docs/API.md "Content"), with names in the member's
 * locale, `is_locked` on top-level categories beyond the tier limit and the challenge types
 * that have published content (or a generator, for Sudoku and Math Sprint) in each subtree.
 */
class BuildCategoryTree
{
    public function __construct(
        private readonly CategoryTree $tree,
        private readonly PolicyResolver $policy,
        private readonly ResolveCategoryAccess $access,
        private readonly ChallengeTypeRegistry $registry,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(FamilyMember $member): array
    {
        $age = $this->policy->memberAge($member);
        $locale = $member->user->locale;
        $locked = $this->access->lockedRootIds($member);
        $typesByCategory = $this->typesByCategory($age);

        $nodes = [];

        foreach ($this->tree->childrenOf(null) as $root) {
            $node = $this->node($root, $age, $locale, $typesByCategory);

            if ($node !== null) {
                $node['is_locked'] = in_array($root->id, $locked, true);
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * @param  array<int, list<string>>  $typesByCategory
     * @return array<string, mixed>|null
     */
    private function node(Category $category, ?int $age, string $locale, array $typesByCategory): ?array
    {
        if (! $category->is_active || ! $category->allowsAge($age)) {
            return null;
        }

        $children = [];
        $types = $typesByCategory[$category->id] ?? [];

        foreach ($category->generatorSpecs() as $spec) {
            if ($this->registry->isActive($spec->type)) {
                $types[] = $spec->type;
            }
        }

        foreach ($this->tree->childrenOf($category->id) as $child) {
            $childNode = $this->node($child, $age, $locale, $typesByCategory);

            if ($childNode !== null) {
                $children[] = $childNode;
                $types = array_merge($types, $childNode['challenge_types']);
            }
        }

        $types = array_values(array_unique($types));
        sort($types);

        return [
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => $category->localizedName($locale),
            'icon' => $category->icon,
            'parent_id' => $category->parent_id,
            'min_age' => $category->min_age,
            'max_age' => $category->max_age,
            'is_locked' => false,
            'challenge_types' => $types,
            'children' => $children,
        ];
    }

    /**
     * Active challenge types with published, age-appropriate content directly in each category.
     *
     * @return array<int, list<string>>
     */
    private function typesByCategory(?int $age): array
    {
        $active = $this->registry->activeKeys();
        $map = [];

        ContentItem::query()
            ->published()
            ->forAge($age)
            ->whereIn('type_key', $active)
            ->select(['category_id', 'type_key'])
            ->distinct()
            ->get()
            ->each(function (ContentItem $row) use (&$map): void {
                $map[$row->category_id][] = $row->type_key;
            });

        return $map;
    }
}
