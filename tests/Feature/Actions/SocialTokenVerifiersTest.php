<?php

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Social\AppleIdTokenVerifier;
use App\Modules\Identity\Social\FacebookTokenVerifier;
use App\Modules\Identity\Social\GoogleIdTokenVerifier;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\FacebookProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * A fresh RSA key pair as [private PEM, public JWK].
 *
 * @return array{0: string, 1: array<string, string>}
 */
function rsaKey(string $kid): array
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);
    $rsa = openssl_pkey_get_details($key)['rsa'];

    return [$pem, [
        'kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => $kid,
        'n' => JWT::urlsafeB64Encode($rsa['n']), 'e' => JWT::urlsafeB64Encode($rsa['e']),
    ]];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function googleToken(string $pem, string $kid, array $overrides = []): string
{
    return JWT::encode([
        'iss' => 'https://accounts.google.com',
        'aud' => 'web-client.apps.googleusercontent.com',
        'sub' => '1098765',
        'email' => 'Mom@Gmail.com',
        'email_verified' => true,
        'name' => 'Mom Golshan',
        'given_name' => 'Mom',
        'iat' => time(),
        'exp' => time() + 3600,
        ...$overrides,
    ], $pem, 'RS256', $kid);
}

function expectSocialTokenInvalid(callable $callback): void
{
    try {
        $callback();
        test()->fail('Expected social_token_invalid.');
    } catch (ApiException $e) {
        expect($e->errorCode)->toBe('social_token_invalid')->and($e->status)->toBe(401);
    }
}

describe('Google ID tokens', function () {
    beforeEach(function () {
        config()->set('services.google.client_ids', ['web-client.apps.googleusercontent.com', 'ios-client.apps.googleusercontent.com']);
        [$this->pem, $jwk] = rsaKey('k1');
        Http::fake([GoogleIdTokenVerifier::JWKS_URL => Http::response(['keys' => [$jwk]], 200, ['Cache-Control' => 'public, max-age=21600'])]);
    });

    it('accepts a valid token and returns the verified email and first name', function () {
        $identity = app(GoogleIdTokenVerifier::class)->verify(googleToken($this->pem, 'k1'));

        expect($identity->provider)->toBe('google')
            ->and($identity->providerUserId)->toBe('1098765')
            ->and($identity->email)->toBe('mom@gmail.com')
            ->and($identity->name)->toBe('Mom');
    });

    it('accepts any configured client ID as audience (web, iOS) and caches the keys', function () {
        $verifier = app(GoogleIdTokenVerifier::class);
        $verifier->verify(googleToken($this->pem, 'k1', ['iss' => 'accounts.google.com']));
        $verifier->verify(googleToken($this->pem, 'k1', ['aud' => 'ios-client.apps.googleusercontent.com']));

        Http::assertSentCount(1);
    });

    it('ignores an unverified email', function () {
        $identity = app(GoogleIdTokenVerifier::class)->verify(googleToken($this->pem, 'k1', ['email_verified' => false]));

        expect($identity->email)->toBeNull();
    });

    it('rejects a wrong audience, issuer, expired token, foreign signature or garbage', function () {
        $verifier = app(GoogleIdTokenVerifier::class);
        [$otherPem] = rsaKey('k1');

        expectSocialTokenInvalid(fn () => $verifier->verify(googleToken($this->pem, 'k1', ['aud' => 'someone-else'])));
        expectSocialTokenInvalid(fn () => $verifier->verify(googleToken($this->pem, 'k1', ['iss' => 'https://evil.example.com'])));
        expectSocialTokenInvalid(fn () => $verifier->verify(googleToken($this->pem, 'k1', ['exp' => time() - 600])));
        expectSocialTokenInvalid(fn () => $verifier->verify(googleToken($otherPem, 'k1')));
        expectSocialTokenInvalid(fn () => $verifier->verify('not-a-jwt'));
    });

    it('refetches the keys once when the key id is unknown (rotation)', function () {
        $verifier = app(GoogleIdTokenVerifier::class);
        $verifier->verify(googleToken($this->pem, 'k1'));

        expectSocialTokenInvalid(fn () => $verifier->verify(googleToken($this->pem, 'k2')));
        Http::assertSentCount(2);
    });

    it('refuses everything when no client ID is configured', function () {
        config()->set('services.google.client_ids', []);

        expectSocialTokenInvalid(fn () => app(GoogleIdTokenVerifier::class)->verify(googleToken($this->pem, 'k1')));
        Http::assertNothingSent();
    });
});

