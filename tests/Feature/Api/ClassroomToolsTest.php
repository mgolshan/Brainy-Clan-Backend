<?php

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Content\Models\Category;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\Invite;
use App\Modules\Family\Models\SignInCode;
use App\Modules\Family\Support\ClassroomSummaryCsv;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Identity\Legal\LegalDocuments;
use App\Modules\Identity\Models\Consent;
use App\Modules\Identity\Models\User;
use App\Modules\Play\Models\PlaySession;
use App\Modules\Play\Models\SessionItem;
use App\Modules\Policy\Models\Setting;
use App\Modules\Policy\PolicyResolver;
use App\Modules\Scoring\Models\PointEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * B12 step 3 (docs/API.md "Classroom tools"): consent statement, Family `classroom` setup state,
 * 409 `classroom_consent_required`, roster, code cards, class summary (JSON + CSV) and
 * "Archive & start a new year" (points seasons: the ledger stays append-only).
 */

/**
 * A Classroom family whose owner (the teacher, signed in) accepted the current consent statement,
 * unless `$consented` is false.
 */
function classroomTeacher(bool $consented = true, string $name = 'Grade 4B'): FamilyMember
{
    $teacher = familyOwner(['tier' => Family::TIER_CLASSROOM, 'name' => $name]);
    $teacher->forceFill(['nickname' => 'Teacher'])->save();
    actingAsMember($teacher);

    if ($consented) {
        test()->postJson('/api/v1/family/classroom/consent', ['version' => LegalDocuments::classroomConsentVersion()])->assertOk();
    }

    return $teacher;
}

/**
 * @param  list<string>  $nicknames
 * @return list<int> member ids
 */
function rosterStudents(array $nicknames, int $birthYear = 2016): array
{
    $response = test()->postJson('/api/v1/family/classroom/roster', [
        'birth_year' => $birthYear,
        'students' => array_map(fn (string $nickname) => ['nickname' => $nickname], $nicknames),
    ])->assertCreated();

    return array_map(fn (array $row) => (int) $row['id'], $response->json('data'));
}

function newYearBody(array $clear, string $confirmName = 'Grade 4B'): array
{
    return ['confirm_name' => $confirmName, 'clear' => $clear];
}

// ---------------------------------------------------------------------------------------------
// Consent statement
// ---------------------------------------------------------------------------------------------

it('describes the classroom consent statement in GET /legal (en and fr)', function () {
    $this->getJson('/api/v1/legal')->assertOk()
        ->assertJsonPath('data.classroom_consent.version', LegalDocuments::classroomConsentVersion())
        ->assertJsonPath('data.classroom_consent.text_url', LegalDocuments::classroomConsentUrl('en'))
        ->assertJsonPath('data.classroom_consent.statement', fn (string $text) => str_contains($text, 'a teacher, tutor or coach, and I am authorised'))
        ->assertJsonPath('data.terms.version', LegalDocuments::termsVersion());

    $this->getJson('/api/v1/legal?lang=fr')->assertOk()
        ->assertJsonPath('data.classroom_consent.text_url', fn (string $url) => str_ends_with($url, '/legal/classroom?lang=fr'))
        ->assertJsonPath('data.classroom_consent.statement', fn (string $text) => str_starts_with($text, 'Je suis enseignant'));
});

it('serves the draft Classroom terms page in English and French', function () {
    $this->get('/legal/classroom')->assertOk()
        ->assertSee('DRAFT FOR LAWYER REVIEW')
        ->assertSee('authorised by my school or organisation', false)
        ->assertSee('Version '.LegalDocuments::classroomConsentVersion());

    $this->get('/legal/classroom?lang=fr')->assertOk()
        ->assertSee('ÉBAUCHE À FAIRE RÉVISER PAR UN AVOCAT')
        ->assertSee('Je suis enseignant');
});

