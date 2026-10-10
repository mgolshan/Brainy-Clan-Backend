<?php

use App\Modules\Family\Models\Invite;

it('creates an invite with a readable code valid for 7 days', function () {
    $owner = familyOwner();
    actingAsMember($owner);

    $response = $this->postJson('/api/v1/family/invites', ['nickname' => 'Sam', 'birth_year' => now()->year - 9, 'birth_month' => 3, 'avatar' => 'fox'])
        ->assertCreated()
        ->assertJsonPath('data.nickname', 'Sam')
        ->assertJsonPath('data.avatar', 'fox')
        ->assertJsonPath('data.age_band', 'child')
        ->assertJsonPath('data.birth_month', 3);

    expect($response->json('data.code'))->toMatch('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{8}$/');

    $invite = Invite::query()->sole();
    expect((int) round(now()->diffInDays($invite->expires_at)))->toBe(7);
});

it('enforces the tier member limit (members + pending invites)', function () {
    $owner = familyOwner(); // free tier: max 2 members
    actingAsMember($owner);

    $this->postJson('/api/v1/family/invites', ['nickname' => 'One', 'birth_year' => 2015, 'birth_month' => 1])->assertCreated();
    $this->postJson('/api/v1/family/invites', ['nickname' => 'Two', 'birth_year' => 2015, 'birth_month' => 1])
        ->assertStatus(403)
        ->assertJsonPath('code', 'member_limit_reached');
});

it('allows more members on the family plan', function () {
    $owner = familyOwner(['tier' => 'family']);
    actingAsMember($owner);

    foreach (range(1, 6) as $i) {
        $this->postJson('/api/v1/family/invites', ['nickname' => "Kid {$i}", 'birth_year' => 2015, 'birth_month' => 1])->assertCreated();
    }

    $this->postJson('/api/v1/family/invites', ['nickname' => 'Kid 7', 'birth_year' => 2015, 'birth_month' => 1])->assertStatus(403);
});

it('lists only pending invites and deletes them', function () {
    $owner = familyOwner(['tier' => 'family']);
    $pending = Invite::factory()->create(['family_id' => $owner->family_id]);
    Invite::factory()->used()->create(['family_id' => $owner->family_id]);
    Invite::factory()->expired()->create(['family_id' => $owner->family_id]);
    $foreign = Invite::factory()->create();
    actingAsMember($owner);

    $this->getJson('/api/v1/family/invites')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $pending->id);

    $this->deleteJson("/api/v1/family/invites/{$foreign->id}")->assertStatus(404);
    $this->deleteJson("/api/v1/family/invites/{$pending->id}")->assertNoContent();
    expect(Invite::query()->whereKey($pending->id)->exists())->toBeFalse();
});

it('forbids invites for non-admins and validates input', function () {
    $owner = familyOwner(['tier' => 'family']);
    $kid = addMember($owner->family, 9);
    actingAsMember($kid);

    $this->postJson('/api/v1/family/invites', ['nickname' => 'X', 'birth_year' => 2015, 'birth_month' => 1])->assertStatus(403);
    $this->getJson('/api/v1/family/invites')->assertStatus(403);

    app('auth')->forgetGuards();
    actingAsMember($owner);
    $this->postJson('/api/v1/family/invites', ['birth_month' => 13])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['nickname', 'birth_year', 'birth_month']);
});
