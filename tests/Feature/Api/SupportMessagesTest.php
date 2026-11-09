<?php

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Actions\EraseUser;
use App\Modules\Identity\Models\User;
use App\Modules\Support\Actions\PruneSupportMessages;
use App\Modules\Support\Mail\SupportMessageReceived;
use App\Modules\Support\Mail\SupportReplyMail;
use App\Modules\Support\Models\SupportMessage;
use App\Modules\Support\Models\SupportMessageReply;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/**
 * Support messages (docs/API.md "Support messages", 2026-11-08): family admins only, idempotent by
 * client_id, 5/hour + 20/day, text in code points, mailed to the support inbox (Reply-To = the
 * admin), part of /me/export, deleted with the account, pruned after closing / inactivity.
 */
const SUPPORT_URL = '/api/v1/support/messages';

beforeEach(function () {
    Mail::fake();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function supportBody(array $overrides = []): array
{
    return [
        'topic' => 'billing',
        'message' => 'My Family plan shows Free after renewing.',
        'client_id' => (string) Str::uuid(),
        'app_version' => '1.0.0',
        'platform' => 'ios',
        'device' => 'iPad (iPadOS 18.1)',
        ...$overrides,
    ];
}

function supportAs(FamilyMember $member): void
{
    app('auth')->forgetGuards();
    actingAsMember($member);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function supportPost(array $overrides = []): TestResponse
{
    return test()->postJson(SUPPORT_URL, supportBody($overrides));
}

describe('POST /support/messages', function () {
    it('stores a message from a family admin and mails the support inbox with Reply-To = the admin', function () {
        $owner = familyOwner();
        supportAs($owner);

        $response = supportPost(['message' => "  Hello <b>team</b> *star* 🦊\r\n\r\n\r\n\r\nSecond paragraph  "])
            ->assertCreated()
            ->assertJsonPath('data.topic', 'billing')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.answered_at', null)
            ->assertJsonPath('data.message', "Hello <b>team</b> *star* 🦊\n\n\nSecond paragraph");

        expect(array_keys($response->json('data')))->toBe(['id', 'topic', 'message', 'status', 'created_at', 'answered_at']);

        $message = SupportMessage::query()->sole();
        expect($message->source)->toBe('app')
            ->and($message->status)->toBe('new')
            ->and($message->user_id)->toBe($owner->user_id)
            ->and($message->family_id)->toBe($owner->family_id)
            ->and($message->email)->toBe($owner->user->email)
            ->and($message->device)->toBe('iPad (iPadOS 18.1)')
            ->and($message->last_activity_at)->not->toBeNull();

        Mail::assertQueued(SupportMessageReceived::class, fn (SupportMessageReceived $mail) => $mail->hasTo(config('legal.support_email'))
            && $mail->hasReplyTo($owner->user->email)
            && $mail->supportMessage->is($message));
    });

    it('lets a co-admin write too', function () {
        $owner = familyOwner();
        $coAdmin = addMember($owner->family, 38, FamilyMember::ROLE_ADMIN);
        supportAs($coAdmin);

        supportPost()->assertCreated();
    });

    it('refuses children, teens, adult members, people without a family and staff with 403', function (string $who) {
        $owner = familyOwner();
        $member = match ($who) {
            'child' => addMember($owner->family, 9),
            'teen' => addMember($owner->family, 14),
            'adult member' => addMember($owner->family, 40),
            default => null,
        };

        if ($member !== null) {
            supportAs($member);
        } else {
            $user = $who === 'staff' ? User::factory()->staff()->create() : User::factory()->aged(40)->create();
            app('auth')->forgetGuards();
            Sanctum::actingAs($user);
        }

        // Also with an invalid body: 403 comes before validation.
        $this->postJson(SUPPORT_URL, ['topic' => 'nope'])->assertForbidden()->assertJsonPath('code', 'forbidden');
        supportPost()->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->getJson(SUPPORT_URL)->assertForbidden()->assertJsonPath('code', 'forbidden');

        expect(SupportMessage::query()->count())->toBe(0);
        Mail::assertNothingQueued();
    })->with(['child', 'teen', 'adult member', 'no family', 'staff']);

    it('requires a token', function () {
        $this->postJson(SUPPORT_URL, supportBody())->assertUnauthorized();
        $this->getJson(SUPPORT_URL)->assertUnauthorized();
    });

    it('validates the body', function (array $overrides, string $field) {
        supportAs(familyOwner());

        supportPost($overrides)->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors($field);
    })->with([
        'unknown topic' => [['topic' => 'refund'], 'topic'],
        'missing topic' => [['topic' => null], 'topic'],
        'too short after trimming' => [['message' => "   short \n\n  "], 'message'],
        '9 code points' => [['message' => str_repeat('é', 9)], 'message'],
        '5001 code points' => [['message' => str_repeat('🦊', 5001)], 'message'],
        'not a string' => [['message' => ['x']], 'message'],
        'client_id not a uuid' => [['client_id' => 'abc'], 'client_id'],
        'missing client_id' => [['client_id' => null], 'client_id'],
        'app_version too long' => [['app_version' => str_repeat('1', 21)], 'app_version'],
        'unknown platform' => [['platform' => 'windows'], 'platform'],
        'device too long' => [['device' => str_repeat('d', 81)], 'device'],
    ]);

    it('counts the length in code points: 10 and 5000 emoji are fine', function () {
        supportAs(familyOwner());

        supportPost(['message' => str_repeat('🦊', 10)])->assertCreated();
        supportPost(['message' => str_repeat('🦊', 5000)])->assertCreated();
        supportPost(['app_version' => null, 'platform' => null, 'device' => null])->assertCreated();
    });

    it('is idempotent by client_id: the same id returns the existing message with 200', function () {
        $owner = familyOwner();
        supportAs($owner);
        $clientId = (string) Str::uuid();

        $first = supportPost(['client_id' => $clientId])->assertCreated()->json('data');
        $again = supportPost(['client_id' => strtoupper($clientId), 'message' => 'A different text this time.'])->assertOk()->json('data');

        expect($again)->toBe($first)
            ->and(SupportMessage::query()->count())->toBe(1);
        Mail::assertQueuedCount(1);

        // Another admin may use the same client_id (unique per user).
        supportAs(familyOwner());
        supportPost(['client_id' => $clientId])->assertCreated();
    });

    it('allows 5 messages per hour and 20 per day per user (429 rate_limited), retries not counted', function () {
        $owner = familyOwner();
        supportAs($owner);

        foreach (range(1, 5) as $i) {
            $clientId = (string) Str::uuid();
            supportPost(['client_id' => $clientId])->assertCreated();
        }

        // A retry of an existing message still answers 200.
        supportPost(['client_id' => $clientId])->assertOk();

        $limited = supportPost()->assertStatus(429)->assertJsonPath('code', 'rate_limited');
        expect((int) $limited->headers->get('Retry-After'))->toBeGreaterThan(0)
            ->and($limited->json('retry_after'))->toBeGreaterThan(0);

        // Another admin is not affected.
        supportAs(familyOwner());
        supportPost()->assertCreated();

        // After the hour, until 20 in the day.
        supportAs($owner);
        foreach (range(1, 3) as $hour) {
            $this->travel(61)->minutes();
            foreach (range(1, 5) as $i) {
                supportPost()->assertCreated();
            }
        }

        $this->travel(61)->minutes();
        supportPost()->assertStatus(429)->assertJsonPath('code', 'rate_limited');
        expect(SupportMessage::query()->where('user_id', $owner->user_id)->count())->toBe(20);
    });

    it('refuses an admin without an account email (replies go there)', function () {
        $owner = familyOwner();
        $owner->user->forceFill(['email' => null])->save();
        supportAs($owner);

        supportPost()->assertForbidden()->assertJsonPath('message', 'Add an email address to your account so support can answer you.');
    });
});

describe('GET /support/messages', function () {
    it('lists only the caller\'s messages, newest first, at most 50, with the API status', function () {
        $owner = familyOwner();
        $coAdmin = addMember($owner->family, 38, FamilyMember::ROLE_ADMIN);
        $owner->load('user');

        SupportMessage::factory()->count(52)->fromMember($owner)->create();
        $answered = SupportMessage::factory()->fromMember($owner)->status(SupportMessage::STATUS_ANSWERED)->create();
        SupportMessage::factory()->fromMember($coAdmin->load('user'))->create();
        SupportMessage::factory()->create(['email' => $owner->user->email]);

        supportAs($owner);
        $data = $this->getJson(SUPPORT_URL)->assertOk()->assertJsonCount(50, 'data')->json('data');

        expect($data[0]['id'])->toBe($answered->id)
            ->and($data[0]['status'])->toBe('answered')
            ->and($data[0]['answered_at'])->not->toBeNull()
            ->and($data[1]['status'])->toBe('open')
            ->and(collect($data)->pluck('id')->every(fn (int $id) => SupportMessage::query()->find($id)?->user_id === $owner->user_id))->toBeTrue();
    });

    it('runs a fixed number of queries', function () {
        $owner = familyOwner();
        $owner->load('user');
        supportAs($owner);

        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson(SUPPORT_URL)->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        SupportMessage::factory()->count(2)->fromMember($owner)->create();
        $count(); // warm-up (first request of the test touches the device / token)
        $small = $count();
        SupportMessage::factory()->count(10)->fromMember($owner)->create();

        expect($count())->toBe($small);
    });
});

describe('privacy', function () {
    it('adds the user\'s own support messages and replies to /me/export', function () {
        $owner = familyOwner();
        $owner->load('user');
        $message = SupportMessage::factory()->fromMember($owner)->status(SupportMessage::STATUS_ANSWERED)->create(['message' => 'Where is my plan?']);
        SupportMessageReply::query()->create(['support_message_id' => $message->id, 'staff_user_id' => User::factory()->staff()->create()->id, 'body' => 'It is back.', 'sent_at' => now()]);
        SupportMessage::factory()->create(['email' => $owner->user->email, 'message' => 'A website message with my email']);
        SupportMessage::factory()->fromMember(familyOwner()->load('user'))->create();

        supportAs($owner);
        $export = $this->getJson('/api/v1/me/export')->assertOk()->json('data.support_messages');

        expect($export)->toHaveCount(1)
            ->and($export[0])->toMatchArray(['id' => $message->id, 'topic' => $message->topic, 'message' => 'Where is my plan?', 'status' => 'answered'])
            ->and($export[0]['replies'])->toBe([['body' => 'It is back.', 'sent_at' => $export[0]['replies'][0]['sent_at']]])
            ->and($export[0]['replies'][0])->not->toHaveKey('staff_user_id');
    });

    it('deletes the user\'s app messages and their replies with the account, not website messages', function () {
        $owner = familyOwner();
        $owner->load('user');
        $mine = SupportMessage::factory()->fromMember($owner)->create();
        SupportMessageReply::query()->create(['support_message_id' => $mine->id, 'body' => 'Reply', 'sent_at' => now()]);
        $web = SupportMessage::factory()->create(['email' => $owner->user->email]);
        $other = SupportMessage::factory()->fromMember(familyOwner()->load('user'))->create();

        app(EraseUser::class)->handle($owner->user);

        expect(SupportMessage::query()->pluck('id')->sort()->values()->all())->toBe([$web->id, $other->id])
            ->and(SupportMessageReply::query()->count())->toBe(0);
    });

    it('deletes closed messages a year after closing and any message two years after the last activity', function () {
        $closedOld = SupportMessage::factory()->status(SupportMessage::STATUS_CLOSED)->create(['closed_at' => now()->subDays(366)]);
        $closedRecent = SupportMessage::factory()->status(SupportMessage::STATUS_CLOSED)->create(['closed_at' => now()->subDays(300)]);
        $staleOpen = SupportMessage::factory()->status(SupportMessage::STATUS_OPEN)->create(['last_activity_at' => now()->subDays(731)]);
        $activeOld = SupportMessage::factory()->status(SupportMessage::STATUS_ANSWERED)->create(['created_at' => now()->subDays(800), 'last_activity_at' => now()->subDays(10)]);
        SupportMessageReply::query()->create(['support_message_id' => $closedOld->id, 'body' => 'Reply', 'sent_at' => now()]);

        expect(app(PruneSupportMessages::class)->handle())->toBe(2)
            ->and(SupportMessage::query()->pluck('id')->sort()->values()->all())->toBe([$closedRecent->id, $activeOld->id])
            ->and(SupportMessageReply::query()->count())->toBe(0);

        $this->artisan('support:prune')->assertSuccessful()->expectsOutput('Deleted 0 support message(s).');
    });

    it('schedules the pruning daily', function () {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => Str::contains((string) $event->command, 'support:prune'));

        expect($event)->not->toBeNull()->and($event->expression)->toBe('40 3 * * *');
    });
});

describe('mails', function () {
    it('renders the inbox mail and the reply in the shared frame, escaping everything people typed', function (string $locale) {
        config(['app.website_url' => 'https://brainyclan.com']);
        $owner = familyOwner();
        $owner->user->forceFill(['locale' => $locale])->save();
        $evil = "# Heading?\n\n<script>alert(1)</script> **bold** [link](https://evil.example) <img src=x onerror=alert(1)>\n\n    indented code";
        $message = SupportMessage::factory()->fromMember($owner->load('user'))->create(['message' => $evil, 'name' => 'Mom <b>X</b>', 'locale' => $locale]);
        $reply = SupportMessageReply::query()->create(['support_message_id' => $message->id, 'body' => "Thanks! *Fixed* <i>now</i>\n\nBye", 'sent_at' => now()]);

        $inbox = (string) (new SupportMessageReceived($message))->render();
        $replyMail = new SupportReplyMail($reply);
        $replyHtml = (string) $replyMail->render();

        foreach ([$inbox, $replyHtml] as $html) {
            expect($html)->toContain('class="header-name"')
                ->toContain('mailto:'.config('legal.support_email'))
                ->toContain('&lt;script&gt;alert(1)&lt;/script&gt; **bold** [link](https://evil.example) &lt;img src=x onerror=alert(1)&gt;')
                ->toContain('# Heading?<br><br>')
                ->not->toContain('<script')
                ->not->toContain('<strong>bold</strong>')
                ->not->toContain('href="https://evil.example"')
                ->not->toContain('<pre>')
                ->not->toContain('<h1>Heading?');
        }

        expect($inbox)->toContain('<html lang="en"')
            ->toContain('Mom &lt;b&gt;X&lt;/b&gt;')
            ->toContain('/admin/support-messages/'.$message->id)
            ->and($replyHtml)->toContain('<html lang="'.$locale.'"')
            ->toContain('Thanks! *Fixed* &lt;i&gt;now&lt;/i&gt;<br><br>Bye')
            ->toContain($locale === 'fr' ? 'Réponse de l’assistance Brainy Clan' : 'A reply from Brainy Clan support');

        $replyMail->assertSeeInText("Thanks! *Fixed* <i>now</i>\n\nBye")
            ->assertSeeInText('<script>alert(1)</script>')
            ->assertDontSeeInText('<div')
            ->assertDontSeeInText('<br>')
            ->assertHasReplyTo(config('legal.support_email'))
            ->assertHasSubject($locale === 'fr' ? 'Re : votre message à l’assistance Brainy Clan (no '.$message->id.')' : 'Re: your message to Brainy Clan support (#'.$message->id.')');

        (new SupportMessageReceived($message))
            ->assertHasReplyTo($owner->user->email)
            ->assertHasSubject('Support message #'.$message->id.': '.SupportMessage::topicLabel($message->topic).' (app)')
            ->assertSeeInText('From: Mom <b>X</b> <'.$owner->user->email.'>')
            ->assertDontSeeInText('<table');
    })->with(['en', 'fr']);

    it('keeps only ids in the queued job payload', function () {
        $message = SupportMessage::factory()->create(['message' => 'A very private support text']);
        $payload = serialize(new SupportMessageReceived($message));

        expect($payload)->not->toContain('A very private support text');
    });
});
