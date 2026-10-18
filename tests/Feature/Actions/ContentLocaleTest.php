<?php

use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Play\Actions\PickContentItems;
use Illuminate\Support\Facades\DB;

/**
 * Content locale (docs/PLAN.md "Launch question bank and French content"): a member gets items in
 * their locale and the English item only when no translation of it is published.
 */
function memberWithLocale(string $locale): FamilyMember
{
    $member = familyOwner(['tier' => 'family']);
    $member->user->forceFill(['locale' => $locale])->save();

    return $member->load('user');
}

/**
 * @return list<int>
 */
function pickedIds(FamilyMember $member, Category $category): array
{
    return app(PickContentItems::class)->handle($member, $category->id, 20)->pluck('id')->sort()->values()->all();
}

beforeEach(function () {
    $this->category = Category::factory()->create();
    $this->item = fn (array $attributes) => ContentItem::factory()->create(['category_id' => $this->category->id, ...$attributes]);
});

it('gives a French member the French version of a translated item, not both', function () {
    ($this->item)(['locale' => 'en', 'translation_group' => 'q:flags-001']);
    $fr = ($this->item)(['locale' => 'fr', 'translation_group' => 'q:flags-001']);

    expect(pickedIds(memberWithLocale('fr'), $this->category))->toBe([$fr->id]);
});

it('still offers an English item without a translation to a French member', function () {
    $en = ($this->item)(['locale' => 'en', 'translation_group' => 'q:flags-002']);
    $ungrouped = ($this->item)(['locale' => 'en', 'translation_group' => null]);

    expect(pickedIds(memberWithLocale('fr'), $this->category))->toBe([$en->id, $ungrouped->id]);
});

it('never offers a language-bound English item to a French member', function () {
    ($this->item)(['locale' => 'en', 'cross_locale' => false, 'translation_group' => 'q:spelling-001']);
    $open = ($this->item)(['locale' => 'en']);

    expect(pickedIds(memberWithLocale('fr'), $this->category))->toBe([$open->id]);
});

it('never offers French items to an English member', function () {
    $en = ($this->item)(['locale' => 'en', 'translation_group' => 'q:flags-003']);
    ($this->item)(['locale' => 'fr', 'translation_group' => 'q:flags-003']);
    ($this->item)(['locale' => 'fr', 'cross_locale' => false]);

    expect(pickedIds(memberWithLocale('en'), $this->category))->toBe([$en->id]);
});

it('keeps the English item while its French translation is not published', function () {
    $en = ($this->item)(['locale' => 'en', 'translation_group' => 'q:flags-004']);
    ContentItem::factory()->draft()->create(['category_id' => $this->category->id, 'locale' => 'fr', 'translation_group' => 'q:flags-004']);

    expect(pickedIds(memberWithLocale('fr'), $this->category))->toBe([$en->id]);
});

it('picks for a French member with a fixed number of queries', function () {
    foreach (range(1, 8) as $n) {
        ($this->item)(['locale' => 'en', 'translation_group' => "q:x-{$n}"]);
        ($this->item)(['locale' => 'fr', 'translation_group' => "q:x-{$n}"]);
    }

    $member = memberWithLocale('fr');
    app(PickContentItems::class)->handle($member, $this->category->id, 3);

    DB::enableQueryLog();
    $picked = app(PickContentItems::class)->handle($member, $this->category->id, 20);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($picked)->toHaveCount(8)
        ->and($picked->pluck('locale')->unique()->all())->toBe(['fr'])
        ->and($queries)->toBeLessThanOrEqual(4);
});

it('shows a category to a French member only when it has content playable in French', function () {
    $root = Category::factory()->create(['slug' => 'locale-root']);
    $bound = Category::factory()->childOf($root)->create();
    ContentItem::factory()->create(['category_id' => $bound->id, 'locale' => 'en', 'cross_locale' => false]);

    actingAsMember(memberWithLocale('fr'));
    $tree = $this->getJson('/api/v1/categories')->assertOk()->json('data');
    $node = collect($tree)->firstWhere('slug', 'locale-root');

    expect($node === null || $node['challenge_types'] === [])->toBeTrue();

    actingAsMember(memberWithLocale('en'));
    $node = collect($this->getJson('/api/v1/categories')->assertOk()->json('data'))->firstWhere('slug', 'locale-root');

    expect($node['challenge_types'])->toBe(['multiple_choice']);
});