it('records the consent per user and shows the setup state on the Family object', function () {
    $teacher = classroomTeacher(consented: false);

    $this->getJson('/api/v1/family')->assertOk()
        ->assertJsonPath('data.family.classroom.consent_version', null)
        ->assertJsonPath('data.family.classroom.consent_accepted_at', null)
        ->assertJsonPath('data.family.classroom.students_count', 0)
        ->assertJsonPath('data.family.classroom.codes_issued_at', null)
        ->assertJsonPath('data.family.classroom.season_started_at', null);

    $version = LegalDocuments::classroomConsentVersion();

    $this->postJson('/api/v1/family/classroom/consent', ['version' => $version], ['REMOTE_ADDR' => '203.0.113.7'])->assertOk()
        ->assertJsonPath('data.classroom.consent_version', $version)
        ->assertJsonPath('data.classroom.consent_accepted_at', fn ($at) => is_string($at));

    // Accepting again adds no second row.
    $this->postJson('/api/v1/family/classroom/consent', ['version' => $version])->assertOk();

    $consents = Consent::query()->where('user_id', $teacher->user_id)->where('type', Consent::CLASSROOM)->get();
    expect($consents)->toHaveCount(1)
        ->and($consents->first()->version)->toBe($version)
        ->and($consents->first()->ip_hash)->toHaveLength(64)
        ->and($consents->first()->ip_hash)->not->toContain('203.0.113.7');
});

it('refuses a stale consent version and non-classroom families', function () {
    classroomTeacher(consented: false);

    $this->postJson('/api/v1/family/classroom/consent', ['version' => '2020-01-01'])
        ->assertUnprocessable()->assertJsonValidationErrors('version');
    $this->postJson('/api/v1/family/classroom/consent', [])->assertUnprocessable()->assertJsonValidationErrors('version');

    actingAsMember(familyOwner(['tier' => Family::TIER_FAMILY]));

    $this->postJson('/api/v1/family/classroom/consent', ['version' => LegalDocuments::classroomConsentVersion()])
        ->assertForbidden()->assertJsonPath('code', 'plan_required');
    $this->getJson('/api/v1/family')->assertOk()->assertJsonPath('data.family.classroom', null);
});

it('only lets family admins use the Classroom tools', function () {
    $teacher = classroomTeacher();
    actingAsMember(addMember($teacher->family, 10));

    $this->postJson('/api/v1/family/classroom/consent', ['version' => LegalDocuments::classroomConsentVersion()])->assertForbidden()->assertJsonPath('code', 'forbidden');
    $this->getJson('/api/v1/family/classroom/summary')->assertForbidden()->assertJsonPath('code', 'forbidden');
});

it('needs the current consent before roster, cards, summary and new year', function () {
    classroomTeacher(consented: false);

    $this->postJson('/api/v1/family/classroom/roster', [])->assertStatus(409)->assertJsonPath('code', 'classroom_consent_required');
    $this->postJson('/api/v1/family/classroom/sign-in-codes')->assertStatus(409)->assertJsonPath('code', 'classroom_consent_required');
    $this->getJson('/api/v1/family/classroom/summary')->assertStatus(409)->assertJsonPath('code', 'classroom_consent_required');
    $this->get('/api/v1/family/classroom/summary.csv')->assertStatus(409)->assertJsonPath('code', 'classroom_consent_required');
    $this->postJson('/api/v1/family/classroom/new-year', [])->assertStatus(409)->assertJsonPath('code', 'classroom_consent_required');
});

it('does not count a consent from an older version or from a former admin', function () {
    $teacher = classroomTeacher(consented: false);
    Consent::factory()->create(['user_id' => $teacher->user_id, 'type' => Consent::CLASSROOM, 'version' => '2020-01-01', 'accepted_at' => now()->subYear()]);

    // The state shows the newest version an admin accepted; the tools need the current one.
    $this->getJson('/api/v1/family')->assertJsonPath('data.family.classroom.consent_version', '2020-01-01');
    $this->getJson('/api/v1/family/classroom/summary')->assertStatus(409);

    $coTeacher = addMember($teacher->family, 35, FamilyMember::ROLE_ADMIN);
    Consent::factory()->create(['user_id' => $coTeacher->user_id, 'type' => Consent::CLASSROOM, 'version' => LegalDocuments::classroomConsentVersion()]);

    $this->getJson('/api/v1/family/classroom/summary')->assertOk();

    $coTeacher->forceFill(['role' => FamilyMember::ROLE_MEMBER])->save();
    $this->getJson('/api/v1/family/classroom/summary')->assertStatus(409);
});

