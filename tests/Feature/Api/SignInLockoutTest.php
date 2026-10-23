<?php

use App\Modules\Family\Models\Invite;
use App\Modules\Identity\Mail\SignInAttemptsNotice;
use App\Modules\Identity\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

/**
 * Sign-in lockout (docs/API.md Auth → "Lockout"). The 10/min `auth` throttle is switched off here
 * so the lockout rules can be tested on their own (one test keeps it on).
 */
beforeEach(function () {
    $this->freezeSecond();
    $this->withoutMiddleware(ThrottleRequests::class);
    User::factory()->create(['email' => 'mom@example.com', 'locale' => 'en']);
});

function lockLogin(string $email, string $password = 'wrong-password', string $ip = '10.0.0.1'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
        'device_name' => 'iPhone',
        'platform' => 'ios',
    ]);
}

function lockCode(string $code, string $ip = '10.0.0.1'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/v1/auth/code', [
        'code' => $code,
        'device_name' => 'iPhone',
        'platform' => 'ios',
    ]);
}

function lockJoin(string $code, string $ip = '10.0.0.1'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/v1/auth/join', [
        'code' => $code,
        'device_name' => 'iPad',
        'platform' => 'ios',
    ]);
}

function failLogins(int $times, string $email = 'mom@example.com', string $ip = '10.0.0.1'): TestResponse
{
    $response = null;

    for ($i = 0; $i < $times; $i++) {
        $response = lockLogin($email, 'wrong-password', $ip);
    }

    return $response;
}

function assertLocked(TestResponse $response, int $seconds): void
{
    $response->assertStatus(429)
        ->assertExactJson([
            'message' => 'Too many failed sign-in attempts. Please try again later or reset your password.',
            'code' => 'login_locked',
            'retry_after' => $seconds,
        ])
        ->assertHeader('Retry-After', (string) $seconds);
}

it('locks email + IP for 5 minutes after 5 failures in a row', function () {
    failLogins(4)->assertStatus(401)->assertJsonPath('code', 'invalid_credentials');

    assertLocked(lockLogin('mom@example.com'), 300);

    // While locked the credentials are not checked: even the right password is refused.
    $this->travel(60)->seconds();
    assertLocked(lockLogin('mom@example.com', 'password'), 240);

    // Another IP is not affected by the email + IP lock.
    lockLogin('mom@example.com', 'password', '10.0.0.2')->assertOk();
});

it('escalates to 1 hour and then 24 hours for every further 5 failures', function () {
    assertLocked(failLogins(5), 300);
    $this->travel(301)->seconds();

    failLogins(4)->assertStatus(401);
    assertLocked(lockLogin('mom@example.com'), 3600);
    $this->travel(3601)->seconds();

    assertLocked(failLogins(5), 86400);
    $this->travel(86401)->seconds();

    // After a 24 h lock every 5 failures lock 24 h again.
    assertLocked(failLogins(5), 86400);
});

it('resets the count and the escalation after a successful sign-in', function () {
    assertLocked(failLogins(5), 300);
    $this->travel(301)->seconds();

    lockLogin('mom@example.com', 'password')->assertOk();

    failLogins(4)->assertStatus(401);
    assertLocked(lockLogin('mom@example.com'), 300);
});

it('resets the escalation after 24 hours without failures', function () {
    assertLocked(failLogins(5), 300);
    $this->travel(301 + 86400)->seconds();

    failLogins(4)->assertStatus(401);
    assertLocked(lockLogin('mom@example.com'), 300);
});

it('keeps the escalation when failures continue within 24 hours', function () {
    assertLocked(failLogins(5), 300);
    $this->travel(301 + 3600)->seconds();

    assertLocked(failLogins(5), 3600);
});

it('answers the same for an unknown email (no account discovery)', function () {
    $known = failLogins(5, 'mom@example.com');
    $unknown = failLogins(5, 'nobody@example.com');

    assertLocked($known, 300);
    assertLocked($unknown, 300);
    expect($unknown->json())->toBe($known->json());

    lockLogin('nobody@example.com', 'password', '10.0.0.1')->assertJsonPath('code', 'login_locked');
});

