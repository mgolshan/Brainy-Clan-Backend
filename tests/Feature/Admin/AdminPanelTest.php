<?php

use App\Modules\Content\Models\ContentItem;
use App\Modules\Family\Models\Invite;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\Models\AgeBand;
use App\Modules\Policy\Models\Setting;

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
        ['{family}', '{ageBand}', '{setting}', '{item}', '{category}'],
        [(string) $owner->family_id, (string) AgeBand::query()->value('id'), (string) Setting::query()->value('id'), (string) $item->id, (string) $item->category_id],
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
]);

it('logs admin changes to the audit log', function () {
    $staff = User::factory()->staff()->create();
    $this->actingAs($staff);

    AgeBand::query()->where('code', 'teen')->firstOrFail()->update(['name' => 'Teenager']);

    $this->assertDatabaseHas('activity_log', ['log_name' => 'admin', 'description' => 'updated', 'causer_id' => $staff->id]);
});
