<?php

use App\Filament\Resources\Families\Pages\ViewFamily;
use App\Filament\Resources\Families\RelationManagers\ConsentsRelationManager;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\Invite;
use App\Modules\Identity\Models\Consent;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Consents are visible read-only on the family page in the admin, without N+1 queries.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->staff()->create());
});

/**
 * A family whose owner accepted the terms and gave parental consent for `$children` children
 * (joined) plus one pending child invite.
 */
function consentFamily(int $children): FamilyMember
{
    $owner = familyOwner(['tier' => 'family']);
    Consent::factory()->create(['user_id' => $owner->user_id]);
    Consent::factory()->privacy()->create(['user_id' => $owner->user_id]);

    for ($i = 0; $i < $children; $i++) {
        $child = addMember($owner->family, 8);
        Consent::factory()->parental()->create(['user_id' => $owner->user_id, 'subject_member_id' => $child->id]);
    }

    $invite = Invite::factory()->create(['family_id' => $owner->family_id, 'created_by' => $owner->user_id, 'nickname' => 'Pending kid']);
    Consent::factory()->parental()->create(['user_id' => $owner->user_id, 'subject_invite_id' => $invite->id]);

    return $owner;
}

it('lists the family consents read-only with who gave them and for which child', function () {
    $owner = consentFamily(2);
    $other = consentFamily(1);
    $family = Family::query()->findOrFail($owner->family_id);

    Livewire::test(ConsentsRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class])
        ->assertOk()
        ->assertCanSeeTableRecords(Consent::query()->where('user_id', $owner->user_id)->get())
        ->assertCanNotSeeTableRecords(Consent::query()->where('user_id', $other->user_id)->get())
        ->assertSee('Pending kid (invite)')
        ->assertSee('parental');

    expect((new ConsentsRelationManager)->isReadOnly())->toBeTrue();
});

it('keeps the consents relation manager at a constant number of queries', function () {
    $count = function (FamilyMember $owner): int {
        $family = Family::query()->findOrFail($owner->family_id);
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(ConsentsRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class])->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $count(consentFamily(1));

    expect($count(consentFamily(6)))->toBe($count(consentFamily(2)));
});
