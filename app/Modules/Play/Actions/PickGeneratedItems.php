<?php

namespace App\Modules\Play\Actions;

use App\Modules\ChallengeTypes\ChallengeTypeRegistry;
use App\Modules\ChallengeTypes\Contracts\GeneratesItems;
use App\Modules\ChallengeTypes\Support\GeneratorSpec;
use App\Modules\Content\Support\CategoryTree;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Policy\PolicyResolver;

/**
 * Generated items for a session: which generators a category subtree offers to this member,
 * and building one item from a fresh seed.
 *
 * A generator is offered when its category is visible to the member (age), its type is active,
 * and its difficulty range overlaps the member's policy range. When none overlaps (e.g. a child
 * opening the 9×9 scope directly) the nearest generators are used, like content falls back to
 * the nearest difficulty. The item's difficulty is random inside the overlap (the easiest for a
 * redemption round).
 */
class PickGeneratedItems
{
    public function __construct(
        private readonly CategoryTree $tree,
        private readonly PolicyResolver $policy,
        private readonly ChallengeTypeRegistry $registry,
    ) {}

    /**
     * @return list<GeneratorSpec>
     */
    public function specs(FamilyMember $member, int $categoryId): array
    {
        $age = $this->policy->memberAge($member);
        $difficulty = $this->policy->difficultyForMember($member);
        $candidates = [];

        foreach ($this->tree->subtreeIds($categoryId) as $id) {
            $category = $this->tree->find($id);

            if ($category === null || ! $this->tree->isVisible($id, $age)) {
                continue;
            }

            foreach ($category->generatorSpecs() as $spec) {
                if ($this->registry->isActive($spec->type) && $this->registry->get($spec->type) instanceof GeneratesItems) {
                    $candidates[] = $spec;
                }
            }
        }

        if ($candidates === []) {
            return [];
        }

        $distances = array_map(fn (GeneratorSpec $spec) => $spec->distanceTo($difficulty['min'], $difficulty['max']), $candidates);
        $nearest = min($distances);

        return array_values(array_filter($candidates, fn (GeneratorSpec $spec, int $i) => $distances[$i] === $nearest, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * The difficulty to generate an item at: random inside the overlap of the spec and the
     * member's range (the easiest for a redemption round).
     */
    public function level(GeneratorSpec $spec, FamilyMember $member, bool $easier = false): int
    {
        $difficulty = $this->policy->difficultyForMember($member);
        [$low, $high] = $spec->rangeFor($difficulty['min'], $difficulty['max']);

        return $easier ? $low : random_int($low, $high);
    }

    public function points(GeneratorSpec $spec, int $level): int
    {
        return max(1, $this->generator($spec)->pointsFor($spec->options, $level));
    }

    /**
     * Builds one item at $level (see level()) from a fresh seed, in the member's language.
     *
     * @return array{type_key: string, category_id: int, seed: string, difficulty: int, points: int, payload: array<string, mixed>, answer: array<string, mixed>}
     */
    public function build(GeneratorSpec $spec, FamilyMember $member, int $level): array
    {
        $seed = bin2hex(random_bytes(8));
        $generator = $this->generator($spec);
        $item = $generator->generate($seed, $level, [...$spec->options, 'locale' => $member->user->locale]);

        return [
            'type_key' => $spec->type,
            'category_id' => $spec->categoryId,
            'seed' => $seed,
            'difficulty' => $level,
            'points' => $this->points($spec, $level),
            'payload' => $item['payload'],
            'answer' => $item['answer'],
        ];
    }

    private function generator(GeneratorSpec $spec): GeneratesItems
    {
        $handler = $this->registry->get($spec->type);

        if (! $handler instanceof GeneratesItems) {
            throw new \LogicException("Challenge type [{$spec->type}] does not generate items.");
        }

        return $handler;
    }
}
