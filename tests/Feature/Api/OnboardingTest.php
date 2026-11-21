<?php

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Content\Models\Category;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\Invite;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Identity\Legal\LegalDocuments;
use App\Modules\Identity\Models\Consent;
use App\Modules\Identity\Models\OnboardingProgress;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\OnboardingSteps;
use App\Modules\Play\Models\PlaySession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * B12 step 4 (docs/API.md "Onboarding (FTUE)"): onboarding progress per user, manual and derived
 * steps, dismiss, the flow picked from tier + role, erasure and export.
 */
function onbPlay(FamilyMember $member, string $status = PlaySession::STATUS_COMPLETED): void
{
    PlaySession::query()->create([
        'family_id' => $member->family_id, 'member_id' => $member->id, 'category_id' => Category::factory()->create()->id,
        'target_points' => 10, 'blunder_cap' => 3, 'mistakes' => 0, 'points' => 10,
        'second_chances_used' => 0, 'redemption_failed' => false, 'status' => $status,
        'started_at' => now(), 'completed_at' => now(),
    ]);
}

function onbConsent(FamilyMember $admin, ?string $version = null): void
{
    Consent::factory()->create([
        'user_id' => $admin->user_id,
        'type' => Consent::CLASSROOM,
        'version' => $version ?? LegalDocuments::classroomConsentVersion(),
        'accepted_at' => now(),
    ]);
}

it('returns every step key, all null, for a new user without a family', function () {
    Sanctum::actingAs(User::factory()->aged(35)->create());

    $data = $this->getJson('/api/v1/me/onboarding')->assertOk()->json('data');

    expect(array_keys($data['steps']))->toBe(OnboardingSteps::ALL)
        ->and(array_filter($data['steps']))->toBe([])
        ->and($data['dismissed_at'])->toBeNull()
        ->and($data['flow'])->toBe('owner')
        ->and($data['required_steps'])->toBe(['welcome', 'create_family', 'pick_world', 'add_member', 'first_game', 'first_goal', 'say_hi'])
        ->and($data['optional_steps'])->toBe([])
        ->and(OnboardingProgress::query()->count())->toBe(0);
});

it('splits the catalog into manual and derived keys', function () {
    expect(array_values(array_intersect(OnboardingSteps::MANUAL, OnboardingSteps::DERIVED)))->toBe([])
        ->and(count(OnboardingSteps::MANUAL) + count(OnboardingSteps::DERIVED))->toBe(count(OnboardingSteps::ALL))
        ->and(array_diff(OnboardingSteps::ALL, [...OnboardingSteps::MANUAL, ...OnboardingSteps::DERIVED]))->toBe([]);
});

it('marks a manual step once and keeps the first done_at', function () {
    Carbon::setTestNow('2026-11-21 10:00:00');
    actingAsMember(familyOwner());

    $this->postJson('/api/v1/me/onboarding/welcome')->assertOk()
        ->assertJsonPath('data.steps.welcome', '2026-11-21T10:00:00Z')
        ->assertJsonPath('data.steps.tip_play', null);

    Carbon::setTestNow('2026-11-21 11:00:00');
    $this->postJson('/api/v1/me/onboarding/welcome', [])->assertOk()
        ->assertJsonPath('data.steps.welcome', '2026-11-21T10:00:00Z');
    $this->postJson('/api/v1/me/onboarding/tip_play')->assertOk()
        ->assertJsonPath('data.steps.welcome', '2026-11-21T10:00:00Z')
        ->assertJsonPath('data.steps.tip_play', '2026-11-21T11:00:00Z');

    expect(OnboardingProgress::query()->count())->toBe(1);
    Carbon::setTestNow();
});

it('rejects unknown and derived step keys with 422 on step', function (string $step, string $message) {
    actingAsMember(familyOwner());

    $this->postJson('/api/v1/me/onboarding/'.$step)->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonPath('errors.step.0', $message);

    expect(OnboardingProgress::query()->count())->toBe(0);
})->with([
    'unknown' => ['launch_rockets', 'Unknown onboarding step.'],
    'derived first game' => ['first_game', 'This step is done automatically.'],
    'derived roster' => ['roster', 'This step is done automatically.'],
    'derived create family' => ['create_family', 'This step is done automatically.'],
]);

