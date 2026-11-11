<?php

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Models\ChatMessageReaction;
use App\Modules\Chat\Support\ChatReactions;
use App\Modules\Family\Actions\RemoveMember;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Actions\EraseUser;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Jobs\DeliverPushNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/**
 * Family chat reactions (docs/API.md "Family chat" → "Reactions", 2026-11-11): one reaction per
 * member per message from a fixed set, `reactions` on every Message, a cursor endpoint for polling
 * (adds, replacements, removals, deleted messages, members who left), no push, no unread change,
 * constant query counts.
 */
const RX_MESSAGES = '/api/v1/family/chat/messages';

const RX_CHANGES = '/api/v1/family/chat/reactions';

beforeEach(fn () => Carbon::setTestNow('2026-11-11 12:00:00'));

afterEach(fn () => Carbon::setTestNow());

/**
 * @return array{0: FamilyMember, 1: FamilyMember} owner (admin) and a teen member of one family
 */
function rxFamily(string $tier = Family::TIER_FREE): array
{
    $owner = familyOwner(['tier' => $tier]);

    return [$owner, addMember($owner->family, 15)];
}

function rxAs(FamilyMember $member): void
{
    app('auth')->forgetGuards();
    actingAsMember($member);
}

function rxReact(int $messageId, string $key): TestResponse
{
    return test()->putJson(RX_MESSAGES."/{$messageId}/reaction", ['key' => $key]);
}

function rxUnreact(int $messageId): TestResponse
{
    return test()->deleteJson(RX_MESSAGES."/{$messageId}/reaction");
}

/**
 * @return array{data: list<array{message_id: int, reactions: list<array<string, mixed>>}>, cursor: string}
 */
function rxPoll(?string $since): array
{
    $response = test()->getJson(RX_CHANGES.($since !== null ? '?since='.urlencode($since) : ''))->assertOk();

    return ['data' => $response->json('data'), 'cursor' => $response->json('meta.cursor')];
}

// ---------------------------------------------------------------------------------------------
// Setting, replacing, removing
// ---------------------------------------------------------------------------------------------

it('sets, replaces and removes the caller\'s single reaction and returns the message', function () {
    [$owner, $teen] = rxFamily();
    $adult = addMember($owner->family, 35);
    $message = ChatMessage::factory()->by($teen)->create(['body' => 'We won!']);

    rxAs($teen);
    rxReact($message->id, 'party')->assertOk()
        ->assertJsonPath('data.id', $message->id)
        ->assertJsonPath('data.body', 'We won!')
        ->assertJsonPath('data.reactions', [['key' => 'party', 'count' => 1, 'mine' => true, 'member_ids' => [$teen->id]]]);

    rxAs($adult);
    rxReact($message->id, 'heart')->assertOk();
    rxAs($owner);
    rxReact($message->id, 'heart')->assertOk()
        // Fixed key order (heart before party), member_ids in reaction order.
        ->assertJsonPath('data.reactions', [
            ['key' => 'heart', 'count' => 2, 'mine' => true, 'member_ids' => [$adult->id, $owner->id]],
            ['key' => 'party', 'count' => 1, 'mine' => false, 'member_ids' => [$teen->id]],
        ]);

    // Replacing: the teen's party becomes a heart, after the others.
    rxAs($teen);
    rxReact($message->id, 'heart')->assertOk()
        ->assertJsonPath('data.reactions', [['key' => 'heart', 'count' => 3, 'mine' => true, 'member_ids' => [$adult->id, $owner->id, $teen->id]]]);
    expect(ChatMessageReaction::query()->where('member_id', $teen->id)->count())->toBe(1);

    // The same key again changes nothing (no new row, no marker bump).
    $marker = ChatMessage::query()->whereKey($message->id)->value('reactions_changed_at');
    Carbon::setTestNow('2026-11-11 12:00:05');
    rxReact($message->id, 'heart')->assertOk()->assertJsonPath('data.reactions.0.count', 3);
    expect(ChatMessage::query()->whereKey($message->id)->value('reactions_changed_at'))->toBe($marker);

    // Removing, twice (idempotent 200).
    rxUnreact($message->id)->assertOk()
        ->assertJsonPath('data.reactions', [['key' => 'heart', 'count' => 2, 'mine' => false, 'member_ids' => [$adult->id, $owner->id]]]);
    rxUnreact($message->id)->assertOk()->assertJsonPath('data.reactions.0.count', 2);
    expect(ChatMessageReaction::query()->count())->toBe(2);
});

