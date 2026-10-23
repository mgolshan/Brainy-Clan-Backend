<?php

use App\Modules\Content\Models\Category;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\Invite;
use App\Modules\Family\Models\SignInCode;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\SocialAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Play\Models\PlaySession;
use App\Modules\Play\Models\SessionItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Security review (session 16): cross-family access, mass assignment, validation limits, rate
 * limits, secrets at rest, the audit log and admin/production settings.
 */

// ---------------------------------------------------------------------------------------------
// Cross-family access: every route id of another family is 404 (never 403, which would leak that it exists)
// ---------------------------------------------------------------------------------------------

/**
 * Another family with one of everything a route id can point at.
 *
 * @return array{member: FamilyMember, device: Device, invite: Invite, plan: GoalPlan, session: PlaySession, item: SessionItem}
 */
function strangerFamilyRecords(): array
{
    $strangerOwner = familyOwner(['tier' => 'family']);
    $kid = addMember($strangerOwner->family, 9);
    $category = Category::factory()->create();

    $session = PlaySession::query()->create([
        'family_id' => $kid->family_id,
        'member_id' => $kid->id,
        'category_id' => $category->id,
        'target_points' => 10,
        'blunder_cap' => 3,
        'status' => PlaySession::STATUS_ACTIVE,
        'started_at' => now(),
    ]);

    return [
        'member' => $kid,
        'device' => Device::factory()->create(['user_id' => $kid->user_id]),
        'invite' => Invite::factory()->create(['family_id' => $strangerOwner->family_id, 'created_by' => $strangerOwner->user_id]),
        'plan' => GoalPlan::factory()->create(['family_id' => $strangerOwner->family_id]),
        'session' => $session,
        'item' => SessionItem::query()->create([
            'play_session_id' => $session->id,
            'type_key' => 'multiple_choice',
            'category_id' => $category->id,
            'position' => 1,
            'points' => 1,
        ]),
    ];
}

it('returns 404 for every route id that belongs to another family', function (string $method, Closure $uri, array $body) {
    $owner = familyOwner(['tier' => 'family']);
    $records = strangerFamilyRecords();
    actingAsMember($owner);

    $this->json($method, '/api/v1/'.$uri($records), $body)
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');
})->with([
    'update member' => ['PATCH', fn (array $r) => "family/members/{$r['member']->id}", ['nickname' => 'X']],
    'remove member' => ['DELETE', fn (array $r) => "family/members/{$r['member']->id}", []],
    'list member devices' => ['GET', fn (array $r) => "family/members/{$r['member']->id}/devices", []],
    'sign out member device' => ['DELETE', fn (array $r) => "family/members/{$r['member']->id}/devices/{$r['device']->id}", []],
    'issue sign-in code' => ['POST', fn (array $r) => "family/members/{$r['member']->id}/sign-in-codes", []],
    'member notification prefs' => ['GET', fn (array $r) => "family/members/{$r['member']->id}/notification-preferences", []],
    'update member notification prefs' => ['PATCH', fn (array $r) => "family/members/{$r['member']->id}/notification-preferences", ['reminder_time' => '17:00']],
    'revoke invite' => ['DELETE', fn (array $r) => "family/invites/{$r['invite']->id}", []],
    'show goal' => ['GET', fn (array $r) => "goals/{$r['plan']->id}", []],
    'goal history' => ['GET', fn (array $r) => "goals/{$r['plan']->id}/history", []],
    'update goal' => ['PATCH', fn (array $r) => "goals/{$r['plan']->id}", ['name' => 'Mine now']],
    'delete goal' => ['DELETE', fn (array $r) => "goals/{$r['plan']->id}", []],
    'show session' => ['GET', fn (array $r) => "play/sessions/{$r['session']->id}", []],
    'answer in session' => ['POST', fn (array $r) => "play/sessions/{$r['session']->id}/answers", ['session_item_id' => $r['item']->id ?? 0, 'response' => ['choice_id' => 'a']]],
    'second chance' => ['POST', fn (array $r) => "play/sessions/{$r['session']->id}/second-chance", ['method' => 'free']],
    'redemption' => ['POST', fn (array $r) => "play/sessions/{$r['session']->id}/redemption", []],
    'abandon session' => ['POST', fn (array $r) => "play/sessions/{$r['session']->id}/abandon", []],
]);