it('answers plan_required for every Classroom tool on other tiers', function (string $tier) {
    actingAsMember(familyOwner(['tier' => $tier]));

    $this->postJson('/api/v1/family/classroom/roster', [])->assertForbidden()->assertJsonPath('code', 'plan_required');
    $this->postJson('/api/v1/family/classroom/sign-in-codes')->assertForbidden()->assertJsonPath('code', 'plan_required');
    $this->getJson('/api/v1/family/classroom/summary')->assertForbidden()->assertJsonPath('code', 'plan_required');
    $this->postJson('/api/v1/family/classroom/new-year', [])->assertForbidden()->assertJsonPath('code', 'plan_required');
})->with(['free', 'family', 'big_clan']);

// ---------------------------------------------------------------------------------------------
// Roster
// ---------------------------------------------------------------------------------------------

it('adds students in one go, without invites or logins', function () {
    $teacher = classroomTeacher();

    $response = $this->postJson('/api/v1/family/classroom/roster', [
        'birth_year' => 2016,
        'students' => [['nickname' => ' Ava '], ['nickname' => 'Noah', 'birth_year' => 2015]],
    ])->assertCreated()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.nickname', 'Ava')
        ->assertJsonPath('data.0.role', 'member')
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('data.0.birth_year', 2016)
        ->assertJsonPath('data.0.birth_month', 6)
        ->assertJsonPath('data.0.has_own_login', false)
        ->assertJsonPath('data.1.birth_year', 2015);

    $user = User::query()->findOrFail($response->json('data.0.user_id'));
    expect($user->email)->toBeNull()
        ->and($user->password)->toBeNull()
        ->and(Invite::query()->count())->toBe(0)
        ->and(Consent::query()->where('type', Consent::PARENTAL)->count())->toBe(0);

    $this->getJson('/api/v1/family')->assertJsonPath('data.family.classroom.students_count', 2)
        ->assertJsonPath('data.family.member_count', 3);
    expect($teacher->family->members()->count())->toBe(3);
});

