<?php

use App\Modules\Policy\Models\Setting;

it('returns health without auth', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.api_version', '1')
        ->assertJsonPath('data.min_app_version', '1.0.0')
        ->assertJsonStructure(['data' => ['time']]);
});

it('answers 426 when the app is older than min_app_version', function () {
    Setting::query()->where('key', 'min_app_version')->firstOrFail()->update(['value' => '1.2.0']);

    $this->getJson('/api/v1/me', ['X-App-Version' => '1.1.9'])
        ->assertStatus(426)
        ->assertJsonPath('code', 'app_update_required');
});

it('lets a current app version through and exempts health from the version check', function () {
    Setting::query()->where('key', 'min_app_version')->firstOrFail()->update(['value' => '2.0.0']);

    $this->getJson('/api/v1/health', ['X-App-Version' => '1.0.0'])->assertOk()
        ->assertJsonPath('data.min_app_version', '2.0.0');
    $this->getJson('/api/v1/me', ['X-App-Version' => '2.0.0'])->assertStatus(401);
});

it('uses the contract error format', function () {
    $this->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertExactJson(['message' => 'Unauthenticated.', 'code' => 'unauthenticated']);

    $this->getJson('/api/v1/does-not-exist')
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');

    $this->postJson('/api/v1/auth/login', [])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonStructure(['message', 'code', 'errors' => ['email', 'password', 'device_name', 'platform']]);
});

it('allows the Expo web origin via CORS', function () {
    $this->withHeaders([
        'Origin' => 'http://localhost:8081',
        'Access-Control-Request-Method' => 'POST',
    ])->options('/api/v1/auth/login')
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:8081');
});

it('rate limits auth endpoints to 10 per minute per IP', function () {
    // Different emails, so the sign-in lockout (5 failures per email + IP) does not kick in first.
    foreach (range(1, 10) as $i) {
        $this->postJson('/api/v1/auth/login', ['email' => "x{$i}@example.com", 'password' => 'nope', 'device_name' => 't', 'platform' => 'web'])
            ->assertStatus(401);
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'x11@example.com', 'password' => 'nope', 'device_name' => 't', 'platform' => 'web'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'rate_limited');
});
