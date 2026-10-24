<?php

use App\Modules\Chat\Actions\PruneChatMessages;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Support\ChatText;
use App\Modules\Core\Support\SentryScrubber;
use App\Modules\Family\Actions\DeleteFamily;
use App\Modules\Family\Actions\RemoveMember;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Actions\EraseUser;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Jobs\DeliverPushNotification;
use App\Modules\Notifications\Listeners\NotifyFamilyChat;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Policy\Models\Setting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Sentry\Event;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * Family chat (docs/API.md "Family chat", 2026-10-23): only the family's active members see it,
 * plain text end to end (XSS payloads are stored and returned verbatim, never transformed), emoji
 * round-trip, idempotent posts, tombstones, read markers, retention per tier, erasure, pushes
 * without text, no N+1.
 */
const CHAT_URL = '/api/v1/family/chat/messages';

/**
 * @return array{0: FamilyMember, 1: FamilyMember} owner (admin) and a teen member of one family
 */
function chatFamily(string $tier = Family::TIER_FREE): array
{
    $owner = familyOwner(['tier' => $tier]);

    return [$owner, addMember($owner->family, 15)];
}

function chatAs(FamilyMember $member): void
{
    app('auth')->forgetGuards();
    actingAsMember($member);
}

/**
 * Posts as the current user and returns the response.
 */
function chatPost(string $body, ?string $clientId = null): TestResponse
{
    return test()->postJson(CHAT_URL, ['body' => $body, 'client_id' => $clientId ?? (string) Str::uuid()]);
}

// ---------------------------------------------------------------------------------------------
// Posting, listing, plain text
// ---------------------------------------------------------------------------------------------

it('posts a message and lists it for the other family members', function () {
    [$owner, $teen] = chatFamily();
    chatAs($teen);

    $clientId = (string) Str::uuid();
    $response = chatPost('We won! 🎉', $clientId)->assertCreated();

    $response->assertJsonPath('data.body', 'We won! 🎉')
        ->assertJsonPath('data.member_id', $teen->id)
        ->assertJsonPath('data.nickname', $teen->nickname)
        ->assertJsonPath('data.avatar', $teen->user->avatar)
        ->assertJsonPath('data.deleted', false)
        ->assertJsonPath('data.client_id', $clientId)
        ->assertJsonStructure(['data' => ['id', 'member_id', 'nickname', 'avatar', 'body', 'deleted', 'client_id', 'created_at']]);

    chatAs($owner);
    $this->getJson(CHAT_URL)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', 'We won! 🎉')
        ->assertJsonPath('meta.has_more', false)
        ->assertJsonPath('meta.retention_days', 7)
        ->assertJsonPath('meta.last_read_id', null)
        ->assertJsonPath('meta.unread', 1);
});

it('stores the body encrypted at rest, never as plain text', function () {
    [, $teen] = chatFamily();
    chatAs($teen);
    chatPost('secret family plans 🍕')->assertCreated();

    $raw = (string) DB::table('chat_messages')->value('body');

    expect($raw)->not->toContain('secret')->not->toContain('🍕')
        ->and(ChatMessage::query()->sole()->body)->toBe('secret family plans 🍕');
});

it('returns XSS payloads verbatim as plain JSON text, never transformed or as HTML', function (string $payload) {
    [$owner, $teen] = chatFamily();
    chatAs($teen);

    $posted = chatPost($payload)->assertCreated();
    expect($posted->json('data.body'))->toBe($payload)
        ->and($posted->headers->get('Content-Type'))->toStartWith('application/json');

    chatAs($owner);
    $listed = $this->get(CHAT_URL, ['Accept' => 'text/html'])->assertOk();

    expect($listed->headers->get('Content-Type'))->toStartWith('application/json')
        ->and(json_decode((string) $listed->getContent(), true)['data'][0]['body'])->toBe($payload);
})->with([
    'script tag' => '<script>alert(1)</script>',
    'img onerror' => '<img src=x onerror=alert(document.cookie)>',
    'javascript url' => 'javascript:alert(1)',
    'svg onload' => '"><svg onload=alert(1)>',
    'html entities stay entities' => '&lt;b&gt;bold&lt;/b&gt; &amp; &#60;i&#62;',
    'template syntax' => '{{ $secret }} {!! $x !!} ${alert(1)} <?php echo 1; ?>',
    'markdown link' => '[click](javascript:alert(1)) **bold**',
    'iframe' => '<iframe src="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=="></iframe>',
]);