it('lets members react to their own message, and shows reactions everywhere a Message is returned', function () {
    [$owner, $teen] = rxFamily();
    $own = ChatMessage::factory()->by($teen)->create();

    rxAs($teen);
    rxReact($own->id, 'star')->assertOk()->assertJsonPath('data.reactions.0.mine', true);

    $expected = [['key' => 'star', 'count' => 1, 'mine' => false, 'member_ids' => [$teen->id]]];
    rxAs($owner);
    $this->getJson(RX_MESSAGES)->assertOk()->assertJsonPath('data.0.reactions', $expected);
    $this->getJson(RX_MESSAGES.'?after=0')->assertOk()->assertJsonPath('data.0.reactions', $expected);

    $clientId = (string) Str::uuid();
    $this->postJson(RX_MESSAGES, ['body' => 'nice', 'client_id' => $clientId])->assertCreated()->assertJsonPath('data.reactions', []);
    rxReact((int) ChatMessage::query()->where('client_id', $clientId)->value('id'), 'laugh')->assertOk();
    // A retried post returns the existing message with its reactions.
    $this->postJson(RX_MESSAGES, ['body' => 'nice', 'client_id' => $clientId])->assertOk()
        ->assertJsonPath('data.reactions', [['key' => 'laugh', 'count' => 1, 'mine' => true, 'member_ids' => [$owner->id]]]);
});

it('validates the key and refuses deleted messages, other families and messages past retention', function () {
    [$owner, $teen] = rxFamily();
    [, $otherTeen] = rxFamily();
    $message = ChatMessage::factory()->by($teen)->create();
    $deleted = ChatMessage::factory()->by($teen)->deleted()->create();
    $foreign = ChatMessage::factory()->by($otherTeen)->create();
    $old = ChatMessage::factory()->by($teen)->create(['created_at' => now()->subDays(8)]);

    rxAs($owner);
    rxReact($message->id, 'poop')->assertUnprocessable()->assertJsonValidationErrors('key');
    $this->putJson(RX_MESSAGES."/{$message->id}/reaction", [])->assertUnprocessable()->assertJsonValidationErrors('key');
    rxReact($deleted->id, 'heart')->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
    rxReact($foreign->id, 'heart')->assertNotFound()->assertJsonPath('code', 'not_found');
    rxUnreact($foreign->id)->assertNotFound();
    rxReact($old->id, 'heart')->assertNotFound();
    rxReact(999999, 'heart')->assertNotFound();
    // Removing on a deleted message is a no-op: it has no reactions.
    rxUnreact($deleted->id)->assertOk()->assertJsonPath('data.reactions', []);

    expect(ChatMessageReaction::query()->count())->toBe(0);
});

it('requires authentication, an active membership and chat enabled', function () {
    [$owner, $teen] = rxFamily();
    $message = ChatMessage::factory()->by($owner)->create();

    rxReact($message->id, 'heart')->assertUnauthorized();
    $this->getJson(RX_CHANGES)->assertUnauthorized();

    Sanctum::actingAs(User::factory()->aged(35)->create());
    rxReact($message->id, 'heart')->assertNotFound();
    rxUnreact($message->id)->assertNotFound();
    $this->getJson(RX_CHANGES)->assertNotFound();

    $teen->forceFill(['chat_enabled' => false])->save();
    rxAs($teen->fresh() ?? $teen);
    rxReact($message->id, 'heart')->assertForbidden()->assertJsonPath('code', 'chat_disabled');
    rxUnreact($message->id)->assertForbidden()->assertJsonPath('code', 'chat_disabled');
    $this->getJson(RX_CHANGES)->assertForbidden()->assertJsonPath('code', 'chat_disabled');

    expect(ChatMessageReaction::query()->count())->toBe(0);
});

it('sends no push and leaves unread counts alone', function () {
    Bus::fake([DeliverPushNotification::class]);
    [$owner, $teen] = rxFamily();
    $message = ChatMessage::factory()->by($teen)->create();

    rxAs($owner);
    $unread = $this->getJson('/api/v1/family/chat/unread')->json('data.unread');
    rxReact($message->id, 'wow')->assertOk();
    $this->getJson('/api/v1/family/chat/unread')->assertJsonPath('data.unread', $unread);
    rxAs($teen);
    $this->getJson('/api/v1/family/chat/unread')->assertJsonPath('data.unread', 0);

    Bus::assertNotDispatched(DeliverPushNotification::class);
});

