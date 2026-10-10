<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Support\CategoryTree;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Policy\PolicyResolver;

/**
 * Which categories a member may see and play: active, age range contains the member's age
 * (for the node and all its ancestors), and — on tiers with a category limit — the root
 * category is among the first `limits.max_categories` visible top-level categories.
 */
class ResolveCategoryAccess
{
    public function __construct(
        private readonly CategoryTree $tree,
        private readonly PolicyResolver $policy,
    ) {}

    /**
     * Ids of visible top-level categories that are locked for this member's tier.
     *
     * @return list<int>
     */
    public function lockedRootIds(FamilyMember $member): array
    {
        $age = $this->policy->memberAge($member);
        $limit = $this->policy->maxCategories($member->family);

        $visibleRoots = $this->tree->childrenOf(null)
            ->filter(fn ($root) => $this->tree->isVisible($root->id, $age))
            ->values();

        return $visibleRoots->slice($limit)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    public function isVisible(FamilyMember $member, int $categoryId): bool
    {
        return $this->tree->isVisible($categoryId, $this->policy->memberAge($member));
    }

    public function canPlay(FamilyMember $member, int $categoryId): bool
    {
        if (! $this->isVisible($member, $categoryId)) {
            return false;
        }

        $root = $this->tree->rootOf($categoryId);

        return $root !== null && ! in_array($root->id, $this->lockedRootIds($member), true);
    }
}