describe('Facebook access tokens', function () {
    beforeEach(function () {
        config()->set('services.facebook.client_id', '1234');
        config()->set('services.facebook.client_secret', 'shh');
    });

    function fakeDebugToken(array $data): void
    {
        Http::fake(['graph.facebook.com/*/debug_token*' => Http::response(['data' => $data])]);
    }

    function fakeFacebookMe(array $raw): void
    {
        $provider = Mockery::mock(FacebookProvider::class);
        $provider->shouldReceive('usingGraphVersion')->andReturnSelf();
        $provider->shouldReceive('fields')->with(['first_name', 'name', 'email'])->andReturnSelf();
        $provider->shouldReceive('userFromToken')->andReturn((new SocialiteUser)->setRaw($raw)->map([
            'id' => $raw['id'], 'name' => $raw['name'] ?? null, 'email' => $raw['email'] ?? null,
        ]));
        Socialite::shouldReceive('driver')->with('facebook')->andReturn($provider);
    }

    it('accepts a valid token issued for our app', function () {
        fakeDebugToken(['is_valid' => true, 'app_id' => '1234', 'user_id' => '777']);
        fakeFacebookMe(['id' => '777', 'first_name' => 'Dad', 'name' => 'Dad Golshan', 'email' => 'Dad@Example.com']);

        $identity = app(FacebookTokenVerifier::class)->verify('EAAB-token');

        expect([$identity->provider, $identity->providerUserId, $identity->email, $identity->name])
            ->toBe(['facebook', '777', 'dad@example.com', 'Dad']);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'input_token=EAAB-token')
            && str_contains($request->url(), 'access_token='.urlencode('1234|shh')));
    });

    it('returns no email for a phone-only account', function () {
        fakeDebugToken(['is_valid' => true, 'app_id' => '1234', 'user_id' => '777']);
        fakeFacebookMe(['id' => '777', 'name' => 'Dad']);

        expect(app(FacebookTokenVerifier::class)->verify('EAAB-token')->email)->toBeNull();
    });

    it('rejects tokens of another app, invalid tokens and id mismatches', function () {
        fakeDebugToken(['is_valid' => true, 'app_id' => '9999', 'user_id' => '777']);
        expectSocialTokenInvalid(fn () => app(FacebookTokenVerifier::class)->verify('EAAB-token'));

        fakeDebugToken(['is_valid' => false, 'app_id' => '1234', 'user_id' => '777']);
        expectSocialTokenInvalid(fn () => app(FacebookTokenVerifier::class)->verify('EAAB-token'));
    });

    it('rejects a /me id that differs from debug_token', function () {
        fakeDebugToken(['is_valid' => true, 'app_id' => '1234', 'user_id' => '777']);
        fakeFacebookMe(['id' => '888', 'name' => 'Other']);

        expectSocialTokenInvalid(fn () => app(FacebookTokenVerifier::class)->verify('EAAB-token'));
    });

    it('refuses iOS Limited Login (JWT) tokens without calling Facebook', function () {
        Http::fake();
        [$pem] = rsaKey('fb');
        $jwt = JWT::encode(['iss' => 'https://www.facebook.com', 'sub' => '777'], $pem, 'RS256', 'fb');

        expectSocialTokenInvalid(fn () => app(FacebookTokenVerifier::class)->verify($jwt));
        Http::assertNothingSent();
    });
});

/**
 * @param  array<string, mixed>  $overrides
 */
function appleToken(string $pem, string $kid, array $overrides = []): string
{
    return JWT::encode([
        'iss' => 'https://appleid.apple.com',
        'aud' => 'app.brainyclan',
        'sub' => '001234.abcdef0123456789.0123',
        'email' => 'Mom@Example.com',
        'email_verified' => 'true',
        'is_private_email' => 'false',
        'nonce' => hash('sha256', 'raw-nonce-1'),
        'iat' => time(),
        'exp' => time() + 600,
        ...$overrides,
    ], $pem, 'RS256', $kid);
}