it('rate-limits reaction changes to 60 per minute per member', function () {
    [$owner, $teen] = rxFamily();
    $message = ChatMessage::factory()->by($teen)->create();
    rxAs($owner);

    for ($i = 0; $i < 60; $i++) {
        rxReact($message->id, ChatReactions::KEYS[$i % 6])->assertOk();
    }

    rxUnreact($message->id)->assertStatus(429)->assertJsonPath('code', 'rate_limited');

    // Another member has their own budget.
    rxAs($teen);
    rxReact($message->id, 'heart')->assertOk();
});

// ---------------------------------------------------------------------------------------------
// Members leaving, deleted messages, export
// ---------------------------------------------------------------------------------------------

it('never lists or counts a former member\'s reactions, and deletes them when the member is removed or erased', function () {
    [$owner, $teen] = rxFamily();
    $leaver = addMember($owner->family, 35);
    $erased = addMember($owner->family, 40);
    $gone = addMember($owner->family, 30);
    $message = ChatMessage::factory()->by($owner)->create();

    foreach ([$teen, $leaver, $erased, $gone] as $member) {
        ChatMessageReaction::factory()->of($message, $member)->create();
    }

    // However a member stopped being active, their reactions no longer show.
    $gone->forceFill(['status' => FamilyMember::STATUS_REMOVED])->save();
    rxAs($owner);
    $this->getJson(RX_MESSAGES)->assertJsonPath('data.0.reactions.0.member_ids', [$teen->id, $leaver->id, $erased->id]);

    $cursor = rxPoll(null)['cursor'];
    app(RemoveMember::class)->handle($leaver);
    app(EraseUser::class)->handle($erased->user);

    expect(ChatMessageReaction::query()->pluck('member_id')->sort()->values()->all())->toBe([$teen->id, $gone->id]);
    $this->getJson(RX_MESSAGES)->assertJsonPath('data.0.reactions', [['key' => 'heart', 'count' => 1, 'mine' => false, 'member_ids' => [$teen->id]]]);
    // Pollers see the change.
    expect(rxPoll($cursor)['data'])->toBe([['message_id' => $message->id, 'reactions' => [['key' => 'heart', 'count' => 1, 'mine' => false, 'member_ids' => [$teen->id]]]]]);
});

it('removes the reactions of a deleted message and of messages gone by retention', function () {
    [$owner, $teen] = rxFamily();
    $message = ChatMessage::factory()->by($teen)->create();
    $old = ChatMessage::factory()->by($teen)->create(['created_at' => now()->subDays(8)]);
    ChatMessageReaction::factory()->of($message, $owner)->create();
    ChatMessageReaction::factory()->of($old, $owner)->create();

    rxAs($teen);
    $this->deleteJson(RX_MESSAGES."/{$message->id}")->assertNoContent();
    $this->getJson(RX_MESSAGES)->assertJsonPath('data.0.deleted', true)->assertJsonPath('data.0.reactions', []);

    $this->artisan('chat:prune')->assertSuccessful();
    expect(ChatMessageReaction::query()->count())->toBe(0);
});

it('includes the user\'s own reactions in the data export', function () {
    [$owner, $teen] = rxFamily();
    $message = ChatMessage::factory()->by($teen)->create();
    ChatMessageReaction::factory()->of($message, $owner, 'star')->create();
    ChatMessageReaction::factory()->of($message, $teen)->create();

    rxAs($owner);
    expect($this->getJson('/api/v1/me/export')->assertOk()->json('data.chat_reactions'))
        ->toBe([['message_id' => $message->id, 'key' => 'star', 'created_at' => '2026-11-11T12:00:00Z']]);
});

// ---------------------------------------------------------------------------------------------
// Polling by cursor
// ---------------------------------------------------------------------------------------------

