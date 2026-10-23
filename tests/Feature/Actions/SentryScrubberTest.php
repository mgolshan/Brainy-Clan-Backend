<?php

use App\Modules\Core\Support\SentryScrubber;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\UserDataBag;

/**
 * Sentry never receives personal data (docs/PLAN.md B9): config and the before_send scrubber.
 */
it('is configured without PII, scrubbed, with low trace sampling, and off without a DSN', function () {
    expect(config('sentry.send_default_pii'))->toBeFalse()
        ->and(config('sentry.before_send'))->toBe([SentryScrubber::class, 'beforeSend'])
        ->and(config('sentry.before_send_transaction'))->toBe([SentryScrubber::class, 'beforeSend'])
        ->and(config('sentry.traces_sample_rate'))->toBeLessThanOrEqual(0.1)
        ->and(config('sentry.dsn'))->toBeEmpty()
        ->and(config('sentry.breadcrumbs.sql_bindings'))->toBeFalse()
        ->and(config('sentry.tracing.sql_bindings'))->toBeFalse();
});

it('strips user details, cookies, auth headers and request bodies', function () {
    $event = Event::createEvent();
    $event->setUser(UserDataBag::createFromArray(['id' => 42, 'email' => 'mom@example.com', 'username' => 'Mom', 'ip_address' => '203.0.113.9']));
    $event->setRequest([
        'url' => 'https://brainyclan.com/account/delete/confirm/'.str_repeat('a1B2', 16),
        'method' => 'POST',
        'query_string' => 'email=mom@example.com',
        'headers' => ['Authorization' => 'Bearer 12|secrettoken', 'Cookie' => 'session=abc', 'User-Agent' => 'Brainy/1.0', 'X-App-Version' => '1.0.0'],
        'cookies' => ['session' => 'abc'],
        'data' => ['email' => 'mom@example.com', 'password' => 'secret123', 'birth_year' => 1988],
        'env' => ['REMOTE_ADDR' => '203.0.113.9'],
    ]);

    $scrubbed = SentryScrubber::beforeSend($event);

    expect($scrubbed->getUser()?->getId())->toBe(42)
        ->and($scrubbed->getUser()?->getEmail())->toBeNull()
        ->and($scrubbed->getUser()?->getUsername())->toBeNull()
        ->and($scrubbed->getUser()?->getIpAddress())->toBeNull();

    $request = $scrubbed->getRequest();
    expect($request)->not->toHaveKeys(['cookies', 'data', 'env'])
        ->and($request['headers']['Authorization'])->toBe('[Filtered]')
        ->and($request['headers']['Cookie'])->toBe('[Filtered]')
        ->and($request['headers']['User-Agent'])->toBe('Brainy/1.0')
        ->and($request['url'])->toBe('https://brainyclan.com/account/delete/confirm/[Filtered]')
        ->and($request['query_string'])->toBe('email=[Filtered]');
});

it('masks names, emails, birth data and tokens in messages, exceptions, breadcrumbs and extra', function () {
    $event = Event::createEvent();
    $event->setMessage('Mail to dad@example.com failed');
    $event->setExceptions([new ExceptionDataBag(new RuntimeException('Duplicate entry kid@example.com for key users_email_unique'))]);
    $event->setBreadcrumb([
        new Breadcrumb('info', 'default', 'http', 'POST with Bearer 5|abcdefghijklmnopqrstuvwxyz', ['nickname' => 'Sam', 'status' => 422]),
    ]);
    $event->setExtra(['member' => ['nickname' => 'Sam', 'birth_year' => 2017, 'birth_month' => 1, 'role' => 'member']]);
    $event->setContext('job', ['payload' => ['display_name' => 'Robin', 'push_token' => 'ExponentPushToken[x]']]);

    $scrubbed = SentryScrubber::beforeSend($event);
    $serialized = json_encode([
        $scrubbed->getMessage(),
        array_map(fn (ExceptionDataBag $e) => $e->getValue(), $scrubbed->getExceptions()),
        array_map(fn (Breadcrumb $b) => [$b->getMessage(), $b->getMetadata()], $scrubbed->getBreadcrumbs()),
        $scrubbed->getExtra(),
        $scrubbed->getContexts()['job'],
    ]);

    expect($serialized)->not->toContain('example.com')
        ->not->toContain('Sam')
        ->not->toContain('Robin')
        ->not->toContain('2017')
        ->not->toContain('abcdefghijklmnop')
        ->not->toContain('ExponentPushToken')
        ->toContain('member')
        ->and($scrubbed->getBreadcrumbs()[0]->getMetadata()['status'])->toBe(422);
});
