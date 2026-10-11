<?php

use App\Modules\Content\Models\Category;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Scoring\Actions\AppendPoints;
use App\Modules\Scoring\Actions\BuildLeaderboard;
use App\Modules\Scoring\Actions\RecordDailyActivity;
use App\Modules\Scoring\Models\PointEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Append a ledger row at a given UTC time.
 */
function award(FamilyMember $member, int $amount, string $at, ?Category $category = null): PointEntry
{
    Carbon::setTestNow($at);
    $entry = app(AppendPoints::class)->handle($member->family_id, $member->id, ($category ?? Category::factory()->create())->id, $amount, PointEntry::REASON_CORRECT_ANSWER);
    Carbon::setTestNow();

    return $entry;
}

afterEach(fn () => Carbon::setTestNow());

it('needs a family for stats and leaderboard', function () {
    $this->getJson('/api/v1/me/stats')->assertStatus(401);
    $this->getJson('/api/v1/family/leaderboard')->assertStatus(401);
});

it('returns the member stats in the family timezone', function () {
    // Toronto is UTC-4 in October. "Now" = Wednesday 2026-10-14 10:00 local.
    $owner = familyOwner();
    award($owner, 2, '2026-10-14 13:00:00');        // today (09:00 local)
    award($owner, 1, '2026-10-14 03:59:00');        // yesterday 23:59 local, same week
    award($owner, 3, '2026-10-12 04:00:00');        // Monday 00:00 local, week start
    award($owner, 5, '2026-10-12 03:59:00');        // Sunday 23:59 local, previous week
    award(addMember($owner->family, 30), 50, '2026-10-14 13:00:00'); // someone else

    Carbon::setTestNow('2026-10-14 14:00:00');
    app(RecordDailyActivity::class)->handle($owner, CarbonImmutable::parse('2026-10-13 14:00:00'));
    app(RecordDailyActivity::class)->handle($owner, CarbonImmutable::parse('2026-10-14 14:00:00'));
    actingAsMember($owner);

    $this->getJson('/api/v1/me/stats')
        ->assertOk()
        ->assertExactJson(['data' => [
            'points_today' => 2,
            'points_week' => 6,
            'points_total' => 11,
            'streak' => ['current' => 2, 'longest' => 2],
        ]]);
});

it('tracks streaks: same day, next day, gap and expiry', function () {
    $owner = familyOwner();
    $record = app(RecordDailyActivity::class);

    $record->handle($owner, CarbonImmutable::parse('2026-10-10 15:00:00'));
    $record->handle($owner, CarbonImmutable::parse('2026-10-10 20:00:00'));
    $record->handle($owner, CarbonImmutable::parse('2026-10-11 15:00:00'));
    $streak = $record->handle($owner, CarbonImmutable::parse('2026-10-12 15:00:00'));
    expect([$streak->current, $streak->longest])->toBe([3, 3]);

    $streak = $record->handle($owner, CarbonImmutable::parse('2026-10-15 15:00:00'));
    expect([$streak->current, $streak->longest])->toBe([1, 3]);

    Carbon::setTestNow('2026-10-16 15:00:00');
    expect($record->current($owner))->toBe(['current' => 1, 'longest' => 3]);
    Carbon::setTestNow('2026-10-17 15:00:00');
    expect($record->current($owner))->toBe(['current' => 0, 'longest' => 3]);
});

it('builds the weekly leaderboard with shared ranks for ties', function () {
    $owner = familyOwner();
    $owner->update(['nickname' => 'Mom']);
    $owner->user->update(['avatar' => 'owl']);
    $sam = addMember($owner->family, 9);
    $sam->update(['nickname' => 'Sam']);
    $alex = addMember($owner->family, 14);
    $alex->update(['nickname' => 'Alex']);
    $dad = addMember($owner->family, 40);
    $dad->update(['nickname' => 'Dad']);

    award($owner, 5, '2026-10-13 15:00:00');
    award($sam, 5, '2026-10-14 15:00:00');
    award($alex, 7, '2026-10-14 15:00:00');
    award($dad, 9, '2026-10-01 15:00:00'); // earlier this month, not this week

    Carbon::setTestNow('2026-10-14 16:00:00');
    actingAsMember($sam);

    $this->getJson('/api/v1/family/leaderboard')
        ->assertOk()
        ->assertJsonPath('data.period', 'week')
        ->assertJsonPath('data.starts_at', '2026-10-12T04:00:00Z')
        ->assertJsonPath('data.ends_at', '2026-10-19T04:00:00Z')
        ->assertJsonPath('data.team_total', 17)
        ->assertJsonPath('data.entries', [
            ['member_id' => $alex->id, 'nickname' => 'Alex', 'avatar' => $alex->user->avatar, 'points' => 7, 'rank' => 1],
            ['member_id' => $owner->id, 'nickname' => 'Mom', 'avatar' => 'owl', 'points' => 5, 'rank' => 2],
            ['member_id' => $sam->id, 'nickname' => 'Sam', 'avatar' => $sam->user->avatar, 'points' => 5, 'rank' => 2],
            ['member_id' => $dad->id, 'nickname' => 'Dad', 'avatar' => $dad->user->avatar, 'points' => 0, 'rank' => 4],
        ]);

    $this->getJson('/api/v1/family/leaderboard?period=month')->assertJsonPath('data.team_total', 26)->assertJsonPath('data.entries.0.member_id', $dad->id);
    $this->getJson('/api/v1/family/leaderboard?period=today')->assertJsonPath('data.team_total', 12);
    $this->getJson('/api/v1/family/leaderboard?period=all')
        ->assertJsonPath('data.starts_at', null)
        ->assertJsonPath('data.ends_at', null)
        ->assertJsonPath('data.team_total', 26);
    $this->getJson('/api/v1/family/leaderboard?period=year')->assertUnprocessable()->assertJsonValidationErrors('period');
});

it('never mixes families on the leaderboard', function () {
    $mine = familyOwner();
    $other = familyOwner();
    award($other, 10, now()->toDateTimeString());

    expect(app(BuildLeaderboard::class)->handle($mine->family, 'all'))
        ->team_total->toBe(0)
        ->entries->toHaveCount(1);
});

it('ranks competition style', function () {
    expect(array_column(BuildLeaderboard::rank([['points' => 9], ['points' => 9], ['points' => 4], ['points' => 0], ['points' => 0]]), 'rank'))
        ->toBe([1, 1, 3, 4, 4]);
});
