<?php

use App\Modules\Identity\Mail\PasswordChanged;
use App\Modules\Identity\Mail\PasswordResetLink;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\SocialAccount;
use App\Modules\Identity\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * `POST /auth/password/forgot` and the web page `/password/reset` (docs/API.md Auth).
 */
beforeEach(function () {
    $this->freezeSecond();
    Mail::fake();
    config(['app.website_url' => 'https://brainyclan.test']);
});

function forgot(string $email, ?string $locale = null, string $ip = '10.0.0.1'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson('/api/v1/auth/password/forgot', ['email' => $email, ...($locale !== null ? ['locale' => $locale] : [])]);
}

/**
 * Requests a link for this email and returns the token from the queued email.
 */
function resetToken(string $email, ?string $locale = null): string
{
    forgot($email, $locale)->assertStatus(202);

    $url = null;
    Mail::assertQueued(PasswordResetLink::class, function (PasswordResetLink $mail) use (&$url, $email) {
        if ($mail->hasTo($email)) {
            $url = $mail->resetUrl;
        }

        return true;
    });

    parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);

    return (string) $query['token'];
}

function resetPost(string $token, string $password = 'new-secret-1', ?string $confirmation = null, string $lang = 'en'): TestResponse
{
    return test()->post('/password/reset'.($lang === 'fr' ? '?lang=fr' : ''), [
        'token' => $token,
        'password' => $password,
        'password_confirmation' => $confirmation ?? $password,
    ]);
}

it('always answers 202 and emails a link only for an existing account', function () {
    $user = User::factory()->create(['email' => 'mom@example.com', 'locale' => 'en']);

    forgot('MOM@example.com')->assertStatus(202)->assertExactJson(['data' => ['sent' => true]]);
    forgot('nobody@example.com')->assertStatus(202)->assertExactJson(['data' => ['sent' => true]]);

    Mail::assertQueuedCount(1);
    Mail::assertQueued(PasswordResetLink::class, function (PasswordResetLink $mail) {
        return $mail->hasTo('mom@example.com')
            && str_starts_with($mail->resetUrl, 'https://brainyclan.test/password/reset?token=')
            && $mail->mailLocale === 'en'
            && $mail->minutes === 60;
    });

    $row = DB::table('password_reset_tokens')->where('email', $user->email)->first();
    expect($row)->not->toBeNull()
        ->and(strlen($row->token))->toBe(64);
});

it('stores only a hash of the token and replaces older links', function () {
    User::factory()->create(['email' => 'mom@example.com']);

    $first = resetToken('mom@example.com');
    Mail::fake();
    $second = resetToken('mom@example.com');

    expect(DB::table('password_reset_tokens')->count())->toBe(1)
        ->and(DB::table('password_reset_tokens')->value('token'))->toBe(hash('sha256', $second))
        ->and($first)->not->toBe($second);

    $this->get('/password/reset?token='.$first)->assertOk()->assertSee('no longer valid');
    $this->get('/password/reset?token='.$second)->assertOk()->assertSee('Choose a new password');
});

it('validates the request', function () {
    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'not-an-email'])->assertStatus(422)->assertJsonPath('code', 'validation_failed');
    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'mom@example.com', 'locale' => 'de'])->assertStatus(422);
});

it('drops requests over 3 per hour per email silently', function () {
    User::factory()->create(['email' => 'mom@example.com']);

    for ($i = 0; $i < 5; $i++) {
        forgot('mom@example.com', null, "10.0.1.{$i}")->assertStatus(202)->assertExactJson(['data' => ['sent' => true]]);
    }

    Mail::assertQueuedCount(3);

    $this->travel(3601)->seconds();
    forgot('mom@example.com', null, '10.0.1.9')->assertStatus(202);
    Mail::assertQueuedCount(4);
});

it('drops requests over 10 per hour per IP silently', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    User::factory()->create(['email' => 'mom@example.com']);

    for ($i = 0; $i < 10; $i++) {
        forgot("someone{$i}@example.com")->assertStatus(202);
    }

    forgot('mom@example.com')->assertStatus(202)->assertExactJson(['data' => ['sent' => true]]);
    Mail::assertNothingQueued();

    forgot('mom@example.com', null, '10.0.0.2')->assertStatus(202);
    Mail::assertQueuedCount(1);
});