it('leaves the other family\'s records untouched after the 404s', function () {
    $owner = familyOwner(['tier' => 'family']);
    $records = strangerFamilyRecords();
    actingAsMember($owner);

    $this->deleteJson("/api/v1/family/members/{$records['member']->id}")->assertNotFound();
    $this->deleteJson("/api/v1/family/members/{$records['member']->id}/devices/{$records['device']->id}")->assertNotFound();
    $this->deleteJson("/api/v1/family/invites/{$records['invite']->id}")->assertNotFound();
    $this->deleteJson("/api/v1/goals/{$records['plan']->id}")->assertNotFound();
    $this->postJson("/api/v1/play/sessions/{$records['session']->id}/abandon")->assertNotFound();

    expect($records['member']->refresh()->status)->toBe(FamilyMember::STATUS_ACTIVE)
        ->and(Device::query()->find($records['device']->id))->not->toBeNull()
        ->and(Invite::query()->find($records['invite']->id))->not->toBeNull()
        ->and($records['plan']->refresh()->trashed())->toBeFalse()
        ->and($records['session']->refresh()->status)->toBe(PlaySession::STATUS_ACTIVE);
});

it('returns 404 for a member removed from the caller\'s own family', function () {
    $owner = familyOwner();
    $removed = addMember($owner->family, 9);
    $removed->update(['status' => FamilyMember::STATUS_REMOVED]);
    actingAsMember($owner);

    $this->patchJson("/api/v1/family/members/{$removed->id}", ['nickname' => 'Back'])->assertNotFound();
    $this->postJson("/api/v1/family/members/{$removed->id}/sign-in-codes")->assertNotFound();
});

it('returns 404 for another member\'s session inside the same family', function () {
    $owner = familyOwner(['tier' => 'family']);
    $kid = addMember($owner->family, 9);
    $session = PlaySession::query()->create([
        'family_id' => $owner->family_id,
        'member_id' => $kid->id,
        'category_id' => Category::factory()->create()->id,
        'target_points' => 10,
        'blunder_cap' => 3,
        'status' => PlaySession::STATUS_ACTIVE,
        'started_at' => now(),
    ]);
    actingAsMember($owner);

    $this->getJson("/api/v1/play/sessions/{$session->id}")->assertNotFound();
    $this->postJson("/api/v1/play/sessions/{$session->id}/abandon")->assertNotFound();
});