it('treats the email case-insensitively', function () {
    failLogins(4, 'MOM@Example.com');

    assertLocked(lockLogin('mom@example.com'), 300);
});

it('locks the email from all IPs for 15 minutes after 20 failures within an hour and notifies the owner once', function () {
    Mail::fake();

    for ($i = 1; $i <= 19; $i++) {
        lockLogin('mom@example.com', 'wrong-password', "10.0.1.{$i}")->assertStatus(401);
    }

    assertLocked(lockLogin('mom@example.com', 'wrong-password', '10.0.1.20'), 900);
    assertLocked(lockLogin('mom@example.com', 'password', '10.0.9.9'), 900);

    Mail::assertQueued(SignInAttemptsNotice::class, fn (SignInAttemptsNotice $mail) => $mail->hasTo('mom@example.com') && $mail->mailLocale === 'en' && $mail->lockMinutes === 15);
    Mail::assertQueuedCount(1);

    // The next lock within 24 h sends no second email.
    $this->travel(901)->seconds();

    for ($i = 1; $i <= 20; $i++) {
        lockLogin('mom@example.com', 'wrong-password', "10.0.2.{$i}");
    }

    assertLocked(lockLogin('mom@example.com', 'password', '10.0.9.9'), 900);
    Mail::assertQueuedCount(1);

    // A day later the owner can be told again.
    $this->travel(86400)->seconds();

    for ($i = 1; $i <= 20; $i++) {
        lockLogin('mom@example.com', 'wrong-password', "10.0.3.{$i}");
    }

    Mail::assertQueuedCount(2);
});

it('sends the owner notice in the user\'s language and mentions the reset and support', function () {
    $user = User::factory()->create(['email' => 'papa@example.com', 'locale' => 'fr']);

    $mail = new SignInAttemptsNotice('fr', 15);
    $mail->assertSeeInText('Mot de passe oublié');
    $mail->assertSeeInText('support@brainyclan.com');
    $mail->assertSeeInText('15 minutes');
    expect($mail->envelope()->subject)->toContain('Tentatives de connexion');

    Mail::fake();

    for ($i = 1; $i <= 20; $i++) {
        lockLogin($user->email, 'wrong-password', "10.0.4.{$i}");
    }

    Mail::assertQueued(SignInAttemptsNotice::class, fn (SignInAttemptsNotice $mail) => $mail->hasTo('papa@example.com') && $mail->mailLocale === 'fr');
});

it('sends no notice for an unknown email but locks it the same way', function () {
    Mail::fake();

    for ($i = 1; $i <= 20; $i++) {
        lockLogin('nobody@example.com', 'wrong-password', "10.0.5.{$i}");
    }

    assertLocked(lockLogin('nobody@example.com', 'password', '10.0.9.9'), 900);
    Mail::assertNothingQueued();
});

it('counts per-email failures in a 1 hour window', function () {
    for ($i = 1; $i <= 19; $i++) {
        lockLogin('mom@example.com', 'wrong-password', "10.0.6.{$i}");
    }

    $this->travel(3601)->seconds();

    // The window started over: one more failure does not lock.
    lockLogin('mom@example.com', 'wrong-password', '10.0.6.20')->assertStatus(401);
    lockLogin('mom@example.com', 'password', '10.0.6.21')->assertOk();
});

it('locks an IP for 1 hour after 30 failures within an hour, for login, codes and invites', function () {
    for ($i = 1; $i <= 29; $i++) {
        lockLogin("user{$i}@example.com")->assertStatus(401);
    }

    assertLocked(lockLogin('user30@example.com'), 3600);

    assertLocked(lockLogin('mom@example.com', 'password'), 3600);
    assertLocked(lockCode('ABCD-2345'), 3600);
    assertLocked(lockJoin('ABCD2345'), 3600);

    // Other IPs are fine.
    lockLogin('mom@example.com', 'password', '10.0.0.99')->assertOk();

    $this->travel(3601)->seconds();
    lockLogin('mom@example.com', 'password')->assertOk();
});

