<?php

use App\Filament\Resources\AgeBands\Pages\EditAgeBand;
use App\Filament\Resources\ContentItems\Pages\EditContentItem;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Modules\ChallengeTypes\Generators\WordSearchBuilder;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Family\Models\Invite;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\Models\AgeBand;
use App\Modules\Policy\Models\Setting;
use Livewire\Livewire;

it('keeps non-staff users out of the admin', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});

it('renders every admin page for staff', function (string $url) {
    $owner = familyOwner(['tier' => 'family']);
    addMember($owner->family, 9);
    Invite::factory()->create(['family_id' => $owner->family_id]);
    $item = ContentItem::factory()->create();
    GoalPlan::factory()->create(['family_id' => $owner->family_id])->categories()->attach($item->category_id);

    $url = str_replace(
        ['{family}', '{ageBand}', '{setting}', '{item}', '{category}', '{plan}'],
        [(string) $owner->family_id, (string) AgeBand::query()->value('id'), (string) Setting::query()->value('id'), (string) $item->id, (string) $item->category_id, (string) GoalPlan::query()->value('id')],
        $url,
    );

    $this->actingAs(User::factory()->staff()->create())->get($url)->assertOk();
})->with([
    '/admin',
    '/admin/age-bands',
    '/admin/age-bands/create',
    '/admin/age-bands/{ageBand}/edit',
    '/admin/settings',
    '/admin/families',
    '/admin/families/{family}',
    '/admin/families/{family}/edit',
    '/admin/activities',
    '/admin/categories',
    '/admin/categories/create',
    '/admin/categories/{category}/edit',
    '/admin/challenge-types',
    '/admin/content-items',
    '/admin/content-items/create',
    '/admin/content-items/{item}/edit',
    '/admin/tags',
    '/admin/content-batches',
    '/admin/content-batches/create',
    '/admin/content-preview',
    '/admin/content-preview?item={item}&band=teen',
    '/admin/goal-plans',
    '/admin/goal-plans/{plan}',
]);

it('logs admin changes to the audit log', function () {
    $staff = User::factory()->staff()->create();
    $this->actingAs($staff);

    AgeBand::query()->where('code', 'teen')->firstOrFail()->update(['name' => 'Teenager']);

    $this->assertDatabaseHas('activity_log', ['log_name' => 'admin', 'description' => 'updated', 'causer_id' => $staff->id]);
});

it('previews B7 types: word puzzles, generated samples and category generators', function () {
    $staff = User::factory()->staff()->create();
    $built = (new WordSearchBuilder)->build(['CAT', 'DOG'], 6, ['right', 'down'], 'admin');
    $search = ContentItem::factory()->create(['type_key' => 'word_puzzle', 'payload' => ['variant' => 'word_search', 'grid' => $built['grid'], 'words' => ['CAT', 'DOG']], 'answer' => ['found' => $built['found']]]);
    $anagram = ContentItem::factory()->create(['type_key' => 'word_puzzle', 'payload' => ['variant' => 'anagram', 'letters' => ['T', 'A', 'C'], 'hint' => 'An animal'], 'answer' => ['words' => ['CAT']]]);
    $category = Category::factory()->create(['generators' => [['type' => 'sudoku', 'options' => ['size' => 6], 'min_difficulty' => 3, 'max_difficulty' => 6]]]);

    $this->actingAs($staff)->get("/admin/content-preview?item={$search->id}")->assertOk()->assertSee('3 points');
    $this->actingAs($staff)->get("/admin/content-preview?item={$anagram->id}")->assertOk()->assertSee('An animal');
    $this->actingAs($staff)->get('/admin/content-preview?sample=sudoku-9&difficulty=7&seed=x')->assertOk()->assertSee('generated sample')->assertSee('8 points');
    $this->actingAs($staff)->get('/admin/content-preview?sample=math-fractions&difficulty=6')->assertOk()->assertSee('fraction');
    $this->actingAs($staff)->get("/admin/categories/{$category->id}/edit")->assertOk();
    $this->actingAs($staff)->get('/admin/categories')->assertOk()->assertSee('sudoku 6');
});

it('edits a band\'s ad request configuration', function () {
    $this->actingAs(User::factory()->staff()->create());
    $teen = AgeBand::query()->where('code', 'teen')->firstOrFail();

    Livewire::test(EditAgeBand::class, ['record' => $teen->getRouteKey()])
        ->assertFormSet(['policy.ad_request.under_age_of_consent' => true, 'policy.ad_request.max_ad_content_rating' => 'G'])
        ->fillForm(['policy.ad_request.max_ad_content_rating' => 'PG', 'policy.ad_request.non_personalized' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($teen->fresh()->rule('ad_request'))->toBe(['non_personalized' => false, 'under_age_of_consent' => true, 'max_ad_content_rating' => 'PG']);
});

it('shows the translation group read-only and toggles cross_locale on a content item', function () {
    $item = ContentItem::factory()->create(['translation_group' => 'q:flags-001', 'locale' => 'en']);
    $this->actingAs(User::factory()->staff()->create());

    Livewire::test(EditContentItem::class, ['record' => $item->getRouteKey()])
        ->assertFormSet(['translation_group' => 'q:flags-001', 'cross_locale' => true])
        ->assertFormFieldIsDisabled('translation_group')
        ->fillForm(['cross_locale' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($item->refresh()->cross_locale)->toBeFalse()->and($item->translation_group)->toBe('q:flags-001');
});

it('filters content items by locale', function () {
    $en = ContentItem::factory()->create(['locale' => 'en']);
    $fr = ContentItem::factory()->create(['locale' => 'fr']);
    $this->actingAs(User::factory()->staff()->create());

    Livewire::test(ListContentItems::class)
        ->filterTable('locale', 'fr')
        ->assertCanSeeTableRecords([$fr])
        ->assertCanNotSeeTableRecords([$en]);
});