it('keeps HTML out of error responses on chat routes too', function () {
    [, $teen] = chatFamily();
    chatAs($teen);

    $response = $this->post(CHAT_URL, ['body' => '', 'client_id' => 'nope'], ['Accept' => 'text/html'])->assertStatus(422);

    expect($response->headers->get('Content-Type'))->toStartWith('application/json')
        ->and($response->json('code'))->toBe('validation_failed');
});

it('round-trips emoji: ZWJ sequences, flags, skin tones and tag sequences', function (string $emoji) {
    [, $teen] = chatFamily();
    chatAs($teen);

    chatPost($emoji)->assertCreated()->assertJsonPath('data.body', $emoji);
    $this->getJson(CHAT_URL)->assertJsonPath('data.0.body', $emoji);
})->with([
    'family ZWJ' => '👨‍👩‍👧‍👦',
    'rainbow flag ZWJ' => '🏳️‍🌈',
    'country flags' => '🇨🇦🇫🇷',
    'skin tones' => '👍🏽👋🏿🧑🏻‍🚀',
    'england tag sequence' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
    'keycap' => '1️⃣#️⃣',
    'mixed text' => "Bravo Léa ! ❤️‍🔥\nÀ demain 😘",
]);

it('accepts 1000 characters counted as code points and rejects 1001', function () {
    [, $teen] = chatFamily();
    chatAs($teen);

    chatPost(str_repeat('😀', 1000))->assertCreated();
    chatPost(str_repeat('😀', 1001))->assertStatus(422)->assertJsonValidationErrors('body');
    chatPost(str_repeat('a', 1000))->assertCreated();
});

it('normalizes the body: NFC, control characters, blank lines, trimming', function () {
    [, $teen] = chatFamily();
    chatAs($teen);

    // "e" + combining acute → "é" (NFC); NUL, tab, ESC and C1 controls removed; CRLF → LF.
    chatPost("Cafe\u{0301}\u{0000}\t\u{001B}[31m\u{0085}ok\r\nline2\n\n\n\n\n\nend")
        ->assertCreated()
        ->assertJsonPath('data.body', "Café[31mok\nline2\n\n\nend");

    expect(ChatText::normalize("  \n hi 👋 \n  "))->toBe('hi 👋')
        ->and(ChatText::normalize("a\n\n\nb"))->toBe("a\n\n\nb")
        ->and(ChatText::normalize("a\n \n\t\n \n\nb"))->toBe("a\n\n\nb")
        ->and(ChatText::normalize("\xC3\x28"))->toBeNull();
});

it('rejects bodies that are empty after normalization and bad client ids', function (array $payload) {
    [, $teen] = chatFamily();
    chatAs($teen);

    $this->postJson(CHAT_URL, $payload)->assertStatus(422)->assertJsonPath('code', 'validation_failed');
    expect(ChatMessage::query()->count())->toBe(0);
})->with([
    'empty' => [['body' => '', 'client_id' => '6f1c3c1e-8a7b-4d2e-9f00-1234567890ab']],
    'only whitespace and controls' => [['body' => " \n\u{0000}\u{0007}\n\t ", 'client_id' => '6f1c3c1e-8a7b-4d2e-9f00-1234567890ab']],
    'array body' => [['body' => ['<b>'], 'client_id' => '6f1c3c1e-8a7b-4d2e-9f00-1234567890ab']],
    'missing client id' => [['body' => 'hi']],
    'non-uuid client id' => [['body' => 'hi', 'client_id' => 'abc']],
]);

it('is idempotent per client_id: a retry returns the existing message with 200', function () {
    [, $teen] = chatFamily();
    chatAs($teen);
    $clientId = (string) Str::uuid();

    $first = chatPost('hello', $clientId)->assertCreated();
    $retry = chatPost('hello', $clientId)->assertOk();

    expect($retry->json('data.id'))->toBe($first->json('data.id'))
        ->and(ChatMessage::query()->count())->toBe(1);
});

