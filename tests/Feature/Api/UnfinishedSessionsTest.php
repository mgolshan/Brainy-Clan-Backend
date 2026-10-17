<?php

use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Play\Actions\AbandonIdleSessions;
use App\Modules\Play\Models\PlaySession;
use App\Modules\Play\Models\SessionItem;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

/**
 * Unfinished games (docs/PLAN.md, 2026-10-17): `GET /play/sessions/current`, a new game abandons
 * older unfinished ones, idle games are abandoned after 24 h.
 */

/**
 * A playable category (multiple-choice items, "a" correct) and a signed-in family-tier member.
 *
 * @return array{0: FamilyMember, 1: Category}
 */
function unfinishedSetup(): array
{
    $category = Category::factory()->create();
    ContentItem::factory()->count(20)->create(['category_id' => $category->id, 'difficulty' => 2]);
    ContentItem::factory()->count(5)->create(['category_id' => $category->id, 'difficulty' => 1]);

    $member = familyOwner(['tier' => 'family']);
    actingAsMember($member);

    return [$member, $category];
}

function unfinishedStart(Category $category): int
{
    return test()->postJson('/api/v1/play/sessions', ['category_id' => $category->id])->assertCreated()->json('data.session.id');
}

/**
 * A session row without items (for the idle sweep).
 *
 * @param  array<string, mixed>  $attributes
 */
function idleSession(FamilyMember $member, Category $category, array $attributes = []): PlaySession
{
    return PlaySession::query()->create([
        'family_id' => $member->family_id,
        'member_id' => $member->id,
        'category_id' => $category->id,
        'target_points' => 10,
        'blunder_cap' => 3,
        'status' => PlaySession::STATUS_ACTIVE,
        'started_at' => now(),
        ...$attributes,
    ]);
}

function idleAnswerAt(PlaySession $session, int $position, CarbonImmutable $at): void
{
    SessionItem::query()->create([
        'play_session_id' => $session->id,
        'position' => $position,
        'response' => ['choice_id' => 'a'],
        'is_correct' => true,
        'answered_at' => $at,
    ]);
}

it('returns null when the member has no unfinished session', function () {
    [$member, $category] = unfinishedSetup();

    $this->getJson('/api/v1/play/sessions/current')->assertOk()->assertExactJson(['data' => null]);

    $id = unfinishedStart($category);
    PlaySession::query()->whereKey($id)->update(['status' => PlaySession::STATUS_COMPLETED, 'completed_at' => now()]);
    $this->getJson('/api/v1/play/sessions/current')->assertOk()->assertExactJson(['data' => null]);

    PlaySession::query()->whereKey($id)->update(['status' => PlaySession::STATUS_ABANDONED]);
    $this->getJson('/api/v1/play/sessions/current')->assertOk()->assertExactJson(['data' => null]);
});

it('needs auth and a family', function () {
    $this->getJson('/api/v1/play/sessions/current')->assertUnauthorized();
});

it('returns the unfinished session with the same payload as GET session', function (string $status) {
    [$member, $category] = unfinishedSetup();
    $id = unfinishedStart($category);
    PlaySession::query()->whereKey($id)->update(['status' => $status]);

    $current = $this->getJson('/api/v1/play/sessions/current')->assertOk()->json();
    $show = $this->getJson("/api/v1/play/sessions/{$id}")->assertOk()->json();

    expect($current)->toBe($show)
        ->and($current['data']['session']['id'])->toBe($id)
        ->and($current['data']['session']['status'])->toBe($status)
        ->and($current['data']['items'])->toHaveCount(13);
})->with([PlaySession::STATUS_ACTIVE, PlaySession::STATUS_FAILED, PlaySession::STATUS_REDEMPTION]);

it('returns the most recent unfinished session', function () {
    [$member, $category] = unfinishedSetup();
    $older = idleSession($member, $category, ['started_at' => now()->subHours(2)]);
    $newer = idleSession($member, $category, ['started_at' => now()->subHour(), 'status' => PlaySession::STATUS_FAILED]);
    idleSession($member, $category, ['status' => PlaySession::STATUS_COMPLETED]);

    $this->getJson('/api/v1/play/sessions/current')->assertOk()->assertJsonPath('data.session.id', $newer->id);
    expect($older->id)->not->toBe($newer->id);
});

it('does not return another member\'s session', function () {
    [$member, $category] = unfinishedSetup();
    $sibling = addMember($member->family, 9);
    idleSession($sibling, $category);

    $this->getJson('/api/v1/play/sessions/current')->assertOk()->assertExactJson(['data' => null]);
});

it('does not clash with GET /play/sessions/{session}', function () {
    [$member, $category] = unfinishedSetup();
    $id = unfinishedStart($category);

    $this->getJson("/api/v1/play/sessions/{$id}")->assertOk()->assertJsonPath('data.session.id', $id);
    $this->getJson('/api/v1/play/sessions/current')->assertOk()->assertJsonPath('data.session.id', $id);
});

