<?php

use App\Modules\Content\Models\Category;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Goals\Events\GoalReached;
use App\Modules\Goals\Events\PeriodClosed;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Identity\Models\Device;
use App\Modules\Notifications\Actions\SendDailyReminders;
use App\Modules\Notifications\Contracts\ExpoPushSender;
use App\Modules\Notifications\Jobs\DeliverPushNotification;
use App\Modules\Notifications\Listeners\NotifyFamilyActivity;
use App\Modules\Notifications\Listeners\NotifyGoalReached;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Notifications\Support\HttpExpoPushSender;
use App\Modules\Play\Events\SessionCompleted;
use App\Modules\Play\Models\PlaySession;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * B8: sending push notifications (goal_reached, period_closed) through a fake Expo sender.
 */
class FakeExpoPushSender implements ExpoPushSender
{
    /** @var list<array{to: string, title: string, body: string, data: array<string, mixed>}> */
    public array $sent = [];

    /** @var list<string> */
    public array $invalid = [];

    public function send(array $messages): array
    {
        array_push($this->sent, ...$messages);

        return array_values(array_intersect($this->invalid, array_column($messages, 'to')));
    }
}

/**
 * A family (Berlin time) with a cooperation plan whose members each have one device with a push token.
 *
 * @return array{0: GoalPlan, 1: list<FamilyMember>}
 */
function pushPlan(int $extraMembers = 1): array
{
    $owner = familyOwner(['tier' => 'family', 'timezone' => 'Europe/Berlin']);
    $members = [$owner];

    for ($i = 0; $i < $extraMembers; $i++) {
        $members[] = addMember($owner->family, 9);
    }

    $plan = GoalPlan::factory()->create(['family_id' => $owner->family_id, 'name' => 'Pizza goal']);

    foreach ($members as $member) {
        $plan->planMembers()->create(['member_id' => $member->id]);
        Device::factory()->create(['user_id' => $member->user_id, 'push_token' => "ExponentPushToken[m{$member->id}]"]);
    }

    return [$plan, $members];
}

beforeEach(function () {
    $this->sender = new FakeExpoPushSender;
    app()->instance(ExpoPushSender::class, $this->sender);
    // Midday in Berlin: outside everyone's quiet hours.
    $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00', 'Europe/Berlin'));
});

it('sends goal_reached to plan members with deep-link data, skipping members who turned it off', function () {
    [$plan, [$owner, $child]] = pushPlan();
    NotificationPreference::query()->create(['family_member_id' => $child->id, 'types' => ['goal_reached' => false], 'quiet_from' => null, 'quiet_to' => null, 'reminder_time' => '17:00']);

    GoalReached::dispatch(11, $plan->id, $plan->family_id, []);

    expect($this->sender->sent)->toHaveCount(1)
        ->and($this->sender->sent[0]['to'])->toBe("ExponentPushToken[m{$owner->id}]")
        ->and($this->sender->sent[0]['body'])->toContain('Pizza goal')
        ->and($this->sender->sent[0]['data'])->toBe(['type' => 'goal_reached', 'goal_id' => $plan->id, 'period_id' => 11]);
});

it('sends period_closed to every plan member', function () {
    [$plan] = pushPlan(2);

    PeriodClosed::dispatch(7, $plan->id, $plan->family_id, false, []);

    expect($this->sender->sent)->toHaveCount(3)
        ->and(collect($this->sender->sent)->pluck('data.type')->unique()->all())->toBe(['period_closed']);
});

it('delays notifications during quiet hours until they end in the family timezone', function () {
    [$plan, [$owner, $child]] = pushPlan();
    $this->travelTo(CarbonImmutable::parse('2026-10-15 23:00', 'Europe/Berlin'));
    Queue::fake([DeliverPushNotification::class]);

    app(NotifyGoalReached::class)->handle(new GoalReached(1, $plan->id, $plan->family_id, []));

    // Adults' quiet hours end 07:00, children's 07:30 (defaults) → two delayed jobs.
    Queue::assertPushed(DeliverPushNotification::class, 2);
    Queue::assertPushed(DeliverPushNotification::class, fn (DeliverPushNotification $job) => $job->memberIds === [$owner->id]
        && CarbonImmutable::instance($job->delay)->eq(CarbonImmutable::parse('2026-10-16 07:00', 'Europe/Berlin')));
    Queue::assertPushed(DeliverPushNotification::class, fn (DeliverPushNotification $job) => $job->memberIds === [$child->id]
        && CarbonImmutable::instance($job->delay)->eq(CarbonImmutable::parse('2026-10-16 07:30', 'Europe/Berlin')));
    expect($this->sender->sent)->toBe([]);
});

