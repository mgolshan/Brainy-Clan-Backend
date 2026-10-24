<?php

use App\Modules\Core\Listeners\ForwardLongWaitToWebhook;
use App\Modules\Core\Listeners\SendFailedJobAlert;
use App\Modules\Core\Support\SchedulerHeartbeat;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Events\LongWaitDetected;
use Laravel\Horizon\Horizon;

/**
 * B11 ops hardening: the token-protected deep health check (`GET /api/v1/health/deep`), the
 * scheduler heartbeat, `horizon:snapshot`, and failed-job / long-wait alerts.
 */
const DEEP_TOKEN = 'test-deep-token-0123456789abcdef';

/**
 * Healthy defaults: Redis answers, one running Horizon master, a fresh heartbeat.
 *
 * @param  list<object>  $masters
 */
function healthyOps(array $masters = [], bool $redisUp = true): void
{
    config(['ops.health.deep_token' => DEEP_TOKEN]);

    $masters = $masters !== [] ? $masters : [(object) ['name' => 'master-1', 'status' => 'running']];
    test()->mock(MasterSupervisorRepository::class, fn ($mock) => $mock->shouldReceive('all')->andReturn($masters));

    $redis = Redis::shouldReceive('connection->ping');
    $redisUp ? $redis->andReturn(true) : $redis->andThrow(new RuntimeException('Connection refused redis:6379 secret'));

    app(SchedulerHeartbeat::class)->beat();
}

function deepHealth(?string $token = DEEP_TOKEN): TestResponse
{
    return test()->getJson('/api/v1/health/deep', $token !== null ? ['X-Health-Token' => $token] : []);
}

it('keeps the shallow health check exactly as the app uses it', function () {
    $this->getJson('/api/v1/health')->assertOk()
        ->assertExactJsonStructure(['data' => ['status', 'api_version', 'min_app_version', 'time']])
        ->assertJsonPath('data.status', 'ok');
});

it('hides the deep check without a configured token and refuses a wrong or missing token', function () {
    config(['ops.health.deep_token' => null]);
    deepHealth()->assertNotFound()->assertJsonPath('code', 'not_found');

    config(['ops.health.deep_token' => DEEP_TOKEN]);
    deepHealth(null)->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    deepHealth('wrong')->assertUnauthorized();
});

it('reports every check as ok with 200 and no secrets or versions', function () {
    healthyOps();

    $response = deepHealth()->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and(array_keys($response->json('data')))->toBe(['status', 'checks', 'time'])
        ->and($response->json('data.status'))->toBe('ok')
        ->and($response->json('data.checks'))->toBe([
            'database' => ['status' => 'ok'],
            'redis' => ['status' => 'ok'],
            'scheduler' => ['status' => 'ok', 'age_seconds' => 0],
            'horizon' => ['status' => 'ok', 'state' => 'running'],
            'failed_jobs' => ['status' => 'ok', 'last_hour' => 0],
        ]);

    expect($response->getContent())->not->toContain('version')->not->toContain(DEEP_TOKEN);
});

it('answers 503 when a critical check fails, without the error message', function () {
    healthyOps(redisUp: false);

    $response = deepHealth()->assertStatus(503)
        ->assertJsonPath('data.status', 'fail')
        ->assertJsonPath('data.checks.redis.status', 'fail')
        ->assertJsonPath('data.checks.database.status', 'ok');

    expect($response->getContent())->not->toContain('refused')->not->toContain('secret')->not->toContain('6379');
});

it('fails when the scheduler heartbeat is older than 3 minutes or missing', function () {
    healthyOps();

    $this->travel(179)->seconds();
    deepHealth()->assertOk()->assertJsonPath('data.checks.scheduler.age_seconds', 179);

    $this->travel(2)->seconds();
    deepHealth()->assertStatus(503)->assertJsonPath('data.checks.scheduler', ['status' => 'fail', 'age_seconds' => 181]);

    cache()->forget(SchedulerHeartbeat::CACHE_KEY);
    deepHealth()->assertStatus(503)->assertJsonPath('data.checks.scheduler', ['status' => 'fail', 'age_seconds' => null]);
});

it('fails when Horizon is not running or every master is paused', function (array $masters, string $state) {
    healthyOps();
    $this->mock(MasterSupervisorRepository::class, fn ($mock) => $mock->shouldReceive('all')->andReturn($masters));

    deepHealth()->assertStatus(503)->assertJsonPath('data.checks.horizon', ['status' => 'fail', 'state' => $state]);
})->with([
    'inactive' => [[], 'inactive'],
    'paused' => [[(object) ['status' => 'paused']], 'paused'],
]);

