<?php

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Goals\Events\GoalReached;
use App\Modules\Goals\Events\PeriodClosed;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Identity\Models\Device;
use App\Modules\Notifications\Contracts\ExpoPushSender;
use App\Modules\Notifications\Jobs\DeliverPushNotification;
use App\Modules\Notifications\Listeners\NotifyGoalReached;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Notifications\Support\HttpExpoPushSender;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
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
