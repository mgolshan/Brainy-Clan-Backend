<?php

use App\Modules\Content\Models\Category;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;
use App\Modules\Scoring\Actions\AppendPoints;
use App\Modules\Scoring\Models\PointEntry;
use App\Modules\Scoring\Models\Streak;
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

/**
 * Append a ledger entry for this member (negative = a compensating correction).
 */
function earnPoints(FamilyMember $member, int $amount): void
{
    $categoryId = Category::query()->value('id') ?? Category::factory()->create()->id;
    $reason = $amount < 0 ? PointEntry::REASON_CORRECTION : PointEntry::REASON_CORRECT_ANSWER;

    app(AppendPoints::class)->handle($member->family_id, $member->id, (int) $categoryId, $amount, $reason);
}

/**
 * Set the member's streak record (longest and current days).
 */
function setStreak(FamilyMember $member, int $longest, int $current = 0): void
{
    Streak::query()->updateOrCreate(['member_id' => $member->id], ['longest' => $longest, 'current' => $current, 'last_active_date' => null]);
}

/**
 * Unlock every seeded theme world for the member: family plan, 1000 points, a 30-day streak.
 */
function unlockAllWorlds(FamilyMember $member): FamilyMember
{
    $member->family->forceFill(['tier' => Family::TIER_FAMILY])->save();
    earnPoints($member, 1000);
    setStreak($member, 30);

    return $member;
}
