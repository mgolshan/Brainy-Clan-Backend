<?php

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;
use Laravel\Sanctum\Sanctum;

it('creates a family and makes the caller owner and admin', function () {
    $user = User::factory()->create(['display_name' => 'Moe']);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/families', ['name' => 'The Golshans', 'timezone' => 'America/Toronto', 'country_code' => 'ca'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'The Golshans')
        ->assertJsonPath('data.country_code', 'CA')
        ->assertJsonPath('data.tier', 'free')
        ->assertJsonPath('data.owner_user_id', $user->id)
        ->assertJsonPath('data.member_count', 1);

    $member = FamilyMember::query()->sole();
    expect($member->role)->toBe('admin')->and($member->nickname)->toBe('Moe');
});

it('refuses a second family with already_in_family', function () {
    actingAsMember(familyOwner());

    $this->postJson('/api/v1/families', ['name' => 'Another', 'timezone' => 'UTC', 'country_code' => 'US'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'already_in_family');
});

it('validates the timezone and country', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/families', ['name' => 'X', 'timezone' => 'Mars/Base', 'country_code' => 'CAN'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['timezone', 'country_code']);
});

it('shows the family with members and hides birth data from non-admins', function () {
    $owner = familyOwner(['tier' => 'family']);
    $kid = addMember($owner->family, 9);
    $teen = addMember($owner->family, 14);

    actingAsMember($kid);
    $response = $this->getJson('/api/v1/family')->assertOk()->assertJsonCount(3, 'data.members');

    $members = collect($response->json('data.members'))->keyBy('id');
    expect($members[$kid->id])->toHaveKey('birth_year')
        ->and($members[$teen->id])->not->toHaveKey('birth_year')
        ->and($members[$owner->id])->not->toHaveKey('birth_year')
        ->and($members[$teen->id]['age_band'])->toBe('teen');

    app('auth')->forgetGuards();
    actingAsMember($owner);
    $members = collect($this->getJson('/api/v1/family')->json('data.members'))->keyBy('id');
    expect($members[$teen->id])->toHaveKey('birth_year');
});

it('requires a family for GET /family', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/family')->assertStatus(409)->assertJsonPath('code', 'no_family');
});

it('lets admins update the family and forbids members', function () {
    $owner = familyOwner(['tier' => 'family']);
    $kid = addMember($owner->family, 9);

    actingAsMember($kid);
    $this->patchJson('/api/v1/family', ['name' => 'Kids rule'])->assertStatus(403)->assertJsonPath('code', 'forbidden');

    app('auth')->forgetGuards();
    actingAsMember($owner);
    $this->patchJson('/api/v1/family', ['name' => 'The Bests', 'timezone' => 'Europe/Paris'])
        ->assertOk()
        ->assertJsonPath('data.name', 'The Bests')
        ->assertJsonPath('data.timezone', 'Europe/Paris');
});

it('lets an admin update a member', function () {
    $owner = familyOwner(['tier' => 'family']);
    $kid = addMember($owner->family, 9);
    actingAsMember($owner);

    $this->patchJson("/api/v1/family/members/{$kid->id}", [
        'nickname' => 'Sammy',
        'avatar' => 'unicorn',
        'difficulty_override' => 6,
        'birth_year' => now()->year - 10,
        'birth_month' => 2,
        'role' => 'admin',
        'accessibility' => ['reduced_motion' => true],
    ])
        ->assertOk()
        ->assertJsonPath('data.nickname', 'Sammy')
        ->assertJsonPath('data.avatar', 'unicorn')
        ->assertJsonPath('data.difficulty_override', 6)
        ->assertJsonPath('data.birth_month', 2)
        ->assertJsonPath('data.role', 'admin')
        ->assertJsonPath('data.accessibility.reduced_motion', true);

    $this->patchJson("/api/v1/family/members/{$kid->id}", ['difficulty_override' => null])
        ->assertOk()->assertJsonPath('data.difficulty_override', null);
});

it('never demotes the owner', function () {
    $owner = familyOwner(['tier' => 'family']);
    $dad = addMember($owner->family, 40, 'admin');
    actingAsMember($dad);

    $this->patchJson("/api/v1/family/members/{$owner->id}", ['role' => 'member'])
        ->assertStatus(403)
        ->assertJsonPath('code', 'forbidden');
});

it('removes a member but never the owner', function () {
    $owner = familyOwner(['tier' => 'family']);
    $kid = addMember($owner->family, 9);
    $kid->user->createToken('tablet');
    actingAsMember($owner);

    $this->deleteJson("/api/v1/family/members/{$owner->id}")->assertStatus(403);
    $this->deleteJson("/api/v1/family/members/{$kid->id}")->assertNoContent();

    expect($kid->refresh()->status)->toBe('removed')
        ->and($kid->user->tokens()->count())->toBe(0);
    $this->getJson('/api/v1/family')->assertJsonCount(1, 'data.members');
});

it('returns 404 for members of another family', function () {
    $owner = familyOwner();
    $stranger = addMember(familyOwner()->family, 9);
    actingAsMember($owner);

    $this->patchJson("/api/v1/family/members/{$stranger->id}", ['nickname' => 'X'])->assertStatus(404)->assertJsonPath('code', 'not_found');
    $this->deleteJson("/api/v1/family/members/{$stranger->id}")->assertStatus(404);
});