it('dismisses once and keeps the first dismissed_at', function () {
    Carbon::setTestNow('2026-11-21 10:00:00');
    actingAsMember(familyOwner());

    $this->postJson('/api/v1/me/onboarding/dismiss')->assertOk()
        ->assertJsonPath('data.dismissed_at', '2026-11-21T10:00:00Z');

    Carbon::setTestNow('2026-11-22 10:00:00');
    $this->postJson('/api/v1/me/onboarding/dismiss')->assertOk()
        ->assertJsonPath('data.dismissed_at', '2026-11-21T10:00:00Z');
    $this->getJson('/api/v1/me/onboarding')->assertOk()
        ->assertJsonPath('data.dismissed_at', '2026-11-21T10:00:00Z')
        ->assertJsonPath('data.steps.create_family', '2026-11-21T10:00:00Z');
    Carbon::setTestNow();
});

it('restores a hidden checklist and keeps the progress', function () {
    Carbon::setTestNow('2026-11-21 10:00:00');
    actingAsMember(familyOwner());

    $this->postJson('/api/v1/me/onboarding/welcome')->assertOk();
    $this->postJson('/api/v1/me/onboarding/dismiss')->assertOk()
        ->assertJsonPath('data.dismissed_at', '2026-11-21T10:00:00Z');

    $this->postJson('/api/v1/me/onboarding/restore')->assertOk()
        ->assertJsonPath('data.dismissed_at', null)
        ->assertJsonPath('data.steps.welcome', '2026-11-21T10:00:00Z');
    $this->postJson('/api/v1/me/onboarding/restore')->assertOk()
        ->assertJsonPath('data.dismissed_at', null);
    $this->getJson('/api/v1/me/onboarding')->assertOk()
        ->assertJsonPath('data.dismissed_at', null)
        ->assertJsonPath('data.steps.welcome', '2026-11-21T10:00:00Z');
    Carbon::setTestNow();
});

it('needs a token', function () {
    $this->getJson('/api/v1/me/onboarding')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    $this->postJson('/api/v1/me/onboarding/welcome')->assertUnauthorized();
    $this->postJson('/api/v1/me/onboarding/dismiss')->assertUnauthorized();
    $this->postJson('/api/v1/me/onboarding/restore')->assertUnauthorized();
});

it('derives the owner steps from real data and stores them once', function () {
    $owner = familyOwner(['tier' => Family::TIER_BIG_CLAN]);
    actingAsMember($owner);

    $steps = $this->getJson('/api/v1/me/onboarding')->assertOk()->json('data.steps');
    expect($steps['create_family'])->not->toBeNull()
        ->and($steps['code_sign_in'])->not->toBeNull()
        ->and($steps['add_member'])->toBeNull()
        ->and($steps['co_admin'])->toBeNull()
        ->and($steps['first_game'])->toBeNull()
        ->and($steps['first_goal'])->toBeNull()
        ->and($steps['say_hi'])->toBeNull();

    Invite::factory()->create(['family_id' => $owner->family_id, 'created_by' => $owner->user_id]);
    addMember($owner->family, 40, FamilyMember::ROLE_ADMIN);
    onbPlay($owner, PlaySession::STATUS_FAILED);
    $goal = GoalPlan::factory()->create(['family_id' => $owner->family_id]);
    $goal->delete();
    ChatMessage::factory()->by($owner)->create();

    $steps = $this->getJson('/api/v1/me/onboarding')->assertOk()->json('data.steps');
    foreach (['add_member', 'co_admin', 'first_game', 'first_goal', 'say_hi'] as $key) {
        expect($steps[$key])->not->toBeNull();
    }

    // Done stays done, even when the data goes away.
    ChatMessage::query()->delete();
    PlaySession::query()->delete();
    expect($this->getJson('/api/v1/me/onboarding')->json('data.steps.say_hi'))->not->toBeNull()
        ->and($this->getJson('/api/v1/me/onboarding')->json('data.steps.first_game'))->not->toBeNull();
});

it('counts a pending invite but not a used or expired one for add_member', function () {
    $owner = familyOwner();
    actingAsMember($owner);
    Invite::factory()->expired()->create(['family_id' => $owner->family_id, 'created_by' => $owner->user_id]);
    Invite::factory()->used()->create(['family_id' => $owner->family_id, 'created_by' => $owner->user_id]);

    expect($this->getJson('/api/v1/me/onboarding')->json('data.steps.add_member'))->toBeNull();

    Invite::factory()->create(['family_id' => $owner->family_id, 'created_by' => $owner->user_id]);
    expect($this->getJson('/api/v1/me/onboarding')->json('data.steps.add_member'))->not->toBeNull();
});