it('pages by id cursors: newest first, before, after (oldest first) and has_more', function () {
    [$owner, $teen] = chatFamily();
    $ids = collect(range(1, 5))->map(fn (int $i) => ChatMessage::factory()->by($teen)->create(['body' => "m{$i}"])->id)->all();
    chatAs($owner);

    $this->getJson(CHAT_URL.'?limit=2')->assertJsonPath('data.0.body', 'm5')->assertJsonPath('data.1.body', 'm4')->assertJsonPath('meta.has_more', true);
    $this->getJson(CHAT_URL."?limit=2&before={$ids[3]}")->assertJsonPath('data.0.body', 'm3')->assertJsonPath('data.1.body', 'm2')->assertJsonPath('meta.has_more', true);
    $this->getJson(CHAT_URL."?limit=2&before={$ids[1]}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.body', 'm1')->assertJsonPath('meta.has_more', false);
    $this->getJson(CHAT_URL."?after={$ids[2]}")->assertJsonCount(2, 'data')->assertJsonPath('data.0.body', 'm4')->assertJsonPath('data.1.body', 'm5')->assertJsonPath('meta.has_more', false);
    $this->getJson(CHAT_URL.'?limit=101')->assertStatus(422);
    $this->getJson(CHAT_URL.'?before=3&after=1')->assertStatus(422);
});

// ---------------------------------------------------------------------------------------------
// Access: only the family's active members
// ---------------------------------------------------------------------------------------------

it('requires authentication on every chat route', function (string $method, string $url) {
    $this->json($method, $url)->assertStatus(401)->assertJsonPath('code', 'unauthenticated');
})->with([
    ['GET', CHAT_URL],
    ['POST', CHAT_URL],
    ['DELETE', CHAT_URL.'/1'],
    ['POST', '/api/v1/family/chat/read'],
    ['GET', '/api/v1/family/chat/unread'],
]);

it('answers 404 to users without an active membership, staff included', function (string $who) {
    [, $teen] = chatFamily();
    $message = ChatMessage::factory()->by($teen)->create();

    $user = match ($who) {
        'staff' => User::factory()->staff()->create(),
        'no family' => User::factory()->aged(35)->create(),
        'removed member' => (function () use ($teen) {
            $adult = addMember($teen->family, 30);
            $adult->forceFill(['status' => FamilyMember::STATUS_REMOVED])->save();

            return $adult->user;
        })(),
    };
    Sanctum::actingAs($user);

    $this->getJson(CHAT_URL)->assertNotFound();
    chatPost('hi')->assertNotFound();
    $this->deleteJson(CHAT_URL."/{$message->id}")->assertNotFound();
    $this->postJson('/api/v1/family/chat/read', ['last_read_id' => $message->id])->assertNotFound();
    $this->getJson('/api/v1/family/chat/unread')->assertNotFound();

    expect(ChatMessage::query()->sole()->isDeleted())->toBeFalse();
})->with(['staff', 'no family', 'removed member']);

it('never shows, deletes or counts another family\'s messages', function () {
    [$owner, $teen] = chatFamily();
    [$otherOwner, $otherTeen] = chatFamily();
    $foreign = ChatMessage::factory()->by($otherTeen)->create(['body' => 'other family secret']);
    ChatMessage::factory()->by($teen)->create(['body' => 'ours']);

    chatAs($owner);
    $list = $this->getJson(CHAT_URL)->assertOk()->assertJsonCount(1, 'data');
    expect((string) $list->getContent())->not->toContain('other family secret');
    $this->getJson(CHAT_URL.'?after=0')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/family/chat/unread')->assertJsonPath('data.unread', 1);

    // An admin of family A can not delete family B's message: 404, untouched.
    $this->deleteJson(CHAT_URL."/{$foreign->id}")->assertNotFound()->assertJsonPath('code', 'not_found');
    expect($foreign->fresh()?->body)->toBe('other family secret');

    // Reusing another family's client_id creates a new message in our family (no leak, no 200).
    chatPost('mine', $foreign->client_id)->assertCreated()->assertJsonPath('data.body', 'mine');

    // A read marker can not jump past our own newest message.
    $this->postJson('/api/v1/family/chat/read', ['last_read_id' => $foreign->id + 1000])->assertNoContent();
    expect($owner->fresh()?->chat_last_read_id)->toBe((int) ChatMessage::query()->where('family_id', $owner->family_id)->max('id'));

    chatAs($otherOwner);
    $this->getJson(CHAT_URL)->assertJsonCount(1, 'data')->assertJsonPath('data.0.body', 'other family secret');
});

it('answers 403 chat_disabled when an admin turned chat off, and the admin can turn it back on', function () {
    [$owner, $teen] = chatFamily();
    chatAs($owner);
    $this->patchJson("/api/v1/family/members/{$teen->id}", ['chat_enabled' => false])->assertOk()->assertJsonPath('data.chat_enabled', false);

    chatAs($teen);
    $this->getJson(CHAT_URL)->assertForbidden()->assertJsonPath('code', 'chat_disabled');
    chatPost('hi')->assertForbidden()->assertJsonPath('code', 'chat_disabled');
    $this->getJson('/api/v1/family/chat/unread')->assertForbidden()->assertJsonPath('code', 'chat_disabled');
    $this->postJson('/api/v1/family/chat/read', ['last_read_id' => 1])->assertForbidden();

    // Only admins may change it.
    $this->patchJson("/api/v1/family/members/{$teen->id}", ['chat_enabled' => true])->assertForbidden();

    chatAs($owner);
    $this->patchJson("/api/v1/family/members/{$teen->id}", ['chat_enabled' => true])->assertOk()->assertJsonPath('data.chat_enabled', true);
    chatAs($teen->fresh() ?? $teen); // a new user instance, like a new request
    $this->getJson(CHAT_URL)->assertOk();
});

it('lets the author or a family admin delete a message (tombstone), but not other members', function () {
    [$owner, $teen] = chatFamily(Family::TIER_FAMILY);
    $sibling = addMember($owner->family, 16);
    $teenMessage = ChatMessage::factory()->by($teen)->create(['body' => 'from teen']);
    $ownerMessage = ChatMessage::factory()->by($owner)->create(['body' => 'from owner']);

    chatAs($sibling);
    $this->deleteJson(CHAT_URL."/{$teenMessage->id}")->assertForbidden()->assertJsonPath('code', 'forbidden');

    chatAs($teen);
    $this->deleteJson(CHAT_URL."/{$ownerMessage->id}")->assertForbidden();
    $this->deleteJson(CHAT_URL."/{$teenMessage->id}")->assertNoContent();
    $this->deleteJson(CHAT_URL."/{$teenMessage->id}")->assertNoContent();

    chatAs($owner);
    $this->deleteJson(CHAT_URL.'/'.($ownerMessage->id + 999))->assertNotFound();

    $response = $this->getJson(CHAT_URL)->assertOk();
    $tombstone = collect($response->json('data'))->firstWhere('id', $teenMessage->id);
    expect($tombstone)->toMatchArray(['deleted' => true, 'body' => null])
        ->and(DB::table('chat_messages')->where('id', $teenMessage->id)->value('body'))->toBeNull();

    // An admin deletes anyone's message in their family.
    $siblingMessage = ChatMessage::factory()->by($sibling)->create();
    $this->deleteJson(CHAT_URL."/{$siblingMessage->id}")->assertNoContent();
    expect($siblingMessage->fresh()?->isDeleted())->toBeTrue();
});

it('moves the read marker only forward and counts unread messages from others', function () {
    [$owner, $teen] = chatFamily();
    $first = ChatMessage::factory()->by($teen)->create();
    $second = ChatMessage::factory()->by($teen)->create();
    ChatMessage::factory()->by($owner)->create();
    ChatMessage::factory()->by($teen)->deleted()->create();

    chatAs($owner);
    $this->getJson('/api/v1/family/chat/unread')->assertOk()->assertExactJson(['data' => ['unread' => 2]]);

    $this->postJson('/api/v1/family/chat/read', ['last_read_id' => $second->id])->assertNoContent();
    $this->postJson('/api/v1/family/chat/read', ['last_read_id' => $first->id])->assertNoContent();
    $this->postJson('/api/v1/family/chat/read', ['last_read_id' => 'x'])->assertStatus(422);

    expect($owner->fresh()?->chat_last_read_id)->toBe($second->id);
    $this->getJson('/api/v1/family/chat/unread')->assertJsonPath('data.unread', 0);
    $this->getJson(CHAT_URL)->assertJsonPath('meta.last_read_id', $second->id)->assertJsonPath('meta.unread', 0);
});

it('rate-limits posting to 20 messages per minute per member', function () {
    [, $teen] = chatFamily();
    chatAs($teen);

    for ($i = 0; $i < 20; $i++) {
        chatPost("message {$i}")->assertCreated();
    }

    chatPost('one too many')->assertStatus(429)->assertJsonPath('code', 'rate_limited');
    expect(ChatMessage::query()->count())->toBe(20);
});

// ---------------------------------------------------------------------------------------------
// Members leaving, erasure, family deletion, export
// ---------------------------------------------------------------------------------------------

it('shows a removed member\'s messages as Former member until retention', function () {
    [$owner] = chatFamily(Family::TIER_FAMILY);
    $adult = addMember($owner->family, 35);
    ChatMessage::factory()->by($adult)->create(['body' => 'bye all']);

    app(RemoveMember::class)->handle($adult);

    chatAs($owner);
    $this->getJson(CHAT_URL)
        ->assertJsonPath('data.0.member_id', null)
        ->assertJsonPath('data.0.nickname', 'Former member')
        ->assertJsonPath('data.0.avatar', null)
        ->assertJsonPath('data.0.body', 'bye all');

    $owner->user->forceFill(['locale' => 'fr'])->save();
    chatAs($owner->fresh() ?? $owner);
    $this->getJson(CHAT_URL)->assertJsonPath('data.0.nickname', 'Ancien membre');
});

it('erases a deleted account\'s messages in every family and a removed child\'s too', function () {
    [$owner, $teen] = chatFamily();
    $child = addMember($owner->family, 9);
    $adult = addMember($owner->family, 35);
    ChatMessage::factory()->by($adult)->count(2)->create();
    ChatMessage::factory()->by($child)->create();
    $kept = ChatMessage::factory()->by($teen)->create();

    app(EraseUser::class)->handle($adult->user);
    app(RemoveMember::class)->handle($child);

    expect(ChatMessage::query()->pluck('id')->all())->toBe([$kept->id]);
});

it('deletes the whole chat when the family is deleted', function () {
    [$owner, $teen] = chatFamily();
    [, $otherTeen] = chatFamily();
    ChatMessage::factory()->by($teen)->count(3)->create();
    $other = ChatMessage::factory()->by($otherTeen)->create();

    app(DeleteFamily::class)->handle($owner->family, false);

    expect(ChatMessage::query()->pluck('id')->all())->toBe([$other->id]);
});

it('includes the user\'s own existing messages, decrypted, in the data export', function () {
    [$owner, $teen] = chatFamily();
    $mine = ChatMessage::factory()->by($owner)->create(['body' => 'my words 🙂']);
    ChatMessage::factory()->by($owner)->deleted()->create();
    ChatMessage::factory()->by($teen)->create(['body' => 'not mine']);

    chatAs($owner);
    $export = $this->getJson('/api/v1/me/export')->assertOk();

    expect($export->json('data.chat_messages'))->toHaveCount(1)
        ->and($export->json('data.chat_messages.0'))->toMatchArray(['id' => $mine->id, 'body' => 'my words 🙂', 'member_id' => $owner->id])
        ->and((string) $export->getContent())->not->toContain('not mine');
});

// ---------------------------------------------------------------------------------------------
// Retention
// ---------------------------------------------------------------------------------------------

it('exposes chat retention in the policy: free 7 days, Family plan 365', function () {
    [$owner] = chatFamily();
    chatAs($owner);
    $this->getJson('/api/v1/me')->assertJsonPath('data.policy.chat.retention_days', 7)->assertJsonPath('data.member.chat_enabled', true);

    $owner->family->forceFill(['tier' => Family::TIER_FAMILY])->save();
    $this->getJson('/api/v1/me')->assertJsonPath('data.policy.chat.retention_days', 365);
});

it('prunes messages past each tier\'s retention and hides them before the job runs', function () {
    [$freeOwner, $freeTeen] = chatFamily(Family::TIER_FREE);
    [$paidOwner, $paidTeen] = chatFamily(Family::TIER_FAMILY);

    $freeOld = ChatMessage::factory()->by($freeTeen)->create(['created_at' => now()->subDays(8)]);
    $freeNew = ChatMessage::factory()->by($freeTeen)->create(['created_at' => now()->subDays(6)]);
    $paidMid = ChatMessage::factory()->by($paidTeen)->create(['created_at' => now()->subDays(200)]);
    $paidOld = ChatMessage::factory()->by($paidTeen)->create(['created_at' => now()->subDays(366)]);

    // Hidden from the API even before the hourly job deletes them.
    chatAs($freeOwner);
    $this->getJson(CHAT_URL)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $freeNew->id)->assertJsonPath('meta.unread', 1);
    chatAs($paidOwner);
    $this->getJson(CHAT_URL)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $paidMid->id)->assertJsonPath('meta.retention_days', 365);

    expect(app(PruneChatMessages::class)->handle())->toBe(2)
        ->and(ChatMessage::query()->orderBy('id')->pluck('id')->all())->toBe([$freeNew->id, $paidMid->id]);

    // A week later the free message is gone too.
    $this->travel(2)->days();
    $this->artisan('chat:prune')->assertSuccessful();
    expect(ChatMessage::query()->pluck('id')->all())->toBe([$paidMid->id])
        ->and($freeOld->fresh())->toBeNull()
        ->and($paidOld->fresh())->toBeNull();
});

