<?php

namespace App\Modules\Play\Actions;

use App\Modules\ChallengeTypes\ChallengeTypeRegistry;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Content\Support\CategoryTree;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Chooses content for a member: published items of an active type in the category subtree,
 * suitable for the member's age and locale, inside their difficulty range, least-seen first.
 * When the range runs dry it falls back to the nearest difficulties, and (when allowed) to
 * repeats, so a session can always continue.
 */
class PickContentItems
{
    /**
     * Distance between an item's difficulty and a target, without ever subtracting a larger
     * value from the column: `difficulty` is UNSIGNED on MySQL, where `difficulty - 5` with
     * difficulty 2 overflows (SQLSTATE 22003) instead of going negative. Portable (MySQL, SQLite).
     */
    public const string DIFFICULTY_DISTANCE_SQL = 'CASE WHEN content_items.difficulty >= ? THEN content_items.difficulty - ? ELSE ? - content_items.difficulty END';

    /**
     * @return list<int>
     */
    public static function difficultyDistanceBindings(int $target): array
    {
        return [$target, $target, $target];
    }

    public function __construct(
        private readonly CategoryTree $tree,
        private readonly PolicyResolver $policy,
        private readonly ChallengeTypeRegistry $registry,
    ) {}

    /**
     * @param  list<int>  $excludeIds
     * @return Collection<int, ContentItem>
     */
    public function handle(FamilyMember $member, int $categoryId, int $count, array $excludeIds = [], bool $easier = false, bool $allowRepeats = false): Collection
    {
        if ($count <= 0) {
            return new Collection;
        }

        $difficulty = $this->policy->difficultyForMember($member);
        $picked = new Collection;

        if ($easier) {
            // Redemption round: the easiest items available, least-seen first.
            return $this->query($member, $categoryId, $excludeIds)
                ->orderBy('content_items.difficulty')
                ->orderByRaw('COALESCE(h.times_seen, 0)')
                ->inRandomOrder()
                ->limit($count)
                ->get();
        }

        // 1) Inside the member's difficulty range.
        $picked = $picked->merge(
            $this->query($member, $categoryId, $excludeIds)
                ->whereBetween('content_items.difficulty', [$difficulty['min'], $difficulty['max']])
                ->orderByRaw('COALESCE(h.times_seen, 0)')
                ->inRandomOrder()
                ->limit($count)
                ->get(),
        );

        // 2) Nearest difficulty outside the range.
        if ($picked->count() < $count) {
            $picked = $picked->merge(
                $this->query($member, $categoryId, [...$excludeIds, ...$picked->pluck('id')->all()])
                    ->orderByRaw(self::DIFFICULTY_DISTANCE_SQL, self::difficultyDistanceBindings($difficulty['default']))
                    ->orderByRaw('COALESCE(h.times_seen, 0)')
                    ->inRandomOrder()
                    ->limit($count - $picked->count())
                    ->get(),
            );
        }

        // 3) Repeats of items already in the session.
        if ($allowRepeats && $picked->count() < $count && $picked->count() + count($excludeIds) > 0) {
            $pool = $this->query($member, $categoryId, [])->inRandomOrder()->limit($count)->get();

            while ($picked->count() < $count && $pool->isNotEmpty()) {
                $picked->push($pool->shift());
            }
        }

        return $picked->values();
    }

    /**
     * @param  list<int>  $excludeIds
     * @return Builder<ContentItem>
     */
    private function query(FamilyMember $member, int $categoryId, array $excludeIds): Builder
    {
        $age = $this->policy->memberAge($member);
        $locale = $member->user->locale;

        $query = ContentItem::query()
            ->select('content_items.*')
            ->leftJoin('member_item_history as h', function ($join) use ($member): void {
                $join->on('h.content_item_id', '=', 'content_items.id')->where('h.member_id', '=', $member->id);
            })
            ->where('content_items.status', ContentItem::STATUS_PUBLISHED)
            ->whereIn('content_items.category_id', $this->tree->subtreeIds($categoryId))
            ->whereIn('content_items.type_key', $this->registry->activeKeys())
            ->whereIn('content_items.locale', array_values(array_unique([$locale, 'en'])));

        if ($age !== null) {
            $query->where(fn (Builder $q) => $q->whereNull('content_items.min_age')->orWhere('content_items.min_age', '<=', $age))
                ->where(fn (Builder $q) => $q->whereNull('content_items.max_age')->orWhere('content_items.max_age', '>=', $age));
        }

        if ($excludeIds !== []) {
            $query->whereNotIn('content_items.id', $excludeIds);
        }

        return $query;
    }
}
