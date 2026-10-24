<?php

use App\Filament\Resources\Families\Pages\ViewFamily;
use App\Filament\Resources\Families\RelationManagers\AllMembersRelationManager;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Family\Mail\ChildDataExport;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Actions\EraseUser;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\SocialAccount;
use App\Modules\Identity\Models\User;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * B11 staff privacy actions on a family member (admin only), for verified parent requests sent
 * from the family owner's own email address: "Start data export" mails a child account's export
 * to the owner; "Delete child account" removes and erases it after typing the nickname. Plus the
 * `staff:role` command.
 */

/**
 * @return array{family: Family, owner: FamilyMember, kid: FamilyMember, adult: FamilyMember}
 */
function privacyFamily(): array
{
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    $adult = addMember($owner->family, 35);
    Device::factory()->create(['user_id' => $kid->user_id]);

    return ['family' => Family::query()->findOrFail($owner->family_id), 'owner' => $owner, 'kid' => $kid, 'adult' => $adult];
}

function membersManager(Family $family): mixed
{
    return Livewire::test(AllMembersRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class]);
}

beforeEach(function () {
    $this->admin = User::factory()->staff()->create();
    $this->actingAs($this->admin);
});

it('offers the privacy actions only for child accounts, never for the owner or adults with a login', function () {
    ['family' => $family, 'owner' => $owner, 'kid' => $kid, 'adult' => $adult] = privacyFamily();

    membersManager($family)
        ->assertTableActionVisible('startDataExport', $kid)
        ->assertTableActionVisible('deleteChildAccount', $kid)
        ->assertTableActionHidden('startDataExport', $owner)
        ->assertTableActionHidden('deleteChildAccount', $owner)
        ->assertTableActionHidden('startDataExport', $adult)
        ->assertTableActionHidden('deleteChildAccount', $adult);
});

it('emails a child\'s data export to the family owner and logs it without personal data', function () {
    Mail::fake();
    ['family' => $family, 'owner' => $owner, 'kid' => $kid] = privacyFamily();
    ChatMessage::factory()->create(['family_id' => $family->id, 'family_member_id' => $kid->id, 'body' => 'Hi mom']);
    $owner->user->forceFill(['locale' => 'fr'])->save();

    membersManager($family)
        ->callTableAction('startDataExport', $kid)
        ->assertNotified("Data export of {$kid->nickname} emailed to {$owner->user->email}");

    Mail::assertSent(ChildDataExport::class, function (ChildDataExport $mail) use ($owner, $kid): bool {
        $attachment = $mail->attachments()[0];
        $json = $attachment->attachWith(fn () => null, fn (Closure $data): string => $data());
        $export = json_decode((string) $json, true);

        expect($mail->hasTo($owner->user->email))->toBeTrue()
            ->and($attachment)->toBeInstanceOf(Attachment::class)
            ->and($mail->envelope()->subject)->toBe('Copie des données Brainy Clan de '.$kid->nickname)
            ->and($export['data']['user']['id'])->toBe($kid->user_id)
            ->and($export['data']['chat_messages'][0]['body'])->toBe('Hi mom')
            ->and((string) $json)->not->toContain('"password"')->not->toContain('"push_token"');

        return true;
    });
    Mail::assertSentCount(1);

    $entry = Activity::query()->where('event', 'child_data_exported')->firstOrFail();
    expect($entry->causer_id)->toBe($this->admin->id)
        ->and($entry->subject_id)->toBe($family->id)
        ->and($entry->properties->toArray())->toBe(['member_id' => $kid->id, 'user_id' => $kid->user_id, 'sent_to' => 'family_owner'])
        ->and($entry->toJson())->not->toContain($kid->nickname)->not->toContain($owner->user->email);
});

it('sends the export mail right away instead of queueing it with the data', function () {
    ['family' => $family, 'kid' => $kid] = privacyFamily();

    membersManager($family)->callTableAction('startDataExport', $kid)->assertNotified();

    expect(Mail::getFacadeRoot()->mailer()->getSymfonyTransport()->messages())->toHaveCount(1);
});

it('does not export when the owner has no email address', function () {
    Mail::fake();
    ['family' => $family, 'owner' => $owner, 'kid' => $kid] = privacyFamily();
    $owner->user->forceFill(['email' => null])->save();
    SocialAccount::factory()->create(['user_id' => $owner->user_id]);

    membersManager($family)
        ->callTableAction('startDataExport', $kid)
        ->assertNotified('The family owner has no email address to send the export to.');

    Mail::assertNothingSent();
});

it('deletes a child account only after the nickname is typed, through the member removal flow', function () {
    ['family' => $family, 'kid' => $kid] = privacyFamily();
    $nickname = $kid->nickname;

    membersManager($family)
        ->callTableAction('deleteChildAccount', $kid, data: ['confirm_nickname' => 'wrong'])
        ->assertHasTableActionErrors(['confirm_nickname']);

    expect($kid->refresh()->status)->toBe(FamilyMember::STATUS_ACTIVE);

    membersManager($family)
        ->callTableAction('deleteChildAccount', $kid, data: ['confirm_nickname' => $nickname])
        ->assertHasNoTableActionErrors()
        ->assertNotified("{$nickname}'s account was deleted");

    $kid->refresh();
    $user = User::query()->findOrFail($kid->user_id);

    expect($kid->status)->toBe(FamilyMember::STATUS_REMOVED)
        ->and($kid->nickname)->toBe(EraseUser::ERASED_NAME)
        ->and($user->erased_at)->not->toBeNull()
        ->and($user->display_name)->toBe(EraseUser::ERASED_NAME)
        ->and(Device::query()->where('user_id', $user->id)->count())->toBe(0);

    $entry = Activity::query()->where('event', 'child_account_deleted')->firstOrFail();
    expect($entry->causer_id)->toBe($this->admin->id)
        ->and($entry->subject_id)->toBe($family->id)
        ->and($entry->properties->toArray())->toBe(['member_id' => $kid->id, 'user_id' => $kid->user_id, 'reason' => 'parent_request'])
        ->and($entry->toJson())->not->toContain($nickname);
});

it('sets and removes staff roles from the command line only', function () {
    $user = User::factory()->create(['email' => 'helper@example.com']);

    expect(Artisan::call('staff:role', ['email' => 'helper@example.com', 'role' => 'support']))->toBe(0)
        ->and($user->refresh()->staff_role)->toBe(User::STAFF_ROLE_SUPPORT)
        ->and($user->isStaff())->toBeTrue()
        ->and($user->isStaffAdmin())->toBeFalse();

    expect(Artisan::call('staff:role', ['email' => 'helper@example.com', 'role' => 'admin']))->toBe(0)
        ->and($user->refresh()->isStaffAdmin())->toBeTrue();

    expect(Artisan::call('staff:role', ['email' => 'helper@example.com', 'role' => 'none']))->toBe(0)
        ->and($user->refresh()->staff_role)->toBeNull();

    expect(Artisan::call('staff:role', ['email' => 'helper@example.com', 'role' => 'owner']))->toBe(1)
        ->and(Artisan::call('staff:role', ['email' => 'nobody@example.com', 'role' => 'admin']))->toBe(1)
        ->and(Activity::query()->where('event', 'staff_role_changed')->count())->toBe(3);
});

it('clears the staff role when the account is erased', function () {
    $staff = User::factory()->support()->create();

    app(EraseUser::class)->handle($staff);

    expect($staff->refresh()->staff_role)->toBeNull()
        ->and($staff->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
});
