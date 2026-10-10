<?php

use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * A new family (free tier unless overridden) and its owner's admin membership.
 *
 * @param  array<string, mixed>  $familyAttributes
 */
function familyOwner(array $familyAttributes = [], int $age = 40): FamilyMember
{
    $owner = User::factory()->aged($age)->create();
    $family = Family::factory()->create(['owner_user_id' => $owner->id, ...$familyAttributes]);

    return $family->members()->where('user_id', $owner->id)->firstOrFail();
}

/**
 * Add a member to a family. Ages under 18 create a child account (no email/password).
 */
function addMember(Family $family, int $age = 9, string $role = FamilyMember::ROLE_MEMBER): FamilyMember
{
    $user = $age < 18 ? User::factory()->child($age)->create() : User::factory()->aged($age)->create();

    return FamilyMember::factory()->create([
        'family_id' => $family->id,
        'user_id' => $user->id,
        'role' => $role,
        'nickname' => $user->display_name,
    ]);
}

/**
 * Authenticate the API as this member's user.
 */
function actingAsMember(FamilyMember $member): User
{
    $user = $member->user;
    Sanctum::actingAs($user);

    return $user;
}