it('applies the shorter retention after a downgrade on the next run', function () {
    [$owner, $teen] = chatFamily(Family::TIER_FAMILY);
    $old = ChatMessage::factory()->by($teen)->create(['created_at' => now()->subDays(30)]);
    $recent = ChatMessage::factory()->by($teen)->create(['created_at' => now()->subDays(2)]);

    app(PruneChatMessages::class)->handle();
    expect($old->fresh())->not->toBeNull();

    $owner->family->forceFill(['tier' => Family::TIER_FREE])->save();
    app(PruneChatMessages::class)->handle();

    expect(ChatMessage::query()->pluck('id')->all())->toBe([$recent->id]);
});

it('reads retention from the tier settings an admin can edit', function () {
    [, $teen] = chatFamily(Family::TIER_FREE);
    $message = ChatMessage::factory()->by($teen)->create(['created_at' => now()->subDays(3)]);

    Setting::query()->where('key', 'chat.retention_days')->where('scope_id', 'free')->update(['value' => json_encode(2)]);
    app(PruneChatMessages::class)->handle();

    expect($message->fresh())->toBeNull();
});

it('schedules the retention job hourly', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => Str::contains((string) $event->command, 'chat:prune'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 * * * *');
});

// ---------------------------------------------------------------------------------------------
// Push: family_chat, never the text
// ---------------------------------------------------------------------------------------------

