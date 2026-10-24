<?php

use App\Filament\Pages\AiBatchGenerator;
use App\Filament\Pages\AiBatchReview;
use App\Filament\Resources\AccountDeletionRequests\Pages\ListAccountDeletionRequests;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\ChallengeTypes\Pages\ManageChallengeTypes;
use App\Filament\Resources\ContentBatches\Pages\ListContentBatches;
use App\Filament\Resources\ContentBatches\Pages\ViewContentBatch;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Filament\Resources\Families\Pages\ListFamilies;
use App\Filament\Resources\Families\Pages\ViewFamily;
use App\Filament\Resources\Families\RelationManagers\AllMembersRelationManager;
use App\Filament\Resources\Families\RelationManagers\ConsentsRelationManager;
use App\Filament\Resources\Families\RelationManagers\InvitesRelationManager;
use App\Filament\Resources\GoalPlans\Pages\ManageGoalPlans;
use App\Filament\Resources\GoalPlans\Pages\ViewGoalPlan;
use App\Filament\Resources\GoalPlans\RelationManagers\PeriodsRelationManager;
use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Filament\Resources\ThemeWorlds\Pages\ManageThemeWorlds;
use App\Filament\Resources\WebhookEvents\Pages\ListWebhookEvents;
use App\Filament\Resources\WebhookEvents\Pages\ViewWebhookEvent;
use App\Filament\Support\AdminOnly;
use App\Filament\Support\ReadOnlyForSupport;
use App\Filament\Widgets\BatchesAwaitingReview;
use App\Filament\Widgets\HealthStats;
use App\Modules\Billing\Models\WebhookEvent;
use App\Modules\ChallengeTypes\Handlers\WisdomCard;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ChallengeType;
use App\Modules\Content\Models\ContentBatch;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Content\Models\Tag;
use App\Modules\Family\Models\AccountDeletionRequest;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\Invite;
use App\Modules\Family\Models\SignInCode;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\Models\AgeBand;
use App\Modules\Policy\Models\Setting;
use App\Modules\Policy\Models\ThemeWorld;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/**
 * B11 staff roles (README "Staff roles"): `admin` keeps full access; `support` is read-only —
 * families, members, devices, subscriptions, content, webhooks, deletion requests and the
 * dashboard — with no create/edit/delete, no actions, no AI generator, no settings/age bands,
 * no audit log and no Horizon. Every Filament resource must declare one of the two role traits.
 */

/**
 * A family with a child (device, sign-in code), an adult member, an invite, a goal plan and some
 * content, a webhook event and a deletion request.
 *
 * @return array{owner: FamilyMember, kid: FamilyMember, plan: GoalPlan, item: ContentItem, batch: ContentBatch, event: WebhookEvent, deletion: AccountDeletionRequest}
 */
function staffRolesDataset(): array
{
    $owner = familyOwner(['tier' => 'family']);
    $kid = addMember($owner->family, 9);
    addMember($owner->family, 30);
    Device::factory()->create(['user_id' => $kid->user_id]);
    SignInCode::factory()->create(['member_id' => $kid->id]);
    Invite::factory()->create(['family_id' => $owner->family_id]);

    $item = ContentItem::factory()->create();
    ChallengeType::query()->firstOrCreate(['key' => 'wisdom_card'], ['name' => 'Wisdom', 'handler_class' => WisdomCard::class, 'is_active' => true]);
    Tag::query()->create(['name' => 'staff-roles']);
    $batch = ContentBatch::factory()->create(['category_id' => $item->category_id, 'status' => ContentBatch::STATUS_REVIEW]);
    $plan = GoalPlan::factory()->create(['family_id' => $owner->family_id]);
    $plan->categories()->attach($item->category_id);

    return [
        'owner' => $owner,
        'kid' => $kid,
        'plan' => $plan,
        'item' => $item,
        'batch' => $batch,
        'event' => WebhookEvent::factory()->create(['family_id' => $owner->family_id]),
        'deletion' => AccountDeletionRequest::query()->create(['email_hash' => hash('sha256', 'x'), 'status' => AccountDeletionRequest::STATUS_PENDING, 'expires_at' => now()->addHour()]),
    ];
}

/**
 * Mounts and calls a table action the way the browser would, without the testing helpers'
 * visibility assertion: a hidden (unauthorized) action must not run.
 */