it('validates the roster', function () {
    $teacher = classroomTeacher();
    addMember($teacher->family, 9)->forceFill(['nickname' => 'Ava'])->save();

    $this->postJson('/api/v1/family/classroom/roster', ['birth_year' => 2016, 'students' => [['nickname' => 'ava']]])
        ->assertUnprocessable()->assertJsonValidationErrors('students.0.nickname');
    $this->postJson('/api/v1/family/classroom/roster', ['birth_year' => 2016, 'students' => [['nickname' => 'Leo'], ['nickname' => 'LEO']]])
        ->assertUnprocessable()->assertJsonValidationErrors('students.1.nickname');
    $this->postJson('/api/v1/family/classroom/roster', ['birth_year' => 2016, 'students' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('students');
    $this->postJson('/api/v1/family/classroom/roster', ['birth_year' => 2016, 'students' => [['nickname' => str_repeat('a', 41)]]])
        ->assertUnprocessable()->assertJsonValidationErrors('students.0.nickname');
    $this->postJson('/api/v1/family/classroom/roster', ['students' => [['nickname' => 'Leo']]])
        ->assertUnprocessable()->assertJsonValidationErrors('birth_year');
    $this->postJson('/api/v1/family/classroom/roster', [
        'birth_year' => 2016,
        'students' => array_map(fn (int $i) => ['nickname' => "Student {$i}"], range(1, 36)),
    ])->assertUnprocessable()->assertJsonValidationErrors('students');
});

it('refuses a roster that would pass the member limit, counting pending invites', function () {
    $teacher = classroomTeacher();
    $max = app(PolicyResolver::class)->maxMembers($teacher->family);
    Invite::factory()->create(['family_id' => $teacher->family_id]);

    // owner + 1 pending invite + ($max - 2) students = $max: fits exactly.
    rosterStudents(array_map(fn (int $i) => "Student {$i}", range(1, min(35, $max - 2))));

    $this->postJson('/api/v1/family/classroom/roster', ['birth_year' => 2016, 'students' => [['nickname' => 'One too many']]])
        ->assertForbidden()->assertJsonPath('code', 'member_limit_reached');
});

it('counts adult-aged roster students against the adult seats', function () {
    $teacher = classroomTeacher();
    addMember($teacher->family, 35, FamilyMember::ROLE_ADMIN);

    $this->postJson('/api/v1/family/classroom/roster', ['birth_year' => now()->year - 30, 'students' => [['nickname' => 'Grown-up']]])
        ->assertForbidden()->assertJsonPath('code', 'adult_limit_reached');
});

// ---------------------------------------------------------------------------------------------
// Code cards
// ---------------------------------------------------------------------------------------------

it('issues 14-day code cards for every student, voiding older codes', function () {
    Carbon::setTestNow('2026-11-21 10:00:00');
    $teacher = classroomTeacher();
    [$noah, $ava] = rosterStudents(['Noah', 'Ava']);
    $old = SignInCode::factory()->create(['member_id' => $ava]);

    $response = $this->postJson('/api/v1/family/classroom/sign-in-codes')->assertCreated()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.nickname', 'Ava')
        ->assertJsonPath('data.0.member_id', $ava)
        ->assertJsonPath('data.0.expires_at', '2026-12-05T10:00:00Z')
        ->assertJsonPath('data.1.nickname', 'Noah');

    expect($old->refresh()->voided_at)->not->toBeNull()
        ->and(SignInCode::query()->where('member_id', $ava)->usable()->count())->toBe(1);

    $this->getJson('/api/v1/family')->assertJsonPath('data.family.classroom.codes_issued_at', '2026-11-21T10:00:00Z');

    // A card is a normal one-time sign-in code.
    app('auth')->forgetGuards();
    $this->postJson('/api/v1/auth/code', ['code' => $response->json('data.1.code'), 'device_name' => 'Class iPad', 'platform' => 'ios'])
        ->assertOk()->assertJsonPath('data.user.display_name', 'Noah');

    Carbon::setTestNow();
});

it('issues cards for chosen members only and rejects members of other families', function () {
    $teacher = classroomTeacher();
    [$first] = rosterStudents(['Ava', 'Noah']);
    $stranger = addMember(familyOwner()->family, 9);

    $this->postJson('/api/v1/family/classroom/sign-in-codes', ['member_ids' => [$first]])->assertCreated()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.member_id', $first);
    $this->postJson('/api/v1/family/classroom/sign-in-codes', ['member_ids' => [$first, $stranger->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('member_ids');
});

it('rate-limits code cards to 10 per hour per family', function () {
    classroomTeacher();
    rosterStudents(['Ava']);

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/family/classroom/sign-in-codes')->assertCreated();
    }

    $this->postJson('/api/v1/family/classroom/sign-in-codes')->assertStatus(429)->assertJsonPath('code', 'rate_limited');
});

it('reads the card lifetime from the tier setting', function () {
    expect(app(PolicyResolver::class)->signInCodeCardTtlDays(Family::TIER_CLASSROOM))->toBe(14);

    Setting::query()->where('key', 'sign_in_codes.card_ttl_days')->delete();
    $migration = require database_path('migrations/2026_11_21_100100_add_card_ttl_tier_settings.php');
    $migration->up();
    $migration->up();

    expect(Setting::query()->where('key', 'sign_in_codes.card_ttl_days')->where('scope', 'tier')->where('scope_id', 'classroom')->value('value'))->toBe(14);
});

// ---------------------------------------------------------------------------------------------
// Class summary
// ---------------------------------------------------------------------------------------------

/**
 * A session for the member with `$correct` right and `$wrong` wrong regular answers.
 */
function summarySession(FamilyMember $member, int $correct, int $wrong): void
{
    $category = Category::query()->first() ?? Category::factory()->create();
    $session = PlaySession::query()->create([
        'family_id' => $member->family_id, 'member_id' => $member->id, 'category_id' => $category->id,
        'target_points' => 10, 'blunder_cap' => 3, 'mistakes' => $wrong, 'points' => $correct,
        'second_chances_used' => 0, 'redemption_failed' => false, 'status' => PlaySession::STATUS_COMPLETED,
        'started_at' => now(), 'completed_at' => now(),
    ]);

    foreach ([...array_fill(0, $correct, true), ...array_fill(0, $wrong, false)] as $position => $isCorrect) {
        SessionItem::query()->forceCreate([
            'play_session_id' => $session->id, 'position' => $position, 'is_redemption' => false, 'points' => 1,
            'is_correct' => $isCorrect, 'points_awarded' => $isCorrect ? 1 : 0, 'answered_at' => now(),
        ]);
    }
}

it('summarises the class per member', function () {
    Carbon::setTestNow('2026-11-21 10:00:00');
    $teacher = classroomTeacher();
    [$avaId] = rosterStudents(['Ava']);
    $ava = FamilyMember::query()->findOrFail($avaId);
    earnPoints($ava, 12);
    earnPoints($ava, -2);
    summarySession($ava, 3, 1);
    setStreak($ava, 9, 3);

    $this->getJson('/api/v1/family/classroom/summary')->assertOk()
        ->assertJsonPath('data.generated_at', '2026-11-21T10:00:00Z')
        ->assertJsonPath('data.season_started_at', null)
        ->assertJsonCount(2, 'data.rows')
        ->assertJsonPath('data.rows.0.nickname', 'Ava')
        ->assertJsonPath('data.rows.0.member_id', $avaId)
        ->assertJsonPath('data.rows.0.role', 'member')
        ->assertJsonPath('data.rows.0.points', 10)
        ->assertJsonPath('data.rows.0.games', 1)
        ->assertJsonPath('data.rows.0.accuracy_percent', 75)
        ->assertJsonPath('data.rows.0.longest_streak', 9)
        ->assertJsonPath('data.rows.0.last_played_at', '2026-11-21T10:00:00Z')
        ->assertJsonPath('data.rows.1.role', 'admin')
        ->assertJsonPath('data.rows.1.games', 0)
        ->assertJsonPath('data.rows.1.accuracy_percent', null);

    $this->getJson('/api/v1/family/classroom/summary?period=year')->assertUnprocessable()->assertJsonValidationErrors('period');
    Carbon::setTestNow();
});

it('exports the summary as CSV with headers in the requester\'s locale', function () {
    $teacher = classroomTeacher();
    $this->postJson('/api/v1/family/classroom/roster', ['birth_year' => 2016, 'students' => [['nickname' => '=SUM(A1)'], ['nickname' => 'Zoé']]])->assertCreated();

    $response = $this->get('/api/v1/family/classroom/summary.csv')->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment; filename=class-summary-');

    $csv = $response->streamedContent();
    $lines = preg_split('/\R/', trim(substr($csv, 3)));
    expect(substr($csv, 0, 3))->toBe("\xEF\xBB\xBF")
        ->and($lines[0])->toStartWith('"Member ID",Nickname,Role,Points')
        ->and($csv)->toContain(",'=SUM(A1),member,")
        ->and($csv)->toContain(',Zoé,member,0,0,,0,0,');

    $teacher->user->forceFill(['locale' => 'fr'])->save();
    $csv = $this->get('/api/v1/family/classroom/summary.csv')->assertOk()->streamedContent();
    expect($csv)->toContain(implode(',', array_map(fn (string $h) => str_contains($h, ' ') ? "\"{$h}\"" : $h, array_slice(ClassroomSummaryCsv::HEADERS['fr'], 0, 3))));
});

it('builds the summary with a constant number of queries', function () {
    $count = function (int $students): int {
        app('auth')->forgetGuards();
        $teacher = classroomTeacher();
        $ids = rosterStudents(array_map(fn (int $i) => "Kid {$i}", range(1, $students)));

        foreach ($ids as $id) {
            $member = FamilyMember::query()->findOrFail($id);
            earnPoints($member, 5);
            summarySession($member, 2, 1);
            setStreak($member, 4, 1);
        }

        $this->getJson('/api/v1/family/classroom/summary')->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/family/classroom/summary')->assertOk();
        $this->get('/api/v1/family/classroom/summary.csv')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    expect($count(2))->toBe($count(6));
});

it('shows the family with the classroom state in a constant number of queries', function () {
    $count = function (int $students): int {
        app('auth')->forgetGuards();
        classroomTeacher();
        rosterStudents(array_map(fn (int $i) => "Kid {$i}", range(1, $students)));
        $this->getJson('/api/v1/family')->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/family')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    expect($count(2))->toBe($count(6));
});

it('issues code cards in a constant number of queries', function () {
    $count = function (int $students): int {
        app('auth')->forgetGuards();
        classroomTeacher();
        rosterStudents(array_map(fn (int $i) => "Kid {$i}", range(1, $students)));
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->postJson('/api/v1/family/classroom/sign-in-codes')->assertCreated();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    expect($count(2))->toBe($count(6));
});

// ---------------------------------------------------------------------------------------------
// Archive & start a new year
// ---------------------------------------------------------------------------------------------

it('needs the class name and at least one flag', function () {
    classroomTeacher();

    $this->postJson('/api/v1/family/classroom/new-year', newYearBody(['points' => true], 'Grade 4A'))
        ->assertUnprocessable()->assertJsonValidationErrors('confirm_name');
    $this->postJson('/api/v1/family/classroom/new-year', newYearBody(['points' => false, 'chat' => false]))
        ->assertUnprocessable()->assertJsonValidationErrors('clear');
    $this->postJson('/api/v1/family/classroom/new-year', ['confirm_name' => 'Grade 4B'])
        ->assertUnprocessable()->assertJsonValidationErrors('clear');
    $this->postJson('/api/v1/family/classroom/new-year', newYearBody(['points' => true], '  grade 4b '))->assertOk();
});

it('starts a new points season without touching the ledger', function () {
    Carbon::setTestNow('2026-06-30 12:00:00');
    $teacher = classroomTeacher();
    [$avaId] = rosterStudents(['Ava']);
    $ava = FamilyMember::query()->findOrFail($avaId);
    earnPoints($ava, 40);
    $ledger = PointEntry::query()->count();

    Carbon::setTestNow('2026-09-01 12:00:00');
    $this->postJson('/api/v1/family/classroom/new-year', newYearBody(['points' => true]))->assertOk()
        ->assertJsonPath('data.classroom.season_started_at', '2026-09-01T12:00:00Z')
        ->assertJsonPath('data.classroom.students_count', 1);

    Carbon::setTestNow('2026-09-02 12:00:00');
    earnPoints($ava, 5);

    expect(PointEntry::query()->count())->toBe($ledger + 1)
        ->and(PointEntry::query()->where('member_id', $avaId)->sum('amount'))->toEqual(45);

    $this->getJson('/api/v1/family/classroom/summary')->assertOk()
        ->assertJsonPath('data.season_started_at', '2026-09-01T12:00:00Z')
        ->assertJsonPath('data.rows.0.points', 5);
    $this->getJson('/api/v1/family/classroom/summary?period=all')->assertJsonPath('data.rows.0.points', 45);
    $this->getJson('/api/v1/family/leaderboard?period=all')->assertOk()
        ->assertJsonPath('data.team_total', 5)
        ->assertJsonPath('data.starts_at', '2026-09-01T12:00:00Z');
    $this->getJson('/api/v1/family/leaderboard?period=month')->assertJsonPath('data.team_total', 5);

    // Lifetime values do not change.
    actingAsMember($ava);
    $this->getJson('/api/v1/me/stats')->assertOk()->assertJsonPath('data.points_total', 45);
    Carbon::setTestNow();
});

it('counts goal progress from the season start', function () {
    Carbon::setTestNow('2026-09-01 12:00:00');
    $teacher = classroomTeacher();
    [$avaId] = rosterStudents(['Ava']);
    $ava = FamilyMember::query()->findOrFail($avaId);

    $plan = $this->postJson('/api/v1/goals', [
        'name' => 'Class goal', 'mode' => 'cooperation', 'period_type' => 'monthly', 'target_points' => 100,
        'reward_text' => 'Extra recess', 'starts_on' => '2026-09-01', 'all_categories' => true,
    ])->assertCreated()->json('data.id');

    Carbon::setTestNow('2026-09-02 12:00:00');
    earnPoints($ava, 30);
    $this->getJson("/api/v1/goals/{$plan}")->assertJsonPath('data.current_period.team_total', 30);

    Carbon::setTestNow('2026-09-03 12:00:00');
    $this->postJson('/api/v1/family/classroom/new-year', newYearBody(['points' => true]))->assertOk();
    earnPoints($ava, 4);

    $this->getJson("/api/v1/goals/{$plan}")->assertJsonPath('data.current_period.team_total', 4);
    Carbon::setTestNow();
});

it('removes students, chat and goals, and logs it without names', function () {
    $teacher = classroomTeacher();
    $coTeacher = addMember($teacher->family, 35, FamilyMember::ROLE_ADMIN);
    [$avaId, $noahId] = rosterStudents(['Ava', 'Noah']);
    $ava = FamilyMember::query()->with('user')->findOrFail($avaId);
    earnPoints($ava, 7);
    ChatMessage::factory()->by($teacher)->count(3)->create();
    $otherFamilyMessage = ChatMessage::factory()->create();
    GoalPlan::factory()->count(2)->create(['family_id' => $teacher->family_id]);
    $ledger = PointEntry::query()->count();

    $this->postJson('/api/v1/family/classroom/new-year', newYearBody(['students' => true, 'chat' => true, 'goals' => true]))->assertOk()
        ->assertJsonPath('data.classroom.students_count', 0)
        ->assertJsonPath('data.classroom.season_started_at', null)
        ->assertJsonPath('data.member_count', 2);

    expect(FamilyMember::query()->whereKey([$avaId, $noahId])->pluck('status')->unique()->all())->toBe([FamilyMember::STATUS_REMOVED])
        ->and($ava->user->refresh()->erased_at)->not->toBeNull()
        ->and($coTeacher->refresh()->status)->toBe(FamilyMember::STATUS_ACTIVE)
        ->and(ChatMessage::query()->where('family_id', $teacher->family_id)->count())->toBe(0)
        ->and($otherFamilyMessage->fresh())->not->toBeNull()
        ->and(GoalPlan::query()->where('family_id', $teacher->family_id)->count())->toBe(0)
        ->and(GoalPlan::withTrashed()->where('family_id', $teacher->family_id)->count())->toBe(2)
        ->and(PointEntry::query()->count())->toBe($ledger);

    $log = Activity::query()->where('event', 'classroom_new_year')->sole();
    expect($log->properties['clear'])->toBe(['students' => true, 'points' => false, 'chat' => true, 'goals' => true])
        ->and($log->properties['students_removed'])->toBe(2)
        ->and(json_encode($log->properties))->not->toContain('Ava')
        ->and(json_encode($log->properties))->not->toContain('Grade 4B');
});
