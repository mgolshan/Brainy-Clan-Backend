<?php

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\NotificationPreference;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

const EXPO_TOKEN = 'ExponentPushToken[abcDEF123_-xyz]';

/**
 * A real device token for this member (Sanctum::actingAs has no device).
 */
function deviceTokenFor(FamilyMember $member, string $name = 'Phone'): string
{
    return app(IssueDeviceToken::class)->handle($member->user, $name, 'ios');
}

beforeEach(function () {
    $this->owner = familyOwner();
    $this->family = $this->owner->family;
});

// ---------------------------------------------------------------------------------
// Push token
// ---------------------------------------------------------------------------------

it('stores the push token on the current device and clears it', function () {
    $token = deviceTokenFor($this->owner);

    $this->withToken($token)->putJson('/api/v1/me/push-token', ['token' => EXPO_TOKEN, 'platform' => 'android'])->assertNoContent();

    $device = Device::query()->where('user_id', $this->owner->user_id)->sole();
    expect($device->push_token)->toBe(EXPO_TOKEN)->and($device->platform)->toBe('android');

    $this->withToken($token)->deleteJson('/api/v1/me/push-token')->assertNoContent();
    expect($device->fresh()->push_token)->toBeNull();
});

it('moves a push token away from another device', function () {
    $child = addMember($this->family);
    $first = deviceTokenFor($this->owner);
    $second = deviceTokenFor($child, 'Tablet');

    $this->withToken($first)->putJson('/api/v1/me/push-token', ['token' => EXPO_TOKEN, 'platform' => 'ios'])->assertNoContent();
    app('auth')->forgetGuards();
    $this->withToken($second)->putJson('/api/v1/me/push-token', ['token' => EXPO_TOKEN, 'platform' => 'ios'])->assertNoContent();

    expect(Device::query()->where('push_token', EXPO_TOKEN)->pluck('user_id')->all())->toBe([$child->user_id]);
});

it('rejects a push token that is not an Expo token', function () {
    $this->withToken(deviceTokenFor($this->owner))
        ->putJson('/api/v1/me/push-token', ['token' => 'not-a-token', 'platform' => 'ios'])
        ->assertStatus(422)->assertJsonValidationErrors('token');
});

it('drops the push token with the device on logout', function () {
    $token = deviceTokenFor($this->owner);
    $this->withToken($token)->putJson('/api/v1/me/push-token', ['token' => EXPO_TOKEN, 'platform' => 'ios'])->assertNoContent();

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

    expect(Device::query()->where('push_token', EXPO_TOKEN)->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------------
// Preferences
// ---------------------------------------------------------------------------------

it('returns adult defaults', function () {
    actingAsMember($this->owner);

    $this->getJson('/api/v1/me/notification-preferences')->assertOk()->assertExactJson(['data' => [
        'types' => ['goal_reached' => true, 'period_closed' => true, 'family_activity' => true, 'daily_reminder' => false, 'family_chat' => true, 'family_chat_report' => true],
        'quiet_from' => '21:30',
        'quiet_to' => '07:00',
        'reminder_time' => '17:00',
    ]]);
});

it('returns child quiet hours for a child', function () {
    actingAsMember(addMember($this->family, 9));

    $this->getJson('/api/v1/me/notification-preferences')->assertOk()
        ->assertJsonPath('data.quiet_from', '19:30')
        ->assertJsonPath('data.quiet_to', '07:30');
});

it('updates my preferences partially', function () {
    actingAsMember($this->owner);

    $this->patchJson('/api/v1/me/notification-preferences', ['types' => ['daily_reminder' => true], 'reminder_time' => '18:15'])
        ->assertOk()
        ->assertJsonPath('data.types.daily_reminder', true)
        ->assertJsonPath('data.types.goal_reached', true)
        ->assertJsonPath('data.reminder_time', '18:15')
        ->assertJsonPath('data.quiet_from', '21:30');

    $this->patchJson('/api/v1/me/notification-preferences', ['quiet_from' => null, 'quiet_to' => null])->assertOk();

    $this->getJson('/api/v1/me/notification-preferences')->assertOk()
        ->assertJsonPath('data.quiet_from', null)
        ->assertJsonPath('data.types.daily_reminder', true)
        ->assertJsonPath('data.reminder_time', '18:15');
    expect(NotificationPreference::query()->count())->toBe(1);
});

it('validates preference times and types', function () {
    actingAsMember($this->owner);

    $this->patchJson('/api/v1/me/notification-preferences', ['quiet_from' => '25:00', 'reminder_time' => '7pm', 'types' => ['spam' => true]])
        ->assertStatus(422)->assertJsonValidationErrors(['quiet_from', 'reminder_time', 'types']);

    $this->patchJson('/api/v1/me/notification-preferences', ['quiet_from' => null])
        ->assertStatus(422)->assertJsonValidationErrors('quiet_from');
});

it('needs a family for preferences', function () {
    $user = User::factory()->aged(30)->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me/notification-preferences')->assertStatus(409);
});

it('lets an admin read and change a child\'s preferences', function () {
    $child = addMember($this->family, 8);
    actingAsMember($this->owner);

    $this->getJson("/api/v1/family/members/{$child->id}/notification-preferences")->assertOk()->assertJsonPath('data.quiet_from', '19:30');

    $this->patchJson("/api/v1/family/members/{$child->id}/notification-preferences", ['quiet_from' => '19:00', 'quiet_to' => '08:00'])
        ->assertOk()->assertJsonPath('data.quiet_from', '19:00');

    app('auth')->forgetGuards();
    actingAsMember($child);
    $this->getJson('/api/v1/me/notification-preferences')->assertOk()
        ->assertJsonPath('data.quiet_from', '19:00')->assertJsonPath('data.quiet_to', '08:00');
});

it('forbids non-admins from member preferences', function () {
    $child = addMember($this->family, 8);
    $adult = addMember($this->family, 35);
    actingAsMember($adult);

    $this->getJson("/api/v1/family/members/{$child->id}/notification-preferences")->assertForbidden();
    $this->patchJson("/api/v1/family/members/{$child->id}/notification-preferences", ['quiet_from' => '19:00', 'quiet_to' => '08:00'])->assertForbidden();
});

it('hides members of another family', function () {
    $other = addMember(familyOwner()->family, 8);
    actingAsMember($this->owner);

    $this->getJson("/api/v1/family/members/{$other->id}/notification-preferences")->assertNotFound();
    $this->patchJson("/api/v1/family/members/{$other->id}/notification-preferences", ['reminder_time' => '10:00'])->assertNotFound();
});

it('reads preferences with a bounded number of queries', function () {
    $child = addMember($this->family, 8);
    NotificationPreference::query()->create(['family_member_id' => $child->id, 'types' => ['daily_reminder' => true], 'quiet_from' => '19:00', 'quiet_to' => '07:00', 'reminder_time' => '17:00']);
    actingAsMember($this->owner);
    $url = "/api/v1/family/members/{$child->id}/notification-preferences";
    $this->getJson($url)->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->getJson($url)->assertOk()->assertJsonPath('data.types.daily_reminder', true);
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($count)->toBeLessThanOrEqual(10);
});