it('polls reaction changes by cursor: adds, replacements, removals and deletions', function () {
    [$owner, $teen] = rxFamily();
    [, $otherTeen] = rxFamily();
    $first = ChatMessage::factory()->by($teen)->create();
    $second = ChatMessage::factory()->by($teen)->create();
    $foreign = ChatMessage::factory()->by($otherTeen)->create();

    rxAs($owner);
    $start = rxPoll(null);
    expect($start['data'])->toBe([])->and($start['cursor'])->toBeString()->not->toBe('');

    Carbon::setTestNow('2026-11-11 12:00:01');
    rxReact($second->id, 'heart')->assertOk();
    Carbon::setTestNow('2026-11-11 12:00:02');
    rxReact($first->id, 'thumbs_up')->assertOk();
    rxAs($otherTeen);
    rxReact($foreign->id, 'heart')->assertOk();

    // Oldest change first, own family only.
    rxAs($owner);
    $page = rxPoll($start['cursor']);
    expect(array_column($page['data'], 'message_id'))->toBe([$second->id, $first->id])
        ->and($page['data'][1]['reactions'])->toBe([['key' => 'thumbs_up', 'count' => 1, 'mine' => true, 'member_ids' => [$owner->id]]]);

    // Recent changes stay listed for the safety window; after it the cursor moves past them.
    Carbon::setTestNow('2026-11-11 12:00:05');
    $page = rxPoll($page['cursor']);
    expect(array_column($page['data'], 'message_id'))->toBe([$second->id, $first->id]);
    expect(rxPoll($page['cursor'])['data'])->toBe([]);
    $cursor = rxPoll($page['cursor'])['cursor'];

    // A removal lists the message with what is left.
    Carbon::setTestNow('2026-11-11 12:00:10');
    rxUnreact($second->id)->assertOk();
    expect(rxPoll($cursor)['data'])->toBe([['message_id' => $second->id, 'reactions' => []]]);

    // A deleted message is listed with no reactions.
    Carbon::setTestNow('2026-11-11 12:00:20');
    $cursor = rxPoll($cursor)['cursor'];
    Carbon::setTestNow('2026-11-11 12:00:30');
    rxAs($teen);
    $this->deleteJson(RX_MESSAGES."/{$first->id}")->assertNoContent();
    rxAs($owner);
    expect(rxPoll($cursor)['data'])->toBe([['message_id' => $first->id, 'reactions' => []]]);
});

it('pages 200 changes at a time and never lists messages past retention', function () {
    [$owner, $teen] = rxFamily();
    $messages = ChatMessage::factory()->by($teen)->count(201)->create();
    $old = ChatMessage::factory()->by($teen)->create(['created_at' => now()->subDays(8)]);

    rxAs($owner);
    $cursor = rxPoll(null)['cursor'];

    // All changed in the same microsecond: ties are ordered by id.
    Carbon::setTestNow('2026-11-11 12:00:01');
    ChatReactions::markChanged([...$messages->modelKeys(), $old->id]);

    $first = rxPoll($cursor);
    expect($first['data'])->toHaveCount(200)
        ->and($first['data'][0]['message_id'])->toBe($messages[0]->id)
        ->and($first['data'][199]['message_id'])->toBe($messages[199]->id);

    $second = rxPoll($first['cursor']);
    expect(array_column($second['data'], 'message_id'))->toBe([$messages[200]->id]);
});

it('answers 422 to a malformed cursor', function (string $cursor) {
    [$owner] = rxFamily();
    rxAs($owner);

    $this->getJson(RX_CHANGES.'?since='.urlencode($cursor))->assertUnprocessable()->assertJsonValidationErrors('since');
})->with([
    'garbage' => ['not a cursor!'],
    'not base64 of a cursor' => [rtrim(strtr(base64_encode('hello|1'), '+/', '-_'), '=')],
    'impossible date' => [rtrim(strtr(base64_encode('2026-13-45 99:00:00.000000|1'), '+/', '-_'), '=')],
    'too long' => [str_repeat('A', 101)],
]);

// ---------------------------------------------------------------------------------------------
// Query counts
// ---------------------------------------------------------------------------------------------

it('returns messages and reaction changes with a constant number of queries', function () {
    $count = function (int $messages, string $url): int {
        [$owner, $teen] = rxFamily();
        $members = [$owner, $teen, addMember($owner->family, 30), addMember($owner->family, 33)];
        $former = addMember($owner->family, 35);

        for ($i = 0; $i < $messages; $i++) {
            $message = ChatMessage::factory()->by($members[$i % 2])->create();

            foreach ([...$members, $former] as $index => $member) {
                ChatMessageReaction::factory()->of($message, $member, ChatReactions::KEYS[($i + $index) % 6])->create();
            }
        }

        $former->forceFill(['status' => FamilyMember::STATUS_REMOVED])->save();
        rxAs($owner);
        $since = rxPoll(null)['cursor'];
        Carbon::setTestNow('2026-11-11 12:00:01');
        ChatReactions::markChanged(ChatMessage::query()->where('family_id', $owner->family_id)->pluck('id')->all());
        $url = str_replace('{since}', urlencode($since), $url);

        $this->getJson($url)->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson($url)->assertOk()->assertJsonCount($messages, 'data');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        Carbon::setTestNow('2026-11-11 12:00:00');

        return $queries;
    };

    expect($count(30, RX_MESSAGES.'?limit=100'))->toBe($count(2, RX_MESSAGES.'?limit=100'))
        ->and($count(30, RX_CHANGES.'?since={since}'))->toBe($count(2, RX_CHANGES.'?since={since}'));
});