it('does not count an abandoned or active game as the first game, nor another member\'s', function () {
    $owner = familyOwner();
    onbPlay($owner, PlaySession::STATUS_ABANDONED);
    onbPlay($owner, PlaySession::STATUS_ACTIVE);
    onbPlay(addMember($owner->family, 9));
    actingAsMember($owner);

    expect($this->getJson('/api/v1/me/onboarding')->json('data.steps.first_game'))->toBeNull();
});

it('derives the classroom teacher steps', function () {
    $teacher = familyOwner(['tier' => Family::TIER_CLASSROOM]);
    actingAsMember($teacher);

    $data = $this->getJson('/api/v1/me/onboarding')->assertOk()->json('data');
    expect($data['flow'])->toBe('teacher')
        ->and($data['required_steps'])->toBe(['classroom_welcome', 'class_details', 'roster', 'code_cards', 'chat_mode', 'first_goal', 'end_of_year'])
        ->and($data['optional_steps'])->toBe(['co_admin'])
        ->and($data['steps']['class_details'])->toBeNull()
        ->and($data['steps']['roster'])->toBeNull()
        ->and($data['steps']['code_cards'])->toBeNull();

    // A stale consent version does not count.
    onbConsent($teacher, '2000-01-01');
    expect($this->getJson('/api/v1/me/onboarding')->json('data.steps.class_details'))->toBeNull();

    onbConsent($teacher);
    addMember($teacher->family, 9);
    $teacher->family->forceFill(['codes_issued_at' => now()])->save();
    app('auth')->forgetGuards();
    actingAsMember($teacher->fresh());

    $steps = $this->getJson('/api/v1/me/onboarding')->json('data.steps');
    expect($steps['class_details'])->not->toBeNull()
        ->and($steps['roster'])->not->toBeNull()
        ->and($steps['code_cards'])->not->toBeNull();
});

it('lets a co-teacher see the class details done by the teacher\'s consent', function () {
    $teacher = familyOwner(['tier' => Family::TIER_CLASSROOM]);
    $coTeacher = addMember($teacher->family, 35, FamilyMember::ROLE_ADMIN);
    onbConsent($teacher);
    actingAsMember($coTeacher);

    $data = $this->getJson('/api/v1/me/onboarding')->json('data');
    expect($data['flow'])->toBe('teacher')
        ->and($data['steps']['class_details'])->not->toBeNull()
        ->and($data['steps']['co_admin'])->not->toBeNull();
});

it('does not derive classroom steps on other tiers', function () {
    $owner = familyOwner(['tier' => Family::TIER_FAMILY, 'codes_issued_at' => now()]);
    onbConsent($owner);
    addMember($owner->family, 9);
    actingAsMember($owner);

    $steps = $this->getJson('/api/v1/me/onboarding')->json('data.steps');
    expect($steps['class_details'])->toBeNull()
        ->and($steps['roster'])->toBeNull()
        ->and($steps['code_cards'])->toBeNull();
});

it('picks the flow from tier and role', function (string $tier, string $who, string $flow, array $required) {
    $owner = familyOwner(['tier' => $tier]);
    $member = match ($who) {
        'owner' => $owner,
        'kid' => addMember($owner->family, 9),
        'coadmin' => addMember($owner->family, 40, FamilyMember::ROLE_ADMIN),
    };
    actingAsMember($member);

    $data = $this->getJson('/api/v1/me/onboarding')->assertOk()->json('data');
    expect($data['flow'])->toBe($flow)->and($data['required_steps'])->toBe($required);
})->with([
    'free owner' => [Family::TIER_FREE, 'owner', 'owner', ['welcome', 'create_family', 'pick_world', 'add_member', 'first_game', 'first_goal', 'say_hi']],
    'big clan owner' => [Family::TIER_BIG_CLAN, 'owner', 'owner', ['welcome', 'create_family', 'pick_world', 'add_member', 'co_admin', 'first_game', 'first_goal', 'say_hi']],
    'family kid' => [Family::TIER_FAMILY, 'kid', 'member', ['welcome_family', 'avatar_world', 'first_game', 'family_goal']],
    'family co-admin' => [Family::TIER_FAMILY, 'coadmin', 'member', ['welcome_family', 'avatar_world', 'first_game', 'family_goal']],
    'classroom student' => [Family::TIER_CLASSROOM, 'kid', 'student', ['code_sign_in', 'avatar_world', 'first_game', 'family_goal', 'say_hi']],
]);