it('rejects ids of another family inside request bodies', function () {
    $owner = familyOwner(['tier' => 'family']);
    $records = strangerFamilyRecords();
    $category = Category::factory()->create();
    actingAsMember($owner);

    $this->postJson('/api/v1/goals', [
        'name' => 'Sneaky',
        'mode' => 'competition',
        'period_type' => 'weekly',
        'target_points' => 10,
        'starts_on' => CarbonImmutable::now($owner->family->timezone)->toDateString(),
        'category_ids' => [$category->id],
        'members' => [['member_id' => $records['member']->id]],
    ])->assertUnprocessable()->assertJsonValidationErrors('members');

    $mySession = PlaySession::query()->create([
        'family_id' => $owner->family_id,
        'member_id' => $owner->id,
        'category_id' => $category->id,
        'target_points' => 10,
        'blunder_cap' => 3,
        'status' => PlaySession::STATUS_ACTIVE,
        'started_at' => now(),
    ]);

    $this->postJson("/api/v1/play/sessions/{$mySession->id}/answers", ['session_item_id' => $records['item']->id, 'response' => ['choice_id' => 'a']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('session_item_id');
    expect($records['item']->refresh()->answered_at)->toBeNull();
});

// ---------------------------------------------------------------------------------------------
// Mass assignment
// ---------------------------------------------------------------------------------------------

it('declares an explicit fillable list on every model', function () {
    $files = collect(File::allFiles(app_path('Modules')))
        ->filter(fn (SplFileInfo $file) => Str::contains($file->getPathname(), DIRECTORY_SEPARATOR.'Models'.DIRECTORY_SEPARATOR));

    $models = $files
        ->map(fn (SplFileInfo $file) => 'App\\'.str_replace(['/', '.php'], ['\\', ''], Str::after(str_replace('\\', '/', $file->getPathname()), 'app/')))
        ->filter(fn (string $class) => class_exists($class) && is_subclass_of($class, Model::class));

    expect($models)->not->toBeEmpty();

    foreach ($models as $class) {
        $reflection = new ReflectionClass($class);
        $model = new $class;

        expect($reflection->getAttributes(Fillable::class))->not->toBeEmpty("{$class} has no #[Fillable]")
            ->and($model->getFillable())->not->toBeEmpty("{$class} has an empty fillable list")
            ->and($model->getGuarded())->not->toBe([], "{$class} is unguarded");
    }
});

it('never lets the API set staff, ownership or ledger fields', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    actingAsMember($owner);

    $this->patchJson('/api/v1/me', ['display_name' => 'Moe', 'is_staff' => true, 'email' => 'evil@example.com', 'password' => 'hunter2hunter2'])->assertOk();
    $this->patchJson('/api/v1/family', ['name' => 'Ours', 'tier' => 'family', 'owner_user_id' => $kid->user_id])->assertOk();
    $this->patchJson("/api/v1/family/members/{$kid->id}", ['nickname' => 'Sam', 'family_id' => familyOwner()->family_id, 'user_id' => $owner->user_id, 'status' => 'removed'])->assertOk();

    $user = $owner->user->refresh();
    $family = $owner->family->refresh();
    $kid->refresh();

    expect($user->is_staff)->toBeFalse()
        ->and($user->display_name)->toBe('Moe')
        ->and($user->email)->not->toBe('evil@example.com')
        ->and($family->name)->toBe('Ours')
        ->and($family->tier)->toBe('free')
        ->and($family->owner_user_id)->toBe($owner->user_id)
        ->and($kid->nickname)->toBe('Sam')
        ->and($kid->family_id)->toBe($owner->family_id)
        ->and($kid->user_id)->not->toBe($owner->user_id)
        ->and($kid->status)->toBe(FamilyMember::STATUS_ACTIVE);
});

// ---------------------------------------------------------------------------------------------
// Validation limits
// ---------------------------------------------------------------------------------------------

it('caps string lengths and array sizes on request bodies', function () {
    $owner = familyOwner(['tier' => 'family']);
    $category = Category::factory()->create();

    $this->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => str_repeat('a', 256), 'device_name' => 't', 'platform' => 'web'])
        ->assertUnprocessable()->assertJsonValidationErrors('password');

    actingAsMember($owner);

    $this->putJson('/api/v1/me/password', ['current_password' => str_repeat('a', 256), 'password' => 'long enough', 'password_confirmation' => 'long enough'])
        ->assertUnprocessable()->assertJsonValidationErrors('current_password');

    $this->postJson('/api/v1/goals', [
        'name' => 'Too many', 'mode' => 'cooperation', 'period_type' => 'weekly', 'target_points' => 10,
        'starts_on' => CarbonImmutable::now($owner->family->timezone)->toDateString(),
        'category_ids' => range(1, 201),
        'members' => array_map(fn (int $id) => ['member_id' => $id], range(1, 51)),
    ])->assertUnprocessable()->assertJsonValidationErrors(['category_ids', 'members']);

    $session = PlaySession::query()->create([
        'family_id' => $owner->family_id,
        'member_id' => $owner->id,
        'category_id' => $category->id,
        'target_points' => 10,
        'blunder_cap' => 3,
        'status' => PlaySession::STATUS_ACTIVE,
        'started_at' => now(),
    ]);

    $this->postJson("/api/v1/play/sessions/{$session->id}/answers", [
        'session_item_id' => 1,
        'response' => ['found' => array_fill(0, 101, ['word' => 'CAT', 'start' => [0, 0], 'end' => [0, 2]]), 'grid' => array_fill(0, 17, [0])],
    ])->assertUnprocessable()->assertJsonValidationErrors(['response.found', 'response.grid']);

    $this->postJson("/api/v1/play/sessions/{$session->id}/answers", [
        'session_item_id' => 1,
        'response' => array_combine(array_map(fn ($i) => "k{$i}", range(1, 11)), range(1, 11)),
    ])->assertUnprocessable()->assertJsonValidationErrors('response');
});

// ---------------------------------------------------------------------------------------------
// Rate limits
// ---------------------------------------------------------------------------------------------

it('rate-limits password guesses on one email even when they come from many IPs', function () {
    $body = ['email' => 'Target@Example.com', 'password' => 'nope', 'device_name' => 't', 'platform' => 'web'];

    foreach (range(1, 10) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])->postJson('/api/v1/auth/login', $body)->assertUnauthorized();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])->postJson('/api/v1/auth/login', [...$body, 'email' => ' target@example.com '])
        ->assertStatus(429)->assertJsonPath('code', 'rate_limited');
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.2'])->postJson('/api/v1/auth/login', [...$body, 'email' => 'someone-else@example.com'])
        ->assertUnauthorized();
});