it('pushes family_chat to the other members with chat and the preference on, without the text', function () {
    Bus::fake([DeliverPushNotification::class]);
    $this->travelTo(now()->setTimezone('America/Toronto')->setTime(12, 0));

    [$owner, $teen] = chatFamily(Family::TIER_FAMILY);
    $muted = addMember($owner->family, 30);
    $chatOff = addMember($owner->family, 31);
    $chatOff->forceFill(['chat_enabled' => false])->save();
    $french = addMember($owner->family, 40);
    $french->user->forceFill(['locale' => 'fr'])->save();
    NotificationPreference::query()->create(['family_member_id' => $muted->id, 'types' => ['family_chat' => false], 'quiet_from' => null, 'quiet_to' => null, 'reminder_time' => '17:00']);
    foreach ([$owner, $teen, $muted, $chatOff, $french] as $member) {
        Device::factory()->create(['user_id' => $member->user_id, 'push_token' => "ExponentPushToken[c{$member->id}]"]);
    }

    chatAs($teen);
    $id = chatPost('the secret code is 1234 <b>')->assertCreated()->json('data.id');

    $jobs = Bus::dispatched(DeliverPushNotification::class);
    $recipients = $jobs->flatMap(fn (DeliverPushNotification $job) => $job->memberIds)->sort()->values()->all();

    expect($recipients)->toBe(collect([$owner->id, $french->id])->sort()->values()->all());

    foreach ($jobs as $job) {
        expect($job->title)->toBe('Brainy Clan')
            ->and($job->data)->toBe(['type' => 'family_chat', 'message_id' => $id])
            ->and(json_encode([$job->title, $job->body, $job->data]))->not->toContain('secret')->not->toContain('1234');
    }

    $bodies = $jobs->mapWithKeys(fn (DeliverPushNotification $job) => [$job->memberIds[0] => $job->body]);
    expect($bodies[$owner->id])->toBe("{$teen->nickname} sent a message")
        ->and($bodies[$french->id])->toBe("{$teen->nickname} a envoyé un message");
});