it('counts code and invite failures towards the IP limit', function () {
    for ($i = 1; $i <= 2; $i++) {
        lockCode('NOPE-2345')->assertStatus(422);
        lockJoin('NOPE2345')->assertStatus(422);
    }

    for ($i = 1; $i <= 25; $i++) {
        lockLogin("user{$i}@example.com")->assertStatus(401);
    }

    // The 30th failure of this IP (also the 5th invalid code) starts the 1 h IP lock.
    assertLocked(lockJoin('NOPE2345'), 3600);
    assertLocked(lockLogin('mom@example.com', 'password'), 3600);
});

it('locks sign-in codes per IP after 5 invalid codes, escalating', function () {
    for ($i = 0; $i < 4; $i++) {
        lockCode('NOPE-2345')->assertStatus(422)->assertJsonPath('code', 'sign_in_code_invalid');
    }

    assertLocked(lockCode('NOPE-2345'), 300);
    assertLocked(lockJoin('NOPE2345'), 300);

    // Email login is not affected by the code lock.
    lockLogin('mom@example.com', 'password')->assertOk();
    // Another IP may still redeem codes.
    lockCode('NOPE-2345', '10.0.0.2')->assertStatus(422);

    $this->travel(301)->seconds();
    for ($i = 0; $i < 4; $i++) {
        lockJoin('NOPE2345')->assertStatus(422)->assertJsonPath('code', 'invite_invalid');
    }
    assertLocked(lockJoin('NOPE2345'), 3600);

    $this->travel(3601)->seconds();
    assertLocked(failCodes(5), 86400);
});

it('resets the code escalation after a valid invite code', function () {
    $owner = familyOwner();
    Invite::factory()->create(['family_id' => $owner->family_id, 'code' => 'GOOD2345', 'birth_year' => now()->year - 9, 'birth_month' => 1]);

    assertLocked(failCodes(5), 300);
    $this->travel(301)->seconds();

    lockJoin('GOOD2345')->assertCreated();

    failCodes(4)->assertStatus(422);
    assertLocked(lockCode('NOPE-2345'), 300);
});

it('never affects social sign-in or signed-in devices', function () {
    $member = familyOwner();
    $token = lockLogin($member->user->email, 'password')->assertOk()->json('data.token');

    assertLocked(failLogins(5, $member->user->email), 300);
    for ($i = 0; $i < 25; $i++) {
        lockCode('NOPE-2345');
    }

    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/me')->assertOk();
});

it('keeps the 10 per minute auth throttle', function () {
    $this->withMiddleware(ThrottleRequests::class);

    for ($i = 1; $i <= 10; $i++) {
        lockLogin("user{$i}@example.com")->assertStatus(401);
    }

    lockLogin('user11@example.com')->assertStatus(429)->assertJsonPath('code', 'rate_limited');
});

it('never stores the raw email or IP in cache keys', function () {
    failLogins(5);

    $store = Cache::store()->getStore();
    $keys = array_keys((fn () => $this->storage)->call($store));

    expect($keys)->not->toBeEmpty();
    foreach ($keys as $key) {
        expect($key)->not->toContain('mom@example.com')->not->toContain('10.0.0.1');
    }
});

it('unlocks an email with auth:unlock', function () {
    assertLocked(failLogins(5), 300);
    for ($i = 1; $i <= 20; $i++) {
        lockLogin('mom@example.com', 'wrong-password', "10.0.7.{$i}");
    }

    $this->artisan('auth:unlock', ['email' => 'MOM@example.com'])->assertSuccessful();

    lockLogin('mom@example.com', 'password')->assertOk();
});

it('unlocks an IP with auth:unlock --ip', function () {
    for ($i = 1; $i <= 30; $i++) {
        lockLogin("user{$i}@example.com");
    }
    assertLocked(lockCode('NOPE-2345'), 3600);

    $this->artisan('auth:unlock', ['email' => 'mom@example.com', '--ip' => '10.0.0.1'])->assertSuccessful();

    lockCode('NOPE-2345')->assertStatus(422);
    $this->artisan('auth:unlock', ['email' => 'not-an-email'])->assertFailed();
});

function failCodes(int $times, string $ip = '10.0.0.1'): TestResponse
{
    $response = null;

    for ($i = 0; $i < $times; $i++) {
        $response = lockCode('NOPE-2345', $ip);
    }

    return $response;
}