it('rate-limits sign-in code guesses per IP', function () {
    $body = ['code' => 'ABCD2345', 'device_name' => 'Tablet', 'platform' => 'android'];

    foreach (range(1, 4) as $ignored) {
        $this->postJson('/api/v1/auth/code', $body)->assertUnprocessable();
    }

    // The 5th invalid code locks the IP for codes (sign-in lockout, 2026-10-22) …
    foreach (range(5, 10) as $ignored) {
        $this->postJson('/api/v1/auth/code', $body)->assertStatus(429)->assertJsonPath('code', 'login_locked');
    }

    // … and the 10/min throttle still applies on top.

    $this->postJson('/api/v1/auth/code', $body)->assertStatus(429)->assertJsonPath('code', 'rate_limited');
});

it('configures the export, account deletion and webhook limiters', function () {
    $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '10.1.1.1']);

    expect(RateLimiter::limiter('export')($request)->maxAttempts)->toBe(1)
        ->and(RateLimiter::limiter('account-deletion')($request)->maxAttempts)->toBe(5)
        ->and(RateLimiter::limiter('webhooks')($request)->maxAttempts)->toBe(600);
});

// ---------------------------------------------------------------------------------------------
// Secrets at rest
// ---------------------------------------------------------------------------------------------

it('stores sign-in codes only as a keyed hash', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    actingAsMember($owner);

    $code = (string) $this->postJson("/api/v1/family/members/{$kid->id}/sign-in-codes")->assertCreated()->json('data.code');
    $row = (array) DB::table('sign_in_codes')->sole();

    expect($code)->not->toBe('')
        ->and(json_encode($row))->not->toContain($code)
        ->and($row['code_hash'])->toBe(SignInCode::hashCode($code))
        ->and($row['code_hash'])->not->toBe(hash('sha256', $code));
});

it('encrypts Apple refresh tokens at rest and never serializes them', function () {
    $account = SocialAccount::factory()->apple()->create(['refresh_token' => 'apple-refresh-secret']);
    $raw = (string) DB::table('social_accounts')->where('id', $account->id)->value('refresh_token');

    expect($raw)->not->toContain('apple-refresh-secret')
        ->and(decrypt($raw, false))->toBe('apple-refresh-secret')
        ->and($account->refresh()->toArray())->not->toHaveKey('refresh_token');
});

// ---------------------------------------------------------------------------------------------
// Audit log and admin access
// ---------------------------------------------------------------------------------------------

it('drops the family name from earlier audit entries when the family is deleted', function () {
    $owner = familyOwner(['name' => 'The Golshans']);
    actingAsMember($owner);
    $this->patchJson('/api/v1/family', ['name' => 'Golshan Clan'])->assertOk();

    $entries = fn () => Activity::query()->where('subject_type', $owner->family->getMorphClass())->where('subject_id', $owner->family_id)->get();
    expect($entries()->toJson())->toContain('Golshan Clan');

    $this->deleteJson('/api/v1/family', ['confirm' => 'Golshan Clan'])->assertNoContent();

    expect($entries())->not->toBeEmpty()
        ->and($entries()->toJson())->not->toContain('Golshan')
        ->and(Activity::query()->get()->toJson())->not->toContain('Golshan');
});

it('lets only staff open Horizon', function () {
    expect(Gate::forUser(null)->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser(User::factory()->create())->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser(User::factory()->create(['is_staff' => true]))->allows('viewHorizon'))->toBeTrue();
});

it('ships safe production cookie and CORS settings', function () {
    $prod = (string) file_get_contents(base_path('.env.prod.example'));

    expect($prod)->toContain("SESSION_SECURE_COOKIE=true\n")
        ->toContain("SESSION_HTTP_ONLY=true\n")
        ->toContain("SESSION_SAME_SITE=lax\n")
        ->toContain("CORS_ALLOWED_ORIGINS=\n")
        ->and(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax')
        ->and(config('cors.supports_credentials'))->toBeFalse()
        ->and(config('cors.allowed_origins'))->not->toContain('*')
        ->and(config('cors.allowed_origins_patterns'))->toBe([]);
});

// ---------------------------------------------------------------------------------------------
// Hot-path indexes (migration 2026_10_17_100000)
// ---------------------------------------------------------------------------------------------

it('indexes push token lookups and the daily reminder\'s "played today" check', function () {
    expect(Schema::hasIndex('devices', 'devices_push_token_index'))->toBeTrue()
        ->and(Schema::hasIndex('devices', ['push_token']))->toBeTrue()
        ->and(Schema::hasIndex('play_sessions', 'play_sessions_member_id_started_at_index'))->toBeTrue()
        ->and(Schema::hasIndex('play_sessions', ['member_id', 'started_at']))->toBeTrue();
});