it('throttles family_chat pushes to one per recipient per 2 minutes', function () {
    Bus::fake([DeliverPushNotification::class]);
    $this->travelTo(now()->setTimezone('America/Toronto')->setTime(12, 0));
    [$owner, $teen] = chatFamily();
    chatAs($teen);

    chatPost('one')->assertCreated();
    chatPost('two')->assertCreated();
    Bus::assertDispatchedTimes(DeliverPushNotification::class, 1);

    Cache::forget(NotifyFamilyChat::throttleKey($owner->id));
    chatPost('three')->assertCreated();
    Bus::assertDispatchedTimes(DeliverPushNotification::class, 2);
});

it('offers family_chat as a notification preference, on by default', function () {
    [$owner] = chatFamily();
    chatAs($owner);

    $this->getJson('/api/v1/me/notification-preferences')->assertJsonPath('data.types.family_chat', true);
    $this->patchJson('/api/v1/me/notification-preferences', ['types' => ['family_chat' => false]])->assertOk()->assertJsonPath('data.types.family_chat', false);
});

// ---------------------------------------------------------------------------------------------
// Privacy plumbing and N+1
// ---------------------------------------------------------------------------------------------

it('keeps message text out of Sentry events', function () {
    $event = Event::createEvent();
    $event->setRequest(['url' => 'https://api.brainyclan.com/api/v1/family/chat/messages', 'method' => 'POST', 'data' => ['body' => 'private words', 'client_id' => 'x']]);
    $event->setExtra(['payload' => ['body' => 'private words']]);
    $event->setContext('job', ['data' => ['body' => 'private words']]);

    $scrubbed = SentryScrubber::beforeSend($event);

    expect(json_encode([$scrubbed->getRequest(), $scrubbed->getExtra(), $scrubbed->getContexts()]))->not->toContain('private words');
});