function forceTableAction(mixed $component, string $name, ?Model $record = null): void
{
    $context = $record !== null ? ['table' => true, 'recordKey' => (string) $record->getKey()] : ['table' => true, 'bulk' => true];
    $component->call('mountAction', $name, [], $context);
    $component->call('callMountedAction');
}

/**
 * @param  array<string, mixed>  $data
 */
function staffRolesUrl(string $url, array $data): string
{
    return str_replace(
        ['{family}', '{plan}', '{item}', '{category}', '{batch}', '{event}', '{deletion}', '{ageBand}'],
        [(string) $data['owner']->family_id, (string) $data['plan']->id, (string) $data['item']->id, (string) $data['item']->category_id, (string) $data['batch']->id, (string) $data['event']->id, (string) $data['deletion']->id, (string) AgeBand::query()->value('id')],
        $url,
    );
}

it('gives every Filament resource a staff role rule', function () {
    $resources = Filament::getPanel('admin')->getResources();

    expect($resources)->not->toBeEmpty();

    foreach ($resources as $resource) {
        $traits = class_uses_recursive($resource);

        expect(isset($traits[ReadOnlyForSupport::class]) || isset($traits[AdminOnly::class]))
            ->toBeTrue("{$resource} must use ReadOnlyForSupport or AdminOnly");
    }
});

it('lets support open the read-only pages', function (string $url) {
    $data = staffRolesDataset();

    $this->actingAs(User::factory()->support()->create())->get(staffRolesUrl($url, $data))->assertOk();
})->with([
    '/admin',
    '/admin/families',
    '/admin/families/{family}',
    '/admin/goal-plans',
    '/admin/goal-plans/{plan}',
    '/admin/content-items',
    '/admin/content-batches',
    '/admin/content-batches/{batch}',
    '/admin/content-preview',
    '/admin/content-preview?item={item}',
    '/admin/categories',
    '/admin/tags',
    '/admin/challenge-types',
    '/admin/theme-worlds',
    '/admin/webhook-events',
    '/admin/webhook-events/{event}',
    '/admin/account-deletion-requests',
    '/admin/account-deletion-requests/{deletion}',
]);

it('keeps support out of edit, create, settings, policy, audit, AI and Horizon pages', function (string $url) {
    $data = staffRolesDataset();

    $this->actingAs(User::factory()->support()->create())->get(staffRolesUrl($url, $data))->assertForbidden();
})->with([
    '/admin/families/{family}/edit',
    '/admin/content-items/create',
    '/admin/content-items/{item}/edit',
    '/admin/content-batches/create',
    '/admin/content-batches/{batch}/edit',
    '/admin/categories/create',
    '/admin/categories/{category}/edit',
    '/admin/age-bands',
    '/admin/age-bands/create',
    '/admin/age-bands/{ageBand}/edit',
    '/admin/settings',
    '/admin/activities',
    '/admin/ai-batch-generator',
    '/admin/ai-batch-review?batch={batch}',
    '/horizon',
]);

it('lets admins open the same pages support cannot', function (string $url) {
    $data = staffRolesDataset();

    $this->actingAs(User::factory()->staff()->create())->get(staffRolesUrl($url, $data))->assertOk();
})->with([
    '/admin/families/{family}/edit',
    '/admin/content-items/{item}/edit',
    '/admin/age-bands',
    '/admin/settings',
    '/admin/activities',
    '/admin/ai-batch-review?batch={batch}',
]);

it('keeps users without a staff role and erased staff out of the panel', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    $this->actingAs(User::factory()->create(['staff_role' => 'something-else']))->get('/admin')->assertForbidden();
    $this->actingAs(User::factory()->support()->create(['erased_at' => now()]))->get('/admin')->assertForbidden();
    $this->actingAs(User::factory()->staff()->create(['erased_at' => now()]))->get('/admin')->assertForbidden();
});

it('hides settings, policies, the audit log and the AI generator from the support navigation', function () {
    $this->actingAs(User::factory()->support()->create())->get('/admin')->assertOk()
        ->assertSee('Families')
        ->assertDontSee('/admin/settings', false)
        ->assertDontSee('/admin/age-bands', false)
        ->assertDontSee('/admin/activities', false)
        ->assertDontSee('/admin/ai-batch-generator', false);
});

it('keeps the full navigation for admins', function () {
    $this->actingAs(User::factory()->staff()->create())->get('/admin')->assertOk()
        ->assertSee('/admin/settings', false)
        ->assertSee('/admin/age-bands', false)
        ->assertSee('/admin/ai-batch-generator', false);
});