it('writes the email and the page in the requested or the user\'s language', function () {
    User::factory()->create(['email' => 'papa@example.com', 'locale' => 'fr']);

    $token = resetToken('papa@example.com');

    Mail::assertQueued(PasswordResetLink::class, fn (PasswordResetLink $mail) => $mail->mailLocale === 'fr' && str_contains($mail->resetUrl, 'lang=fr'));

    $mail = new PasswordResetLink('https://brainyclan.test/password/reset?token=x&lang=fr', 'fr', 60);
    $mail->assertSeeInText('valide 60 minutes');
    $mail->assertSeeInText('support@brainyclan.com');

    // Without ?lang the page follows the user's locale; the switch links to English.
    $this->get('/password/reset?token='.$token)->assertOk()
        ->assertSee('Choisissez un nouveau mot de passe')
        ->assertSee('lang=en', false)
        ->assertSee('English');
    $this->get('/password/reset?token='.$token.'&lang=en')->assertOk()->assertSee('Choose a new password')->assertSee('Français');

    Mail::fake();
    forgot('papa@example.com', 'en')->assertStatus(202);
    Mail::assertQueued(PasswordResetLink::class, fn (PasswordResetLink $mail) => $mail->mailLocale === 'en' && ! str_contains($mail->resetUrl, 'lang='));
});

it('shows the form with CSRF and strict headers', function () {
    User::factory()->create(['email' => 'mom@example.com']);
    $token = resetToken('mom@example.com');

    $this->get('/password/reset?token='.$token)
        ->assertOk()
        ->assertSee('name="_token"', false)
        ->assertSee('type="password"', false)
        ->assertSee('value="'.$token.'"', false)
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Frame-Options', 'DENY');
});

it('resets the password, signs out every device, clears lockouts and emails a notice', function () {
    $user = User::factory()->create(['email' => 'mom@example.com', 'locale' => 'en']);
    $this->withoutMiddleware(ThrottleRequests::class);

    $login = fn (string $password, string $ip = '10.0.0.1') => test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson('/api/v1/auth/login', ['email' => 'mom@example.com', 'password' => $password, 'device_name' => 'iPhone', 'platform' => 'ios']);

    $login('password', '10.0.0.5')->assertOk();
    $login('password', '10.0.0.6')->assertOk();
    expect(Device::query()->where('user_id', $user->id)->count())->toBe(2);

    // Locked out: 5 failures from one IP and 20 from many.
    for ($i = 0; $i < 5; $i++) {
        $login('wrong');
    }
    for ($i = 1; $i <= 20; $i++) {
        $login('wrong', "10.0.2.{$i}");
    }
    $login('password')->assertStatus(429)->assertJsonPath('code', 'login_locked');

    $token = resetToken('mom@example.com');

    resetPost($token)->assertOk()->assertSee('Your password was changed')->assertSee('Open the Brainy Clan app');

    $user->refresh();
    expect(Hash::check('new-secret-1', (string) $user->password))->toBeTrue()
        ->and(PersonalAccessToken::query()->where('tokenable_id', $user->id)->count())->toBe(0)
        ->and(Device::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->count())->toBe(0);

    Mail::assertQueued(PasswordChanged::class, fn (PasswordChanged $mail) => $mail->hasTo('mom@example.com') && $mail->mailLocale === 'en');

    // Every lock of that email is gone.
    $login('new-secret-1')->assertOk();
});

it('lets a social-only adult set a password', function () {
    $user = User::factory()->create(['email' => 'social@example.com', 'password' => null]);
    SocialAccount::factory()->create(['user_id' => $user->id]);

    resetPost(resetToken('social@example.com'))->assertOk()->assertSee('Your password was changed');

    expect(Hash::check('new-secret-1', (string) $user->refresh()->password))->toBeTrue();
});

it('accepts a link only once', function () {
    User::factory()->create(['email' => 'mom@example.com']);
    $token = resetToken('mom@example.com');

    resetPost($token)->assertOk()->assertSee('Your password was changed');
    resetPost($token, 'another-secret')->assertOk()->assertSee('no longer valid');
    $this->get('/password/reset?token='.$token)->assertOk()->assertSee('no longer valid')->assertSee('Forgot password?');

    Mail::assertQueuedCount(2);
});