it('leaves out say_hi when chat is off, read-only for students, or off for the member', function () {
    $classroom = familyOwner(['tier' => Family::TIER_CLASSROOM, 'chat_mode' => Family::CHAT_MODE_ANNOUNCEMENTS]);
    $student = addMember($classroom->family, 9);
    actingAsMember($student);
    expect($this->getJson('/api/v1/me/onboarding')->json('data.required_steps'))->not->toContain('say_hi');

    $owner = familyOwner(['tier' => Family::TIER_FAMILY]);
    $owner->forceFill(['chat_enabled' => false])->save();
    app('auth')->forgetGuards();
    actingAsMember($owner->fresh());
    expect($this->getJson('/api/v1/me/onboarding')->json('data.required_steps'))->not->toContain('say_hi');
});

it('stores progress per user, so another device sees it', function () {
    $owner = familyOwner();
    actingAsMember($owner);
    $this->postJson('/api/v1/me/onboarding/pick_world')->assertOk();

    $kid = addMember($owner->family, 9);
    app('auth')->forgetGuards();
    actingAsMember($kid);
    expect($this->getJson('/api/v1/me/onboarding')->json('data.steps.pick_world'))->toBeNull();

    app('auth')->forgetGuards();
    actingAsMember($owner);
    expect($this->getJson('/api/v1/me/onboarding')->json('data.steps.pick_world'))->not->toBeNull();
});

it('includes onboarding in the export and deletes it with the account', function () {
    $user = User::factory()->aged(35)->create();
    Sanctum::actingAs($user);
    $this->postJson('/api/v1/me/onboarding/tip_goals')->assertOk();
    $this->postJson('/api/v1/me/onboarding/dismiss')->assertOk();

    $export = $this->getJson('/api/v1/me/export')->assertOk()->json('data.onboarding');
    expect(array_keys($export['steps']))->toBe(['tip_goals'])
        ->and($export['dismissed_at'])->not->toBeNull();

    $this->deleteJson('/api/v1/me', ['confirm' => 'DELETE'])->assertNoContent();
    expect(OnboardingProgress::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('exports null onboarding when the user has none', function () {
    Sanctum::actingAs(User::factory()->aged(35)->create());

    $this->getJson('/api/v1/me/export')->assertOk()->assertJsonPath('data.onboarding', null);
});

/**
 * Queries of one onboarding GET as this member (after a warm-up call that also stores derived steps).
 */
function onbQueries(FamilyMember $member, bool $warm = true): int
{
    app('auth')->forgetGuards();
    actingAsMember($member);

    if ($warm) {
        test()->getJson('/api/v1/me/onboarding')->assertOk();
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->getJson('/api/v1/me/onboarding')->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

function onbClassroom(int $students): FamilyMember
{
    $teacher = familyOwner(['tier' => Family::TIER_CLASSROOM, 'codes_issued_at' => now()]);
    onbConsent($teacher);

    for ($i = 0; $i < $students; $i++) {
        $student = addMember($teacher->family, 9);
        onbPlay($student);
        ChatMessage::factory()->by($student)->create();
        GoalPlan::factory()->create(['family_id' => $teacher->family_id]);
        Invite::factory()->create(['family_id' => $teacher->family_id, 'created_by' => $teacher->user_id]);
    }

    return $teacher;
}

it('derives the steps with a constant number of queries', function () {
    onbQueries(familyOwner()); // warms the policy cache

    // First call (derives and stores) and later calls, for small and large classes.
    expect(onbQueries(onbClassroom(2), warm: false))->toBe(onbQueries(onbClassroom(8), warm: false))
        ->and(onbQueries(onbClassroom(2)))->toBe(onbQueries(onbClassroom(8)))
        ->and(onbQueries(onbClassroom(8)))->toBeLessThanOrEqual(6);
});