it('opens Horizon for admins only', function () {
    expect(Gate::forUser(User::factory()->support()->create())->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser(User::factory()->staff()->create())->allows('viewHorizon'))->toBeTrue();
});

it('shows no create, edit or delete actions to support on the resource lists', function () {
    $data = staffRolesDataset();
    $this->actingAs(User::factory()->support()->create());
    $tag = Tag::query()->firstOrFail();
    $type = ChallengeType::query()->firstOrFail();

    Livewire::test(ManageTags::class)->assertOk()
        ->assertActionHidden(CreateAction::class)
        ->assertTableActionHidden('edit', $tag)
        ->assertTableActionHidden('delete', $tag);

    Livewire::test(ManageChallengeTypes::class)->assertOk()
        ->assertActionHidden(CreateAction::class)
        ->assertTableActionHidden('edit', $type);

    Livewire::test(ListCategories::class)->assertOk()->assertActionHidden(CreateAction::class);

    Livewire::test(ListContentItems::class)->assertOk()
        ->assertActionHidden(CreateAction::class)
        ->assertTableActionHidden('edit', $data['item'])
        ->assertTableActionVisible('preview', $data['item'])
        ->assertTableBulkActionHidden('publish')
        ->assertTableBulkActionHidden('send_to_review')
        ->assertTableBulkActionHidden('archive')
        ->assertTableBulkActionHidden('retag')
        ->assertTableBulkActionHidden('delete');

    Livewire::test(ListContentBatches::class)->assertOk()
        ->assertActionHidden(CreateAction::class)
        ->assertTableActionHidden('edit', $data['batch'])
        ->assertTableActionVisible('view', $data['batch']);

    Livewire::test(ViewContentBatch::class, ['record' => $data['batch']->id])->assertOk()->assertActionHidden('edit');

    Livewire::test(ListFamilies::class)->assertOk()
        ->assertTableActionHidden('edit', $data['owner']->family)
        ->assertTableActionVisible('view', $data['owner']->family);

    Livewire::test(ViewFamily::class, ['record' => $data['owner']->family_id])->assertOk()->assertActionHidden('edit');

    Livewire::test(ManageGoalPlans::class)->assertOk()
        ->assertTableActionVisible('view', $data['plan'])
        ->assertTableActionHidden('deactivate', $data['plan']);

    Livewire::test(ListWebhookEvents::class)->assertOk()->assertTableActionVisible('view', $data['event']);
    Livewire::test(ViewWebhookEvent::class, ['record' => $data['event']->id])->assertOk();
    Livewire::test(ListAccountDeletionRequests::class)->assertOk()->assertTableActionVisible('view', $data['deletion']);
    Livewire::test(ManageThemeWorlds::class)->assertOk()->assertActionHidden(CreateAction::class);
});

it('blocks support from mutating through table actions, bulk actions and toggle columns', function () {
    $data = staffRolesDataset();
    $this->actingAs(User::factory()->support()->create());
    $draft = ContentItem::factory()->create(['status' => ContentItem::STATUS_DRAFT]);
    $category = Category::query()->findOrFail($data['item']->category_id);
    $world = ThemeWorld::query()->firstOrFail();
    $type = ChallengeType::query()->firstOrFail();

    $list = Livewire::test(ListContentItems::class)->set('selectedTableRecords', [(string) $draft->id]);
    forceTableAction($list, 'publish');
    forceTableAction($list, 'delete');
    forceTableAction(Livewire::test(ListContentItems::class), 'edit', $draft);
    forceTableAction(Livewire::test(ManageGoalPlans::class), 'deactivate', $data['plan']);
    forceTableAction(Livewire::test(ManageTags::class), 'delete', Tag::query()->firstOrFail());
    Livewire::test(ListCategories::class)->call('updateTableColumnState', 'is_active', (string) $category->id, ! $category->is_active);
    Livewire::test(ManageThemeWorlds::class)->call('updateTableColumnState', 'active', (string) $world->getKey(), ! $world->active);
    Livewire::test(ManageChallengeTypes::class)->call('updateTableColumnState', 'is_active', (string) $type->getKey(), ! $type->is_active);

    expect($draft->refresh()->status)->toBe(ContentItem::STATUS_DRAFT)
        ->and($data['plan']->refresh()->is_active)->toBeTrue()
        ->and($category->is_active)->toBe($category->refresh()->is_active)
        ->and($world->active)->toBe($world->refresh()->active)
        ->and($type->is_active)->toBe($type->refresh()->is_active)
        ->and(Tag::query()->count())->toBe(1);
});

