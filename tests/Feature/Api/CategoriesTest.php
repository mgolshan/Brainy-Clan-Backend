<?php

use App\Modules\ChallengeTypes\Handlers\WisdomCard;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ChallengeType;
use App\Modules\Content\Models\ContentItem;

function makeRoots(int $count): array
{
    return collect(range(1, $count))->map(fn ($i) => Category::factory()->create(['sort' => $i * 10, 'slug' => "root-{$i}", 'name' => ['en' => "Root {$i}", 'fr' => "Racine {$i}"]]))->all();
}

it('needs auth and a family', function () {
    $this->getJson('/api/v1/categories')->assertStatus(401);
});

it('returns the visible tree with names, types and children', function () {
    [$geo] = makeRoots(1);
    $capitals = Category::factory()->childOf($geo)->create(['slug' => 'caps', 'name' => ['en' => 'Capitals']]);
    ContentItem::factory()->create(['category_id' => $capitals->id]);
    ContentItem::factory()->wisdomCard()->create(['category_id' => $geo->id]);
    ContentItem::factory()->wisdomCard()->draft()->create(['category_id' => $capitals->id]);

    actingAsMember(familyOwner());

    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'root-1')
        ->assertJsonPath('data.0.name', 'Root 1')
        ->assertJsonPath('data.0.parent_id', null)
        ->assertJsonPath('data.0.is_locked', false)
        ->assertJsonPath('data.0.challenge_types', ['multiple_choice', 'wisdom_card'])
        ->assertJsonPath('data.0.children.0.slug', 'caps')
        ->assertJsonPath('data.0.children.0.parent_id', $geo->id)
        ->assertJsonPath('data.0.children.0.challenge_types', ['multiple_choice'])
        ->assertJsonPath('data.0.children.0.children', []);
});

it('localizes names with an English fallback', function () {
    makeRoots(1);
    $owner = familyOwner();
    $owner->user->update(['locale' => 'fr']);
    actingAsMember($owner);

    $this->getJson('/api/v1/categories')->assertJsonPath('data.0.name', 'Racine 1');
});

it('hides inactive and age-inappropriate categories (and their children)', function () {
    [$a, $b, $c] = makeRoots(3);
    $b->update(['is_active' => false]);
    $c->update(['min_age' => 12]);
    Category::factory()->childOf($a)->create(['max_age' => 8, 'slug' => 'little']);
    Category::factory()->childOf($a)->create(['slug' => 'all-ages']);

    $owner = familyOwner(['tier' => 'family']);
    actingAsMember(addMember($owner->family, 9));

    $response = $this->getJson('/api/v1/categories')->assertOk()->assertJsonCount(1, 'data');
    expect(collect($response->json('data.0.children'))->pluck('slug')->all())->toBe(['all-ages']);
});

it('locks top-level categories beyond the free tier limit', function () {
    makeRoots(5);
    actingAsMember(familyOwner());

    $locked = collect($this->getJson('/api/v1/categories')->json('data'))->pluck('is_locked', 'slug')->all();

    expect($locked)->toBe(['root-1' => false, 'root-2' => false, 'root-3' => false, 'root-4' => true, 'root-5' => true]);
});

it('locks nothing on the family plan', function () {
    makeRoots(5);
    actingAsMember(familyOwner(['tier' => 'family']));

    expect(collect($this->getJson('/api/v1/categories')->json('data'))->where('is_locked', true))->toHaveCount(0);
});

it('omits challenge types that are switched off', function () {
    [$root] = makeRoots(1);
    ContentItem::factory()->wisdomCard()->create(['category_id' => $root->id]);
    ChallengeType::query()->create(['key' => 'wisdom_card', 'name' => 'Wisdom', 'handler_class' => WisdomCard::class, 'is_active' => false]);
    actingAsMember(familyOwner());

    $this->getJson('/api/v1/categories')->assertJsonPath('data.0.challenge_types', []);
});
