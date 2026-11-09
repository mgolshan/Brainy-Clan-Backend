<?php

use App\Filament\Resources\SupportMessages\Pages\ListSupportMessages;
use App\Filament\Resources\SupportMessages\Pages\ViewSupportMessage;
use App\Filament\Resources\SupportMessages\SupportMessageResource;
use App\Filament\Widgets\SupportInbox;
use App\Modules\Identity\Models\User;
use App\Modules\Support\Mail\SupportReplyMail;
use App\Modules\Support\Models\SupportMessage;
use App\Modules\Support\Models\SupportMessageReply;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/**
 * Filament "Support inbox" and the dashboard card (docs/PLAN.md "Support channel without a public
 * mailto"): both staff roles read and answer, only admins delete, viewing marks new messages open,
 * user text is escaped, no N+1.
 */
beforeEach(function () {
    Mail::fake();
});

/**
 * App and website messages in every status, with replies.
 */
function supportInboxData(int $perStatus = 1): void
{
    foreach (SupportMessage::STATUSES as $status) {
        foreach (range(1, $perStatus) as $i) {
            $owner = familyOwner();
            $app = SupportMessage::factory()->fromMember($owner->load('user'))->status($status)->create();
            $web = SupportMessage::factory()->status($status)->create();

            foreach ([$app, $web] as $message) {
                SupportMessageReply::query()->create(['support_message_id' => $message->id, 'staff_user_id' => User::factory()->staff()->create()->id, 'body' => 'Reply '.$i, 'sent_at' => now()]);
            }
        }
    }
}

it('lists messages with status, source and topic filters and search, for both staff roles', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());
    $new = SupportMessage::factory()->create(['name' => 'Findable Person', 'topic' => 'bug', 'message' => 'The maze freezes']);
    $closed = SupportMessage::factory()->status(SupportMessage::STATUS_CLOSED)->create(['topic' => 'billing']);
    $app = SupportMessage::factory()->fromMember(familyOwner()->load('user'))->create(['topic' => 'idea']);

    $this->get('/admin/support-messages')->assertOk()->assertSee('Support inbox');

    Livewire::test(ListSupportMessages::class)
        ->assertCanSeeTableRecords([$new, $closed, $app])
        ->filterTable('status', SupportMessage::STATUS_NEW)
        ->assertCanSeeTableRecords([$new, $app])
        ->assertCanNotSeeTableRecords([$closed])
        ->resetTableFilters()
        ->filterTable('source', SupportMessage::SOURCE_APP)
        ->assertCanSeeTableRecords([$app])
        ->assertCanNotSeeTableRecords([$new, $closed])
        ->resetTableFilters()
        ->filterTable('topic', 'billing')
        ->assertCanSeeTableRecords([$closed])
        ->assertCanNotSeeTableRecords([$new, $app])
        ->resetTableFilters()
        ->searchTable('Findable')
        ->assertCanSeeTableRecords([$new])
        ->assertCanNotSeeTableRecords([$closed, $app])
        ->searchTable('maze freezes')
        ->assertCanSeeTableRecords([$new]);
})->with(['staff', 'support']);

it('shows the unread count in the navigation badge', function () {
    $this->actingAs(User::factory()->support()->create());
    SupportMessage::factory()->count(3)->create();
    SupportMessage::factory()->status(SupportMessage::STATUS_OPEN)->create();

    expect(SupportMessageResource::getNavigationBadge())->toBe('3');

    SupportMessage::query()->update(['status' => SupportMessage::STATUS_CLOSED]);
    expect(SupportMessageResource::getNavigationBadge())->toBeNull();
});