it('still lets admins toggle and run the bulk actions', function () {
    $data = staffRolesDataset();
    $this->actingAs(User::factory()->staff()->create());
    $category = Category::query()->findOrFail($data['item']->category_id);
    $wasActive = $category->is_active;

    Livewire::test(ListCategories::class)->call('updateTableColumnState', 'is_active', (string) $category->id, ! $wasActive);
    Livewire::test(ManageGoalPlans::class)->callTableAction('deactivate', $data['plan']);

    expect($category->refresh()->is_active)->toBe(! $wasActive)
        ->and($data['plan']->refresh()->is_active)->toBeFalse();
});

it('shows family members, invites, consents and goal periods to support without any action', function () {
    $data = staffRolesDataset();
    $this->actingAs(User::factory()->support()->create());
    $family = Family::query()->findOrFail($data['owner']->family_id);
    $kid = $data['kid'];
    $invite = Invite::query()->where('family_id', $family->id)->firstOrFail();

    Livewire::test(AllMembersRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class])
        ->assertOk()
        ->assertCanSeeTableRecords($family->allMembers()->get())
        ->assertTableActionHidden('signInCode', $kid)
        ->assertTableActionHidden('signOutDevice', $kid)
        ->assertTableActionHidden('startDataExport', $kid)
        ->assertTableActionHidden('deleteChildAccount', $kid);

    $members = Livewire::test(AllMembersRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class]);
    forceTableAction($members, 'signInCode', $kid);
    forceTableAction($members, 'startDataExport', $kid);
    forceTableAction($members, 'deleteChildAccount', $kid);

    Livewire::test(InvitesRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class])
        ->assertOk()
        ->assertTableActionHidden('reset', $invite)
        ->assertTableActionHidden('revoke', $invite);
    forceTableAction(Livewire::test(InvitesRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class]), 'revoke', $invite);

    Livewire::test(ConsentsRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class])->assertOk();
    Livewire::test(PeriodsRelationManager::class, ['ownerRecord' => $data['plan'], 'pageClass' => ViewGoalPlan::class])->assertOk();

    expect($kid->refresh()->status)->toBe(FamilyMember::STATUS_ACTIVE)
        ->and(SignInCode::query()->where('member_id', $kid->id)->count())->toBe(1)
        ->and(Invite::query()->find($invite->id))->not->toBeNull();
});

it('keeps the AI generator pages closed to support, also for Livewire calls', function () {
    $data = staffRolesDataset();
    $this->actingAs(User::factory()->support()->create());

    expect(AiBatchGenerator::canAccess())->toBeFalse()
        ->and(AiBatchReview::canAccess())->toBeFalse();

    Livewire::test(AiBatchReview::class, ['batch' => $data['batch']->id])->assertForbidden();

    $this->actingAs(User::factory()->staff()->create());

    expect(AiBatchGenerator::canAccess())->toBeTrue();
});

it('gives every custom page an explicit access rule', function () {
    $this->actingAs(User::factory()->support()->create());

    $pages = collect(Filament::getPanel('admin')->getPages());

    // Support may preview content; the AI generator pages are admin-only. A new page must be added here.
    expect($pages->mapWithKeys(fn (string $page): array => [class_basename($page) => $page::canAccess()])->sortKeys()->all())
        ->toBe(['AiBatchGenerator' => false, 'AiBatchReview' => false, 'ContentPreview' => true, 'Dashboard' => true, 'MonthlyPayouts' => false]);
});

it('shows support the dashboard without the AI review list or Horizon links', function () {
    staffRolesDataset();
    Cache::flush();
    $this->actingAs(User::factory()->support()->create());

    $this->get('/admin')->assertOk()
        ->assertSeeLivewire(HealthStats::class)
        ->assertDontSeeLivewire(BatchesAwaitingReview::class);

    Livewire::test(HealthStats::class)->assertOk()->assertSee('AdMob SSV rejections')->assertDontSee('/horizon/failed');
});

it('never lets the support role reach the settings or age band data', function () {
    $this->actingAs(User::factory()->support()->create());

    expect(Setting::query()->count())->toBeGreaterThan(0);

    $this->get('/admin/settings')->assertForbidden();
    $this->get('/admin/age-bands')->assertForbidden();
});