it('expires links after 60 minutes', function () {
    $user = User::factory()->create(['email' => 'mom@example.com']);
    $token = resetToken('mom@example.com');

    $this->travel(61)->minutes();

    $this->get('/password/reset?token='.$token)->assertOk()->assertSee('no longer valid');
    resetPost($token)->assertOk()->assertSee('no longer valid');

    expect(Hash::check('password', (string) $user->refresh()->password))->toBeTrue();
    Mail::assertNotQueued(PasswordChanged::class);
});

it('shows the invalid page for unknown or missing tokens', function () {
    $this->get('/password/reset')->assertOk()->assertSee('no longer valid');
    $this->get('/password/reset?token=nope')->assertOk()->assertSee('no longer valid');
    $this->get('/password/reset?token=nope&lang=fr')->assertOk()->assertSee('n’est plus valide', false)->assertSee('Mot de passe oublié', false);
});

it('validates the new password', function () {
    $user = User::factory()->create(['email' => 'mom@example.com']);
    $token = resetToken('mom@example.com');
    $page = '/password/reset?token='.$token;

    $this->from($page)->post('/password/reset', ['token' => $token, 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertRedirect($page)
        ->assertSessionHasErrors(['password' => 'The password must be at least 8 characters.']);

    $this->from($page)->post('/password/reset', ['token' => $token, 'password' => 'long-enough-1', 'password_confirmation' => 'different-1'])
        ->assertRedirect($page)
        ->assertSessionHasErrors(['password' => 'The two passwords do not match.']);

    $this->from($page.'&lang=fr')->post('/password/reset?lang=fr', ['token' => $token, 'password' => 'court', 'password_confirmation' => 'court'])
        ->assertSessionHasErrors(['password' => 'Le mot de passe doit contenir au moins 8 caractères.']);

    // The link still works after a validation error.
    expect(Hash::check('password', (string) $user->refresh()->password))->toBeTrue();
    resetPost($token)->assertOk()->assertSee('Your password was changed');
});

it('shows the French page and French success', function () {
    $user = User::factory()->create(['email' => 'papa@example.com', 'locale' => 'fr']);
    $token = resetToken('papa@example.com');

    $this->get('/password/reset?token='.$token.'&lang=fr')->assertOk()
        ->assertSee('<html lang="fr">', false)
        ->assertSee('Nouveau mot de passe')
        ->assertSee('Confirmez le mot de passe');

    resetPost($token, lang: 'fr')->assertOk()->assertSee('Votre mot de passe a été changé', false);
    Mail::assertQueued(PasswordChanged::class, fn (PasswordChanged $mail) => $mail->mailLocale === 'fr');

    $mail = new PasswordChanged('fr');
    $mail->assertSeeInText('support@brainyclan.com');
    expect($user->refresh()->password)->not->toBeNull();
});

it('protects the form with the session and CSRF (web middleware)', function () {
    $route = Route::getRoutes()->getByName('password.reset.submit');

    expect($route->gatherMiddleware())->toContain('web')
        ->and($route->methods())->toContain('POST');
});

it('throttles the reset form to 10 posts per hour per IP', function () {
    for ($i = 0; $i < 10; $i++) {
        resetPost('nope')->assertOk();
    }

    resetPost('nope')->assertStatus(429);
});

it('documents the forgot endpoint and login_locked in the API docs', function () {
    Gate::define('viewApiDocs', fn ($user = null): bool => true);
    $spec = $this->getJson('/docs/api.json')->assertOk()->json();

    $forgot = $spec['paths']['/auth/password/forgot']['post'] ?? null;
    $login = $spec['paths']['/auth/login']['post'] ?? null;

    expect($forgot)->not->toBeNull()
        ->and($forgot['responses'])->toHaveKey(202)
        ->and($login['responses']['429']['content']['application/json']['schema']['properties']['code']['enum'] ?? [])->toContain('login_locked')
        ->and($login['responses']['429']['content']['application/json']['schema']['properties'])->toHaveKey('retry_after');
});