it('never puts the model in the activity log or in serialized output', function () {
    [, $teen] = chatFamily();
    chatAs($teen);
    chatPost('do not log me')->assertCreated();

    expect(DB::table('activity_log')->where('subject_type', (new ChatMessage)->getMorphClass())->count())->toBe(0)
        ->and(json_encode(ChatMessage::query()->sole()->toArray()))->not->toContain('do not log me')
        ->and(class_uses_recursive(ChatMessage::class))->not->toHaveKey(LogsActivity::class);
});

it('lists 50 messages with the same number of queries as 2 (no N+1)', function () {
    $count = function (int $messages): int {
        [$owner, $teen] = chatFamily();
        $authors = [$owner, $teen, addMember($owner->family, 30)];
        $removed = addMember($owner->family, 33);
        ChatMessage::factory()->by($removed)->create();
        $removed->forceFill(['status' => FamilyMember::STATUS_REMOVED])->save();

        for ($i = 0; $i < $messages; $i++) {
            ChatMessage::factory()->by($authors[$i % 3])->create();
        }

        chatAs($owner);
        $this->getJson(CHAT_URL.'?limit=100')->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson(CHAT_URL.'?limit=100')->assertOk()->assertJsonCount($messages + 1, 'data');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    expect($count(50))->toBe($count(2));
});