it('drops deliveries older than 12 hours', function () {
    [, [$owner]] = pushPlan(0);

    (new DeliverPushNotification([$owner->id], 'Hi', 'There', ['type' => 'goal_reached'], CarbonImmutable::now()->subHours(13)))->handle($this->sender);
    expect($this->sender->sent)->toBe([]);

    (new DeliverPushNotification([$owner->id], 'Hi', 'There', ['type' => 'goal_reached'], CarbonImmutable::now()->subHours(11)))->handle($this->sender);
    expect($this->sender->sent)->toHaveCount(1);
});

it('clears tokens Expo reports as not registered', function () {
    [$plan, [$owner, $child]] = pushPlan();
    $this->sender->invalid = ["ExponentPushToken[m{$child->id}]"];

    PeriodClosed::dispatch(1, $plan->id, $plan->family_id, true, []);

    expect(Device::query()->where('user_id', $child->user_id)->value('push_token'))->toBeNull()
        ->and(Device::query()->where('user_id', $owner->user_id)->value('push_token'))->toBe("ExponentPushToken[m{$owner->id}]");
});

it('fans out with a constant number of queries', function () {
    $count = function (int $extra): int {
        [$plan] = pushPlan($extra);
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(NotifyGoalReached::class)->handle(new GoalReached(1, $plan->id, $plan->family_id, []));
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $count(1); // warm up cached policy/age band lookups
    $few = $count(1);
    $many = $count(8);

    expect($many)->toBe($few)->and(count($this->sender->sent))->toBe(2 + 2 + 9);
});

it('posts to Expo in batches of 100 and reports DeviceNotRegistered tokens', function () {
    Http::fake(['exp.host/*' => function (Request $request) {
        $tickets = array_map(fn (array $message) => $message['to'] === 'ExponentPushToken[dead]'
            ? ['status' => 'error', 'message' => 'not registered', 'details' => ['error' => 'DeviceNotRegistered']]
            : ['status' => 'ok', 'id' => 'ticket'], $request->data());

        return Http::response(['data' => $tickets]);
    }]);

    $messages = array_map(fn (int $i) => ['to' => "ExponentPushToken[t{$i}]", 'title' => 'T', 'body' => 'B', 'data' => ['type' => 'goal_reached']], range(1, 149));
    $messages[] = ['to' => 'ExponentPushToken[dead]', 'title' => 'T', 'body' => 'B', 'data' => ['type' => 'goal_reached']];

    $invalid = app(HttpExpoPushSender::class)->send($messages);

    expect($invalid)->toBe(['ExponentPushToken[dead]']);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://exp.host/--/api/v2/push/send' && count($request->data()) === 100);
});

// ---------------------------------------------------------------------------------------------
// family_activity
// ---------------------------------------------------------------------------------------------

/**
 * A Berlin family: the owner plus children/teens, each with one push token.
 *
 * @return list<FamilyMember>
 */
function activityFamily(int $extraMembers = 2): array
{
    $owner = familyOwner(['tier' => 'family', 'timezone' => 'Europe/Berlin']);
    $members = [$owner];

    for ($i = 0; $i < $extraMembers; $i++) {
        $members[] = addMember($owner->family, $i % 2 === 0 ? 9 : 15);
    }

    foreach ($members as $member) {
        Device::factory()->create(['user_id' => $member->user_id, 'push_token' => "ExponentPushToken[m{$member->id}]"]);
    }

    return $members;
}

function finishSession(FamilyMember $member, int $points = 10, int $sessionId = 99): void
{
    SessionCompleted::dispatch($sessionId, $member->family_id, $member->id, $points);
}

it('listens for finished sessions with a queued family_activity listener', function () {
    Event::fake();

    Event::assertListening(SessionCompleted::class, NotifyFamilyActivity::class);
    expect(app(NotifyFamilyActivity::class))->toBeInstanceOf(ShouldQueue::class);
});

it('sends family_activity to the other active family members, not to the finisher', function () {
    [$owner, $kid, $teen] = activityFamily();
    $removed = addMember($owner->family, 30);
    $removed->update(['status' => FamilyMember::STATUS_REMOVED]);
    Device::factory()->create(['user_id' => $removed->user_id, 'push_token' => 'ExponentPushToken[removed]']);

    finishSession($kid, 10, 42);

    expect(collect($this->sender->sent)->pluck('to')->sort()->values()->all())
        ->toBe(collect(["ExponentPushToken[m{$owner->id}]", "ExponentPushToken[m{$teen->id}]"])->sort()->values()->all())
        ->and($this->sender->sent[0]['title'])->toBe("{$kid->nickname} finished a challenge!")
        ->and($this->sender->sent[0]['body'])->toContain('10 points')
        ->and($this->sender->sent[0]['data'])->toBe(['type' => 'family_activity', 'member_id' => $kid->id, 'session_id' => 42]);
});

it('sends at most one family_activity per finishing member per hour', function () {
    [, $kid, $teen] = activityFamily();

    finishSession($kid);
    finishSession($kid);
    expect($this->sender->sent)->toHaveCount(2);

    // Another member finishing is not throttled by the first one.
    finishSession($teen);
    expect($this->sender->sent)->toHaveCount(4);

    $this->travel(59)->minutes();
    finishSession($kid);
    expect($this->sender->sent)->toHaveCount(4);

    $this->travel(2)->minutes();
    finishSession($kid);
    expect($this->sender->sent)->toHaveCount(6)
        ->and(collect($this->sender->sent)->where('data.member_id', $kid->id)->pluck('to'))->not->toContain("ExponentPushToken[m{$kid->id}]");
});

it('respects family_activity preferences and quiet hours', function () {
    [$owner, $kid, $teen] = activityFamily();
    NotificationPreference::query()->create(['family_member_id' => $teen->id, 'types' => ['family_activity' => false], 'quiet_from' => null, 'quiet_to' => null, 'reminder_time' => '17:00']);

    finishSession($kid);
    expect(collect($this->sender->sent)->pluck('to')->all())->toBe(["ExponentPushToken[m{$owner->id}]"]);

    // 22:00 Berlin: inside the owner's default quiet hours (21:30–07:00) → delayed to 07:00.
    $this->travelTo(CarbonImmutable::parse('2026-10-15 22:00', 'Europe/Berlin'));
    Queue::fake([DeliverPushNotification::class]);
    finishSession($teen);

    Queue::assertPushed(DeliverPushNotification::class, fn (DeliverPushNotification $job) => in_array($owner->id, $job->memberIds, true)
        && CarbonImmutable::instance($job->delay)->eq(CarbonImmutable::parse('2026-10-16 07:00', 'Europe/Berlin')));
});

it('sends no family_activity when the finisher is alone in the family', function () {
    [$owner] = activityFamily(0);

    finishSession($owner);

    expect($this->sender->sent)->toBe([])
        ->and(Cache::has(NotifyFamilyActivity::throttleKey($owner->id)))->toBeFalse();
});

it('fans out family_activity with a constant number of queries', function () {
    $count = function (int $extra): int {
        $members = activityFamily($extra);
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(NotifyFamilyActivity::class)->handle(new SessionCompleted(1, $members[0]->family_id, $members[1]->id, 5));
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $count(1);

    expect($count(8))->toBe($count(2));
});

// ---------------------------------------------------------------------------------------------
// daily_reminder
// ---------------------------------------------------------------------------------------------

/**
 * An adult with daily reminders saved at `$time` (no quiet hours) and a push token; the owner of a
 * new family in `$timezone`, or a member of `$owner`'s family.
 */
function reminderMember(string $timezone = 'Europe/Berlin', string $time = '17:00', bool $enabled = true, ?FamilyMember $owner = null): FamilyMember
{
    $member = $owner === null ? familyOwner(['timezone' => $timezone]) : addMember($owner->family, 30);
    NotificationPreference::query()->create([
        'family_member_id' => $member->id,
        'types' => ['daily_reminder' => $enabled],
        'quiet_from' => null,
        'quiet_to' => null,
        'reminder_time' => $time,
    ]);
    Device::factory()->create(['user_id' => $member->user_id, 'push_token' => "ExponentPushToken[r{$member->id}]"]);

    return $member;
}

function playedAt(FamilyMember $member, CarbonImmutable $at): void
{
    PlaySession::query()->create([
        'family_id' => $member->family_id,
        'member_id' => $member->id,
        'category_id' => Category::factory()->create()->id,
        'target_points' => 10,
        'blunder_cap' => 3,
        'status' => PlaySession::STATUS_COMPLETED,
        'started_at' => $at->utc(),
    ]);
}

/**
 * Push tokens reminded by one scheduler run at `$at`.
 *
 * @return list<string>
 */
function remindAt(string $at, string $timezone): array
{
    test()->travelTo(CarbonImmutable::parse($at, $timezone));
    test()->sender->sent = [];
    test()->artisan('notifications:daily-reminders')->assertSuccessful();

    return collect(test()->sender->sent)->pluck('to')->sort()->values()->all();
}

/**
 * @param  list<FamilyMember>  $members
 * @return list<string>
 */
function reminderTokens(array $members): array
{
    return collect($members)->map(fn (FamilyMember $member) => "ExponentPushToken[r{$member->id}]")->sort()->values()->all();
}

it('computes the local 15-minute reminder window', function (string $time, array $window) {
    expect(SendDailyReminders::window(CarbonImmutable::parse("2026-10-15 {$time}")))->toBe($window);
})->with([
    ['17:00', ['17:00', '17:15']],
    ['17:14', ['17:00', '17:15']],
    ['17:15', ['17:15', '17:30']],
    ['00:00', ['00:00', '00:15']],
    ['23:59', ['23:45', '24:00']],
]);

it('reminds members whose reminder time is in the current window of their family timezone, once a day', function () {
    $member = reminderMember('Europe/Berlin', '17:00');

    expect(remindAt('2026-10-15 16:59', 'Europe/Berlin'))->toBe([])
        ->and(remindAt('2026-10-15 17:00', 'Europe/Berlin'))->toBe(reminderTokens([$member]))
        ->and($this->sender->sent[0]['data'])->toBe(['type' => 'daily_reminder'])
        ->and(remindAt('2026-10-15 17:10', 'Europe/Berlin'))->toBe([])
        ->and(remindAt('2026-10-15 17:15', 'Europe/Berlin'))->toBe([])
        ->and(remindAt('2026-10-16 17:05', 'Europe/Berlin'))->toBe(reminderTokens([$member]));
});

it('skips members with daily_reminder off or without saved preferences (default off)', function () {
    $owner = reminderMember('Europe/Berlin', '17:00', enabled: false);
    $defaults = addMember($owner->family, 30);
    Device::factory()->create(['user_id' => $defaults->user_id, 'push_token' => 'ExponentPushToken[defaults]']);

    expect(remindAt('2026-10-15 17:00', 'Europe/Berlin'))->toBe([]);
});

it('skips members who already played today in the family timezone', function () {
    // Toronto 20:05 is already 00:05 UTC of the next day.
    $owner = reminderMember('America/Toronto', '20:00');
    $notPlayed = reminderMember('America/Toronto', '20:00', owner: $owner);
    $playedYesterday = reminderMember('America/Toronto', '20:00', owner: $owner);

    playedAt($owner, CarbonImmutable::parse('2026-10-15 19:00', 'America/Toronto')); // today locally, yesterday in UTC terms
    playedAt($playedYesterday, CarbonImmutable::parse('2026-10-14 23:30', 'America/Toronto'));

    expect(remindAt('2026-10-15 20:05', 'America/Toronto'))->toBe(reminderTokens([$notPlayed, $playedYesterday]));
});

it('handles 30/45-minute offsets and families in different timezones in one run', function () {
    $kathmandu = reminderMember('Asia/Kathmandu', '17:45'); // UTC+5:45
    $kolkata = reminderMember('Asia/Kolkata', '17:30');     // UTC+5:30
    $berlin = reminderMember('Europe/Berlin', '14:00');     // UTC+2 (summer time)
    reminderMember('America/Toronto', '14:00');             // 08:00 there

    // 12:00 UTC = 17:45 Kathmandu = 17:30 Kolkata = 14:00 Berlin.
    expect(remindAt('2026-10-15 12:00', 'UTC'))->toBe(reminderTokens([$kathmandu, $kolkata, $berlin]));
});

it('follows daylight saving time changes', function () {
    $member = reminderMember('Europe/Berlin', '17:00');

    // Berlin leaves summer time on 2026-10-25: 17:00 local is 15:00 UTC before, 16:00 UTC after.
    expect(remindAt('2026-10-24 15:00', 'UTC'))->toBe(reminderTokens([$member]))
        ->and(remindAt('2026-10-25 15:00', 'UTC'))->toBe([])
        ->and(remindAt('2026-10-25 16:00', 'UTC'))->toBe(reminderTokens([$member]));
});

it('sends daily reminders with a constant number of queries', function () {
    $count = function (int $members): int {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 17:00', 'Europe/Berlin'));
        $owner = reminderMember('Europe/Berlin', '17:00');

        for ($i = 1; $i < $members; $i++) {
            $member = reminderMember('Europe/Berlin', '17:00', owner: $owner);

            if ($i % 2 === 0) {
                playedAt($member, CarbonImmutable::parse('2026-10-15 09:00', 'Europe/Berlin'));
            }
        }

        Cache::flush();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $reminded = app(SendDailyReminders::class)->handle();
        DB::disableQueryLog();
        expect($reminded)->toBeGreaterThan(0);

        return count(DB::getQueryLog());
    };

    $count(2);

    expect($count(8))->toBe($count(2));
});

it('schedules daily reminders every 15 minutes', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'notifications:daily-reminders'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/15 * * * *');
});