it('only warns about failed jobs of the last hour', function () {
    healthyOps();
    $failed = fn ($at) => DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => $at]);
    $failed(now()->subHours(2));

    deepHealth()->assertOk()->assertJsonPath('data.status', 'ok')->assertJsonPath('data.checks.failed_jobs.last_hour', 0);

    $failed(now()->subMinutes(10));

    deepHealth()->assertOk()
        ->assertJsonPath('data.status', 'degraded')
        ->assertJsonPath('data.checks.failed_jobs', ['status' => 'warn', 'last_hour' => 1]);
});

it('rate-limits the deep check per IP', function () {
    healthyOps();
    config(['ops.health.deep_rate_per_minute' => 3]);

    deepHealth()->assertOk();
    deepHealth('wrong')->assertUnauthorized();
    deepHealth()->assertOk();
    deepHealth()->assertTooManyRequests()->assertJsonPath('code', 'rate_limited');
});

it('ignores the app version check on the deep check', function () {
    healthyOps();

    $this->getJson('/api/v1/health/deep', ['X-Health-Token' => DEEP_TOKEN, 'X-App-Version' => '0.0.1'])->assertOk();
});

it('writes the heartbeat every minute and snapshots Horizon every five minutes', function () {
    expect(Artisan::call('ops:heartbeat'))->toBe(0)
        ->and(app(SchedulerHeartbeat::class)->ageSeconds())->toBe(0);

    $events = collect(app(Schedule::class)->events());
    $heartbeat = $events->first(fn ($event) => str_contains((string) $event->command, 'ops:heartbeat'));
    $snapshot = $events->first(fn ($event) => str_contains((string) $event->command, 'horizon:snapshot'));

    expect($heartbeat?->expression)->toBe('* * * * *')
        ->and($snapshot?->expression)->toBe('*/5 * * * *');
});

it('routes Horizon long-wait mails to the ops alert address', function () {
    expect(Horizon::$email)->toBe(config('ops.alert_email'))
        ->and(config('ops.alert_email'))->toBe('support@brainyclan.com');
});

/**
 * A failed queued job as the queue worker reports it.
 */
function failedJobEvent(string $jobClass = 'App\\Modules\\Notifications\\Jobs\\DeliverPushNotification'): JobFailed
{
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn($jobClass);
    $job->shouldReceive('getQueue')->andReturn('default');

    return new JobFailed('redis', $job, new RuntimeException('Push to mom@example.com failed'));
}

it('mails one alert per failed job class per cooldown, without the exception message', function () {
    Http::fake();
    config(['ops.alert_webhook_url' => 'https://hooks.example.test/ops']);
    $sent = fn () => Mail::getFacadeRoot()->mailer()->getSymfonyTransport()->messages();

    $listener = app(SendFailedJobAlert::class);
    $listener->handle(failedJobEvent());
    $listener->handle(failedJobEvent());

    expect($sent())->toHaveCount(1);

    $message = $sent()->first()->getOriginalMessage();
    expect($message->getTo()[0]->getAddress())->toBe('support@brainyclan.com')
        ->and($message->getSubject())->toContain('Queued job failed: DeliverPushNotification')
        ->and($message->getTextBody())->toContain('RuntimeException')->not->toContain('mom@example.com');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.example.test/ops'
        && str_contains($request['text'], 'DeliverPushNotification')
        && ! str_contains($request['text'], 'mom@example.com'));

    $listener->handle(failedJobEvent('App\\Modules\\Content\\Jobs\\GenerateContentBatchJob'));
    expect($sent())->toHaveCount(2);

    $this->travel(16)->minutes();
    $listener->handle(failedJobEvent());
    expect($sent())->toHaveCount(3);
});

it('sends no alert mail when no alert address is configured', function () {
    Http::fake();
    config(['ops.alert_email' => '', 'ops.alert_webhook_url' => null]);

    app(SendFailedJobAlert::class)->handle(failedJobEvent());

    expect(Mail::getFacadeRoot()->mailer()->getSymfonyTransport()->messages())->toHaveCount(0);
    Http::assertNothingSent();
});

it('forwards Horizon long waits to the ops webhook once per queue every 5 minutes', function () {
    Http::fake();
    $listener = app(ForwardLongWaitToWebhook::class);

    $listener->handle(new LongWaitDetected('redis', 'default', 75));
    Http::assertNothingSent();

    config(['ops.alert_webhook_url' => 'https://hooks.example.test/ops']);
    $listener->handle(new LongWaitDetected('redis', 'default', 75));
    $listener->handle(new LongWaitDetected('redis', 'default', 90));
    $listener->handle(new LongWaitDetected('redis-ai', 'ai', 1000));

    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => str_contains($request['text'], 'redis / default has waited 75 s'));
});

it('listens to failed jobs and Horizon long waits', function () {
    expect(Event::hasListeners(JobFailed::class))->toBeTrue()
        ->and(collect(Event::getRawListeners()[JobFailed::class])->contains(SendFailedJobAlert::class))->toBeTrue()
        ->and(collect(Event::getRawListeners()[LongWaitDetected::class])->contains(ForwardLongWaitToWebhook::class))->toBeTrue();
});