it('keeps the list at a constant number of queries', function () {
    $this->actingAs(User::factory()->staff()->create());

    $count = function (): int {
        $this->get('/admin/support-messages')->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/admin/support-messages')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    supportInboxData(1);
    $small = $count();
    supportInboxData(3);

    expect($count())->toBe($small);
});

it('shows the full message escaped, the details and the reply history, and marks a new message open', function () {
    $this->actingAs(User::factory()->support()->create());
    $owner = familyOwner();
    $message = SupportMessage::factory()->fromMember($owner->load('user'))->create(['message' => "<script>alert(1)</script>\nLine two **bold**", 'name' => '<b>Evil</b>']);
    SupportMessageReply::query()->create(['support_message_id' => $message->id, 'staff_user_id' => User::factory()->staff()->create(['display_name' => 'Agent Ana'])->id, 'body' => '<i>We fixed it</i>', 'sent_at' => now()]);

    $html = (string) $this->get('/admin/support-messages/'.$message->id)->assertOk()->getContent();

    expect($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->toContain('Line two **bold**')
        ->toContain('&lt;b&gt;Evil&lt;/b&gt;')
        ->toContain('&lt;i&gt;We fixed it&lt;/i&gt;')
        ->toContain('Agent Ana')
        ->toContain('iPhone 15 (iOS 18.1)')
        ->toContain($owner->user->email)
        ->not->toContain('<script>alert(1)')
        ->not->toContain('<i>We fixed it</i>')
        ->and($message->fresh()->status)->toBe(SupportMessage::STATUS_OPEN);

    // An answered message keeps its status when viewed.
    $answered = SupportMessage::factory()->status(SupportMessage::STATUS_ANSWERED)->create();
    $this->get('/admin/support-messages/'.$answered->id)->assertOk();
    expect($answered->fresh()->status)->toBe(SupportMessage::STATUS_ANSWERED);
});

it('replies by email in the sender\'s language, stores the reply and marks the message answered', function (string $role) {
    $staff = User::factory()->{$role}()->create();
    $this->actingAs($staff);
    $message = SupportMessage::factory()->create(['email' => 'parent@example.com', 'locale' => 'fr']);

    Livewire::test(ViewSupportMessage::class, ['record' => $message->getKey()])
        ->callAction('reply', data: ['body' => "Bonjour,\n\nC’est réglé."])
        ->assertHasNoActionErrors()
        ->assertNotified();

    $message->refresh();
    $reply = SupportMessageReply::query()->sole();

    expect($message->status)->toBe(SupportMessage::STATUS_ANSWERED)
        ->and($message->answered_at)->not->toBeNull()
        ->and($reply->body)->toBe("Bonjour,\n\nC’est réglé.")
        ->and($reply->staff_user_id)->toBe($staff->id);

    Mail::assertQueued(SupportReplyMail::class, fn (SupportReplyMail $mail) => $mail->hasTo('parent@example.com')
        && $mail->hasReplyTo(config('legal.support_email'))
        && $mail->locale === 'fr');
})->with(['staff', 'support']);

it('replies to an app message at the account\'s current email', function () {
    $this->actingAs(User::factory()->staff()->create());
    $owner = familyOwner();
    $message = SupportMessage::factory()->fromMember($owner->load('user'))->create();
    $owner->user->forceFill(['email' => 'new-address@example.com'])->save();

    Livewire::test(ViewSupportMessage::class, ['record' => $message->getKey()])
        ->callAction('reply', data: ['body' => 'Hello'])
        ->assertHasNoActionErrors();

    Mail::assertQueued(SupportReplyMail::class, fn (SupportReplyMail $mail) => $mail->hasTo('new-address@example.com'));
});

it('requires a reply text', function () {
    $this->actingAs(User::factory()->staff()->create());
    $message = SupportMessage::factory()->create();

    Livewire::test(ViewSupportMessage::class, ['record' => $message->getKey()])
        ->callAction('reply', data: ['body' => ''])
        ->assertHasActionErrors(['body' => 'required']);

    Mail::assertNothingQueued();
    expect(SupportMessageReply::query()->count())->toBe(0);
});

it('closes and re-opens a message', function () {
    $this->actingAs(User::factory()->support()->create());
    $message = SupportMessage::factory()->status(SupportMessage::STATUS_OPEN)->create();

    Livewire::test(ViewSupportMessage::class, ['record' => $message->getKey()])
        ->assertActionHidden('markOpen')
        ->callAction('close');

    expect($message->fresh()->status)->toBe(SupportMessage::STATUS_CLOSED)
        ->and($message->fresh()->closed_at)->not->toBeNull();

    Livewire::test(ViewSupportMessage::class, ['record' => $message->getKey()])
        ->assertActionHidden('close')
        ->callAction('markOpen');

    expect($message->fresh()->status)->toBe(SupportMessage::STATUS_OPEN)
        ->and($message->fresh()->closed_at)->toBeNull();
});

it('lets only admins delete a message', function () {
    $message = SupportMessage::factory()->create();

    $this->actingAs(User::factory()->support()->create());
    Livewire::test(ViewSupportMessage::class, ['record' => $message->getKey()])->assertActionHidden(DeleteAction::class);

    $this->actingAs(User::factory()->staff()->create());
    Livewire::test(ViewSupportMessage::class, ['record' => $message->getKey()])->callAction(DeleteAction::class);

    expect($message->fresh())->toBeNull();
});

it('keeps people without a staff role out', function () {
    $message = SupportMessage::factory()->create();
    $this->actingAs(User::factory()->aged(40)->create());

    $this->get('/admin/support-messages')->assertForbidden();
    $this->get('/admin/support-messages/'.$message->id)->assertForbidden();
});

describe('dashboard card', function () {
    it('shows new messages in warning colour, the unanswered count and the oldest age, linking to the inbox filtered to new', function () {
        $this->actingAs(User::factory()->support()->create());
        SupportMessage::factory()->count(2)->create();
        SupportMessage::factory()->status(SupportMessage::STATUS_OPEN)->create(['created_at' => now()->subDays(3)]);
        SupportMessage::factory()->status(SupportMessage::STATUS_ANSWERED)->create(['created_at' => now()->subDays(9)]);
        SupportMessage::factory()->status(SupportMessage::STATUS_CLOSED)->create(['created_at' => now()->subDays(20)]);

        Livewire::test(SupportInbox::class)->assertOk()
            ->assertSee('Support messages')
            ->assertSee('2 new')
            ->assertSee('3 unanswered · oldest 3 days old')
            ->assertSee('fi-color-warning', false)
            ->assertSee(e(SupportMessageResource::getUrl('index', ['filters' => ['status' => ['value' => 'new']]])), false);

        // Exact right away (no cache): a new message and a status change show at once.
        SupportMessage::factory()->create();
        SupportMessage::query()->where('status', 'open')->update(['status' => 'answered']);

        Livewire::test(SupportInbox::class)->assertSee('3 new')->assertSee('3 unanswered');
    });

    it('is calm when nothing waits, and runs one query', function () {
        $this->actingAs(User::factory()->staff()->create());
        SupportMessage::factory()->status(SupportMessage::STATUS_CLOSED)->count(3)->create();

        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(SupportInbox::class)->assertOk()->assertSee('0 new')->assertSee('0 unanswered')->assertDontSee('oldest');
        $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => str_contains($sql, 'support_messages'));
        DB::disableQueryLog();

        expect($queries)->toHaveCount(1);
    });
});