it('abandons the previous unfinished sessions when a new one starts', function () {
    [$member, $category] = unfinishedSetup();
    $sibling = addMember($member->family, 9);
    $siblingSession = idleSession($sibling, $category);
    $failed = idleSession($member, $category, ['status' => PlaySession::STATUS_FAILED]);
    $completed = idleSession($member, $category, ['status' => PlaySession::STATUS_COMPLETED]);
    $first = unfinishedStart($category);

    $second = unfinishedStart($category);

    expect(PlaySession::query()->find($first)->status)->toBe(PlaySession::STATUS_ABANDONED)
        ->and($failed->refresh()->status)->toBe(PlaySession::STATUS_ABANDONED)
        ->and($completed->refresh()->status)->toBe(PlaySession::STATUS_COMPLETED)
        ->and($siblingSession->refresh()->status)->toBe(PlaySession::STATUS_ACTIVE)
        ->and(PlaySession::query()->find($second)->status)->toBe(PlaySession::STATUS_ACTIVE);

    $this->getJson('/api/v1/play/sessions/current')->assertOk()->assertJsonPath('data.session.id', $second);
    $this->postJson("/api/v1/play/sessions/{$first}/abandon")->assertStatus(409)->assertJsonPath('code', 'session_not_active');
});

it('keeps the previous session when the new one cannot start', function () {
    [$member, $category] = unfinishedSetup();
    $first = unfinishedStart($category);
    $empty = Category::factory()->create();

    $this->postJson('/api/v1/play/sessions', ['category_id' => $empty->id])->assertStatus(422);

    expect(PlaySession::query()->find($first)->status)->toBe(PlaySession::STATUS_ACTIVE);
});

it('abandons sessions idle for more than 24 hours', function () {
    [$member, $category] = unfinishedSetup();
    $now = CarbonImmutable::parse('2026-10-17 12:00:00');
    $this->travelTo($now);

    $idleNoAnswers = idleSession($member, $category, ['started_at' => $now->subHours(24)->subSecond()]);
    $exactly24h = idleSession($member, $category, ['started_at' => $now->subHours(24)]);
    $recentStart = idleSession($member, $category, ['started_at' => $now->subHours(23)]);
    $idleFailed = idleSession($member, $category, ['status' => PlaySession::STATUS_FAILED, 'started_at' => $now->subDays(3)]);
    $idleRedemption = idleSession($member, $category, ['status' => PlaySession::STATUS_REDEMPTION, 'started_at' => $now->subDays(3)]);
    $oldAnswers = idleSession($member, $category, ['started_at' => $now->subDays(2)]);
    idleAnswerAt($oldAnswers, 1, $now->subDays(2)->addMinute());
    idleAnswerAt($oldAnswers, 2, $now->subHours(25));
    $recentAnswer = idleSession($member, $category, ['started_at' => $now->subDays(2)]);
    idleAnswerAt($recentAnswer, 1, $now->subDays(2)->addMinute());
    idleAnswerAt($recentAnswer, 2, $now->subHours(23));
    $answerAt24h = idleSession($member, $category, ['started_at' => $now->subDays(2)]);
    idleAnswerAt($answerAt24h, 1, $now->subHours(24));
    $completed = idleSession($member, $category, ['status' => PlaySession::STATUS_COMPLETED, 'started_at' => $now->subDays(3)]);

    $this->artisan('play:abandon-idle-sessions')->expectsOutput('Abandoned 4 idle session(s).')->assertSuccessful();

    $status = fn (PlaySession $s) => $s->refresh()->status;
    expect($status($idleNoAnswers))->toBe(PlaySession::STATUS_ABANDONED)
        ->and($status($idleFailed))->toBe(PlaySession::STATUS_ABANDONED)
        ->and($status($idleRedemption))->toBe(PlaySession::STATUS_ABANDONED)
        ->and($status($oldAnswers))->toBe(PlaySession::STATUS_ABANDONED)
        ->and($status($exactly24h))->toBe(PlaySession::STATUS_ACTIVE)
        ->and($status($recentStart))->toBe(PlaySession::STATUS_ACTIVE)
        ->and($status($recentAnswer))->toBe(PlaySession::STATUS_ACTIVE)
        ->and($status($answerAt24h))->toBe(PlaySession::STATUS_ACTIVE)
        ->and($status($completed))->toBe(PlaySession::STATUS_COMPLETED);
});

it('abandons idle sessions in batches with a constant number of queries per batch', function () {
    [$member, $category] = unfinishedSetup();
    $old = now()->subDays(2);

    $count = function (int $sessions) use ($member, $category, $old): int {
        for ($i = 0; $i < $sessions; $i++) {
            $session = idleSession($member, $category, ['started_at' => $old]);
            idleAnswerAt($session, 1, CarbonImmutable::parse($old)->addMinute());
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $abandoned = app(AbandonIdleSessions::class)->handle();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($abandoned)->toBe($sessions);

        return $queries;
    };

    expect($count(2))->toBe($count(8))->toBe(2);
});

it('runs the idle sweep hourly', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'play:abandon-idle-sessions'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 * * * *');
});

it('loads the current session with a constant number of queries', function () {
    [$member, $category] = unfinishedSetup();

    $queries = function () use ($member): int {
        app('auth')->forgetGuards();
        actingAsMember($member);
        test()->getJson('/api/v1/play/sessions/current')->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        test()->getJson('/api/v1/play/sessions/current')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $id = unfinishedStart($category);
    $few = $queries();

    $items = SessionItem::query()->where('play_session_id', $id)->orderBy('position')->get();
    $items->take(2)->each(fn (SessionItem $item) => $item->forceFill(['response' => ['choice_id' => 'a'], 'is_correct' => true, 'answered_at' => now()])->save());
    idleSession($member, $category, ['started_at' => now()->subHour()]);
    idleSession($member, $category, ['status' => PlaySession::STATUS_COMPLETED]);

    expect($queries())->toBe($few)->toBeLessThanOrEqual(15);
});