describe('Apple identity tokens', function () {
    beforeEach(function () {
        config()->set('services.apple.client_ids', ['app.brainyclan', 'app.brainyclan.web']);
        [$this->pem, $jwk] = rsaKey('a1');
        Http::fake([AppleIdTokenVerifier::JWKS_URL => Http::response(['keys' => [$jwk]], 200)]);
    });

    it('accepts a valid token for the bundle ID or the Services ID, with or without a nonce', function () {
        $verifier = app(AppleIdTokenVerifier::class);
        $identity = $verifier->verify(appleToken($this->pem, 'a1'), 'raw-nonce-1');

        expect($identity->provider)->toBe('apple')
            ->and($identity->providerUserId)->toBe('001234.abcdef0123456789.0123')
            ->and($identity->email)->toBe('mom@example.com')
            ->and($identity->name)->toBeNull();

        $verifier->verify(appleToken($this->pem, 'a1', ['aud' => 'app.brainyclan.web']));
        $verifier->verify(appleToken($this->pem, 'a1', ['email_verified' => true]));
        Http::assertSentCount(1); // keys are cached
    });

    it('accepts a private relay email as verified and drops unverified emails', function () {
        $verifier = app(AppleIdTokenVerifier::class);

        $relay = $verifier->verify(appleToken($this->pem, 'a1', ['email' => 'x7k2@privaterelay.appleid.com', 'is_private_email' => 'true']));
        $unverified = $verifier->verify(appleToken($this->pem, 'a1', ['email_verified' => 'false']));
        $none = $verifier->verify(appleToken($this->pem, 'a1', ['email' => null, 'email_verified' => null]));

        expect($relay->email)->toBe('x7k2@privaterelay.appleid.com')
            ->and($unverified->email)->toBeNull()
            ->and($none->email)->toBeNull();
    });

    it('rejects wrong audience, issuer, expiry, signature, nonce and garbage', function () {
        $verifier = app(AppleIdTokenVerifier::class);
        [$otherPem] = rsaKey('a1');

        expectSocialTokenInvalid(fn () => $verifier->verify(appleToken($this->pem, 'a1', ['aud' => 'com.someone.else'])));
        expectSocialTokenInvalid(fn () => $verifier->verify(appleToken($this->pem, 'a1', ['iss' => 'https://accounts.google.com'])));
        expectSocialTokenInvalid(fn () => $verifier->verify(appleToken($this->pem, 'a1', ['exp' => time() - 600])));
        expectSocialTokenInvalid(fn () => $verifier->verify(appleToken($otherPem, 'a1')));
        expectSocialTokenInvalid(fn () => $verifier->verify(appleToken($this->pem, 'a1'), 'another-nonce'));
        expectSocialTokenInvalid(fn () => $verifier->verify(appleToken($this->pem, 'a1', ['nonce' => null]), 'raw-nonce-1'));
        expectSocialTokenInvalid(fn () => $verifier->verify('junk'));
    });

    it('refetches the keys once when the key id is unknown (rotation)', function () {
        $verifier = app(AppleIdTokenVerifier::class);
        $verifier->verify(appleToken($this->pem, 'a1'));

        expectSocialTokenInvalid(fn () => $verifier->verify(appleToken($this->pem, 'a2')));
        Http::assertSentCount(2);
    });

    it('refuses everything when no client ID is configured', function () {
        config()->set('services.apple.client_ids', []);

        expectSocialTokenInvalid(fn () => app(AppleIdTokenVerifier::class)->verify(appleToken($this->pem, 'a1')));
        Http::assertNothingSent();
    });

    it('signs up through the API with the name the app sends, checking the nonce', function () {
        $this->postJson('/api/v1/auth/social/apple', [
            'token' => appleToken($this->pem, 'a1', ['email' => 'x7k2@privaterelay.appleid.com']),
            'nonce' => 'raw-nonce-1',
            'display_name' => 'Moe',
            'birth_year' => 1985,
            'birth_month' => 3,
            'device_name' => 'iPhone 16',
            'platform' => 'ios',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.display_name', 'Moe')
            ->assertJsonPath('data.user.email', 'x7k2@privaterelay.appleid.com');

        $this->postJson('/api/v1/auth/social/apple', [
            'token' => appleToken($this->pem, 'a1'),
            'nonce' => 'wrong',
            'device_name' => 'iPhone 16',
            'platform' => 'ios',
        ])->assertUnauthorized()->assertJsonPath('code', 'social_token_invalid');

        // Signing in again: Apple sends no name and no birth data is needed.
        $this->postJson('/api/v1/auth/social/apple', [
            'token' => appleToken($this->pem, 'a1', ['email' => 'x7k2@privaterelay.appleid.com']),
            'device_name' => 'iPad',
            'platform' => 'ios',
        ])->assertOk()->assertJsonPath('data.user.display_name', 'Moe');
    });
});

it('verifies the nonce when linking Apple to a signed-in adult', function () {
    config()->set('services.apple.client_ids', ['app.brainyclan']);
    [$pem, $jwk] = rsaKey('a1');
    Http::fake([AppleIdTokenVerifier::JWKS_URL => Http::response(['keys' => [$jwk]], 200)]);
    $user = User::factory()->aged(35)->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/me/social-accounts/apple', ['token' => appleToken($pem, 'a1'), 'nonce' => 'wrong'])
        ->assertUnauthorized()->assertJsonPath('code', 'social_token_invalid');
    expect($user->socialAccounts()->count())->toBe(0);

    $this->postJson('/api/v1/me/social-accounts/apple', ['token' => appleToken($pem, 'a1'), 'nonce' => 'raw-nonce-1'])
        ->assertCreated()
        ->assertJsonPath('data.provider', 'apple')
        ->assertJsonPath('data.email', 'mom@example.com');
});
