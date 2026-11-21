<?php

use App\Modules\Chat\Events\MessagePosted;
use App\Modules\Chat\Events\MessageReported;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Support\ChatText;
use App\Modules\Chat\Support\ProfanityFilter;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Jobs\DeliverPushNotification;
use App\Modules\Notifications\Listeners\NotifyFamilyChat;
use App\Modules\Notifications\Listeners\NotifyFamilyChatReport;
use App\Modules\Policy\Models\Setting;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Chat safety (docs/API.md "Chat safety", B12 step 2): the bad-words filter (every plan, on by
 * default) and the Classroom chat modes (`announcements`, `off`).
 */
const SAFETY_CHAT_URL = '/api/v1/family/chat/messages';

function safetyMask(string $text): string
{
    return (new ProfanityFilter)->mask($text)['text'];
}

/**
 * @return array{0: FamilyMember, 1: FamilyMember} admin and a teen member
 */
function safetyFamily(string $tier = Family::TIER_CLASSROOM, array $attributes = []): array
{
    $owner = familyOwner(['tier' => $tier, ...$attributes]);

    return [$owner, addMember($owner->family, 15)];
}

function safetyAs(FamilyMember $member): void
{
    app('auth')->forgetGuards();
    actingAsMember($member);
}

function safetyPost(string $body): TestResponse
{
    return test()->postJson(SAFETY_CHAT_URL, ['body' => $body, 'client_id' => (string) Str::uuid()]);
}

// ---------------------------------------------------------------------------------------------
// The filter
// ---------------------------------------------------------------------------------------------

it('masks blocked words letter by letter and keeps the length', function (string $input, string $expected) {
    $result = (new ProfanityFilter)->mask($input);

    expect($result['text'])->toBe($expected)
        ->and($result['filtered'])->toBeTrue()
        ->and(mb_strlen($result['text']))->toBe(mb_strlen($input));
})->with([
    'plain' => ['what the fuck', 'what the ★★★★'],
    'case' => ['SHIT happens', '★★★★ happens'],
    'punctuation around' => ['oh, shit!', 'oh, ★★★★!'],
    'leet' => ['you a$$hole', 'you ★★★★★★★'],
    'leet digits' => ['5h1t', '★★★★'],
    'leet at sign' => ['b1tch @ss', '★★★★★ ★★★'],
    'repeated letters' => ['fuuuuuck you', '★★★★★★★★ you'],
    'dots inside' => ['f.u.c.k off', '★.★.★.★ off'],
    'dashes and stars' => ['s-h-i-t and f*ck', '★-★-★-★ and ★*★★'],
    'spaced letters' => ['f u c k this', '★ ★ ★ ★ this'],
    'zero width' => ["sh\u{200B}it", "★★\u{200B}★★"],
    'accents' => ['fück', '★★★★'],
    'phrase' => ['son of a bitch', '★★★ ★★ ★ ★★★★★'],
    'emoji kept' => ['🎉 bullshit 🎉', '🎉 ★★★★★★★★ 🎉'],
    'french' => ['putain de merde', '★★★★★★ de ★★★★★'],
    'french accents' => ['espèce d’enculé', 'espèce d’★★★★★★'],
    'french no accents' => ['ENCULE', '★★★★★★'],
    'french phrase' => ['ferme ta gueule', '★★★★★ ★★ ★★★★★★'],
    'french insult' => ['sale connard', 'sale ★★★★★★★'],
    'french cul' => ['mon cul', 'mon ★★★'],
]);

it('never masks harmless words that contain a blocked word', function (string $text) {
    expect((new ProfanityFilter)->mask($text))->toBe(['text' => $text, 'filtered' => false]);
})->with([
    'class', 'Scunthorpe', 'assassin', 'passage', 'cocktail', 'shiitake', 'shitake mushrooms', 'Dickens',
    'Cockburn', 'Hancock', 'peacock', 'shuttlecock', 'Penistone', 'Essex', 'Sussex', 'classic', 'bass',
    'grass', 'assume', 'assessment', 'embarrass', 'therapist', 'button', 'pussycat', 'Titanic', 'title',
    'constitution', 'computer', 'analysis', 'Niger', 'Nigeria', 'snigger', 'spice', 'Pakistan', 'Matsushita',
    'Assisi', 'scrap', 'crapaud', 'pissenlit', 'cull', 'as', 'a s', 'a s s e t', 'hell', 'dam',
    'député', 'dispute', 'réputé', 'computing', 'culture', 'reculer', 'basculer', 'calcul', 'culotte',
    'connexion', 'connaissance', 'Conard', 'merci', 'mercredi', 'putois', 'chiens', 'cul-de-sac',
    'un cul de sac', 'cul sec', 'con', 'bite', 'chatte', 'baiser', 'en retard', 'douche', 'pipe',
    'pédale', 'Je suis à la classe de 5e', 'Score: 10/10 !!! 🎉', 'the 1st of May', 'R2-D2', 'Dickinson',
]);

it('keeps the whole matched span masked and the rest of the text as is', function () {
    expect(safetyMask('Hello shit-head, nice class!'))->toBe('Hello ★★★★-★★★★, nice class!')
        ->and(safetyMask('shithead'))->toBe('★★★★★★★★');
});

it('matches a 1000-character message quickly', function () {
    $text = mb_substr(str_repeat('Une très belle journée à la plage, class assassin passage shiitake 🎉 ', 20).'fuck', -ChatText::MAX_LENGTH);
    (new ProfanityFilter)->mask('warm up');

    $start = hrtime(true);
    $result = (new ProfanityFilter)->mask($text);
    $milliseconds = (hrtime(true) - $start) / 1e6;

    expect($result['filtered'])->toBeTrue()
        ->and(mb_strlen($result['text']))->toBe(mb_strlen($text))
        ->and(str_ends_with($result['text'], '★★★★'))->toBeTrue()
        ->and($milliseconds)->toBeLessThan(100);
});

// ---------------------------------------------------------------------------------------------
// The filter in the chat
// ---------------------------------------------------------------------------------------------

it('stores the masked text with filtered: true and never the original', function () {
    [, $teen] = safetyFamily(Family::TIER_FREE);
    safetyAs($teen);

    $response = safetyPost('you are a b1tch')->assertCreated()
        ->assertJsonPath('data.body', 'you are a ★★★★★')
        ->assertJsonPath('data.filtered', true);

    $message = ChatMessage::query()->findOrFail($response->json('data.id'));
    expect($message->body)->toBe('you are a ★★★★★')
        ->and($message->filtered)->toBeTrue()
        ->and(json_encode(DB::table('chat_messages')->get()))->not->toContain('b1tch');

    safetyPost('a nice class')->assertCreated()->assertJsonPath('data.filtered', false)->assertJsonPath('data.body', 'a nice class');
    $this->getJson(SAFETY_CHAT_URL)->assertOk()->assertJsonPath('data.0.filtered', false)->assertJsonPath('data.1.filtered', true);
});

it('lets an admin turn the filter off for new messages only', function () {
    [$owner, $teen] = safetyFamily(Family::TIER_FREE);
    safetyAs($teen);
    safetyPost('shit')->assertCreated()->assertJsonPath('data.body', '★★★★');

    safetyAs($owner);
    $this->getJson('/api/v1/family')->assertJsonPath('data.family.chat_filter', true);
    $this->patchJson('/api/v1/family', ['chat_filter' => false])->assertOk()->assertJsonPath('data.chat_filter', false);
    expect($owner->family->refresh()->chat_filter)->toBeFalse();

    safetyAs($teen->fresh());
    safetyPost('shit')->assertCreated()->assertJsonPath('data.body', 'shit')->assertJsonPath('data.filtered', false);
    $this->patchJson('/api/v1/family', ['chat_filter' => true])->assertForbidden()->assertJsonPath('code', 'forbidden');

    expect(ChatMessage::query()->orderBy('id')->pluck('body')->all())->toBe(['★★★★', 'shit']);
});

it('counts the masked length within the 1000-character limit', function () {
    [, $teen] = safetyFamily(Family::TIER_FREE);
    safetyAs($teen);

    $body = str_repeat('a', ChatText::MAX_LENGTH - 5).' shit';
    $response = safetyPost($body)->assertCreated();

    expect(mb_strlen($response->json('data.body')))->toBe(ChatText::MAX_LENGTH)
        ->and($response->json('data.body'))->toEndWith(' ★★★★');
});

// ---------------------------------------------------------------------------------------------
// Chat modes: PATCH /family, policy, effective mode
// ---------------------------------------------------------------------------------------------

it('exposes chat.modes_allowed in the policy: classroom only', function (string $tier, bool $allowed) {
    $owner = familyOwner(['tier' => $tier]);
    safetyAs($owner);

    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.policy.chat.modes_allowed', $allowed);
})->with([
    [Family::TIER_FREE, false],
    [Family::TIER_FAMILY, false],
    [Family::TIER_BIG_CLAN, false],
    [Family::TIER_CLASSROOM, true],
]);

it('lets a Classroom admin set the chat mode, but not other members', function () {
    [$owner, $teen] = safetyFamily();
    safetyAs($owner);

    $this->getJson('/api/v1/family')->assertJsonPath('data.family.chat_mode', 'normal');
    $this->patchJson('/api/v1/family', ['chat_mode' => 'announcements'])->assertOk()->assertJsonPath('data.chat_mode', 'announcements');
    $this->patchJson('/api/v1/family', ['chat_mode' => 'silent'])->assertUnprocessable()->assertJsonValidationErrors('chat_mode');
    $this->patchJson('/api/v1/family', ['chat_filter' => 'maybe'])->assertUnprocessable()->assertJsonValidationErrors('chat_filter');

    safetyAs($teen);
    $this->patchJson('/api/v1/family', ['chat_mode' => 'off'])->assertForbidden()->assertJsonPath('code', 'forbidden');

    expect($owner->family->refresh()->chat_mode)->toBe('announcements');
});

it('answers 403 plan_required for chat modes on other plans', function (string $tier) {
    [$owner] = safetyFamily($tier);
    safetyAs($owner);

    $this->patchJson('/api/v1/family', ['chat_mode' => 'off', 'name' => 'Renamed'])->assertForbidden()->assertJsonPath('code', 'plan_required');
    $family = $owner->family->refresh();
    expect($family->name)->not->toBe('Renamed')->and($family->chat_mode)->toBe('normal');

    // Sending the effective mode back is a no-op, so settings forms can send every field.
    $this->patchJson('/api/v1/family', ['chat_mode' => 'normal', 'chat_filter' => false])->assertOk()
        ->assertJsonPath('data.chat_mode', 'normal')->assertJsonPath('data.chat_filter', false);
})->with([Family::TIER_FREE, Family::TIER_FAMILY, Family::TIER_BIG_CLAN]);

it('falls back to the normal mode after a downgrade and keeps the stored one for an upgrade', function () {
    [$owner, $teen] = safetyFamily(Family::TIER_CLASSROOM, ['chat_mode' => Family::CHAT_MODE_OFF]);
    safetyAs($teen);
    $this->getJson(SAFETY_CHAT_URL)->assertForbidden()->assertJsonPath('code', 'chat_off');

    $owner->family->forceFill(['tier' => Family::TIER_FAMILY])->save();
    safetyAs($teen->fresh());
    $this->getJson(SAFETY_CHAT_URL)->assertOk();
    safetyPost('hello')->assertCreated();
    $this->getJson('/api/v1/family')->assertJsonPath('data.family.chat_mode', 'normal');

    safetyAs($owner->fresh());
    $this->patchJson('/api/v1/family', ['chat_mode' => 'normal'])->assertOk();
    expect($owner->family->refresh()->chat_mode)->toBe('off');

    $owner->family->forceFill(['tier' => Family::TIER_CLASSROOM])->save();
    safetyAs($owner->fresh());
    $this->getJson('/api/v1/family')->assertJsonPath('data.family.chat_mode', 'off');
});

it('reads modes_allowed from the tier settings an admin can edit', function () {
    Setting::query()->where('key', 'chat.modes_allowed')->where('scope_id', 'big_clan')->update(['value' => json_encode(true)]);
    PolicyResolver::flushCache();
    [$owner] = safetyFamily(Family::TIER_BIG_CLAN);
    safetyAs($owner);

    $this->patchJson('/api/v1/family', ['chat_mode' => 'announcements'])->assertOk()->assertJsonPath('data.chat_mode', 'announcements');
});

// ---------------------------------------------------------------------------------------------
// Enforcement
// ---------------------------------------------------------------------------------------------

it('lets only admins post in announcements mode; others still read, react and report', function () {
    [$owner, $teen] = safetyFamily(Family::TIER_CLASSROOM, ['chat_mode' => Family::CHAT_MODE_ANNOUNCEMENTS]);
    $coTeacher = addMember($owner->family, 35, FamilyMember::ROLE_ADMIN);

    safetyAs($owner);
    $id = safetyPost('Homework: page 12')->assertCreated()->json('data.id');
    safetyAs($coTeacher);
    safetyPost('Quiz on Friday')->assertCreated();

    safetyAs($teen);
    safetyPost('can I post?')->assertForbidden()->assertJsonPath('code', 'chat_read_only');
    $this->getJson(SAFETY_CHAT_URL)->assertOk()->assertJsonCount(2, 'data');
    $this->putJson(SAFETY_CHAT_URL."/{$id}/reaction", ['key' => 'heart'])->assertOk();
    $this->postJson(SAFETY_CHAT_URL."/{$id}/report")->assertNoContent();
    $this->postJson('/api/v1/family/chat/read', ['last_read_id' => $id])->assertNoContent();
    $this->getJson('/api/v1/family/chat/unread')->assertOk()->assertJsonPath('data.unread', 1);

    expect(ChatMessage::query()->count())->toBe(2);
});

it('answers 403 chat_off on every chat endpoint, admins included, and 0 unread', function (string $who) {
    [$owner, $teen] = safetyFamily(Family::TIER_CLASSROOM);
    $message = ChatMessage::factory()->by($owner)->create();
    $owner->family->forceFill(['chat_mode' => Family::CHAT_MODE_OFF])->save();
    safetyAs($who === 'admin' ? $owner : $teen);

    $requests = [
        ['GET', SAFETY_CHAT_URL, []],
        ['POST', SAFETY_CHAT_URL, ['body' => 'hi', 'client_id' => (string) Str::uuid()]],
        ['DELETE', SAFETY_CHAT_URL."/{$message->id}", []],
        ['POST', SAFETY_CHAT_URL."/{$message->id}/report", []],
        ['PUT', SAFETY_CHAT_URL."/{$message->id}/reaction", ['key' => 'heart']],
        ['DELETE', SAFETY_CHAT_URL."/{$message->id}/reaction", []],
        ['GET', '/api/v1/family/chat/reactions', []],
        ['POST', '/api/v1/family/chat/read', ['last_read_id' => $message->id]],
    ];

    foreach ($requests as [$method, $url, $data]) {
        $this->json($method, $url, $data)->assertForbidden()->assertJsonPath('code', 'chat_off');
    }

    $this->getJson('/api/v1/family/chat/unread')->assertOk()->assertExactJson(['data' => ['unread' => 0]]);
    expect(ChatMessage::query()->count())->toBe(1);
})->with(['admin', 'member']);

it('keeps chat_disabled per member on top of the mode', function () {
    [$owner, $teen] = safetyFamily(Family::TIER_CLASSROOM, ['chat_mode' => Family::CHAT_MODE_ANNOUNCEMENTS]);
    $teen->forceFill(['chat_enabled' => false])->save();
    ChatMessage::factory()->by($owner)->create();
    safetyAs($teen);

    $this->getJson(SAFETY_CHAT_URL)->assertForbidden()->assertJsonPath('code', 'chat_disabled');
    $this->getJson('/api/v1/family/chat/unread')->assertForbidden()->assertJsonPath('code', 'chat_disabled');

    $owner->family->forceFill(['chat_mode' => Family::CHAT_MODE_OFF])->save();
    safetyAs($teen->fresh());
    $this->getJson(SAFETY_CHAT_URL)->assertForbidden()->assertJsonPath('code', 'chat_off');
    $this->getJson('/api/v1/family/chat/unread')->assertOk()->assertJsonPath('data.unread', 0);
});

it('sends no chat or report pushes while the chat is off, and pushes in announcements mode', function () {
    Bus::fake([DeliverPushNotification::class]);
    $this->travelTo(now()->setTimezone('America/Toronto')->setTime(12, 0));
    [$owner, $teen] = safetyFamily(Family::TIER_CLASSROOM, ['chat_mode' => Family::CHAT_MODE_ANNOUNCEMENTS]);
    $coTeacher = addMember($owner->family, 35, FamilyMember::ROLE_ADMIN);
    foreach ([$owner, $teen, $coTeacher] as $member) {
        Device::factory()->create(['user_id' => $member->user_id, 'push_token' => "ExponentPushToken[s{$member->id}]"]);
    }

    safetyAs($owner);
    $id = safetyPost('Homework: page 12')->assertCreated()->json('data.id');
    Bus::assertDispatched(DeliverPushNotification::class);

    Bus::fake([DeliverPushNotification::class]);
    $owner->family->forceFill(['chat_mode' => Family::CHAT_MODE_OFF])->save();
    app(NotifyFamilyChat::class)->handle(new MessagePosted($id, $owner->family_id, $owner->id));
    app(NotifyFamilyChatReport::class)->handle(new MessageReported($id, $owner->family_id, $teen->id));

    Bus::assertNotDispatched(DeliverPushNotification::class);
});

it('checks the chat mode without extra queries per message', function () {
    $count = function (int $messages): int {
        [$owner, $teen] = safetyFamily(Family::TIER_CLASSROOM, ['chat_mode' => Family::CHAT_MODE_ANNOUNCEMENTS]);
        ChatMessage::factory()->count($messages)->by($owner)->create();
        safetyAs($teen);
        $this->getJson(SAFETY_CHAT_URL)->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson(SAFETY_CHAT_URL.'?limit=100')->assertOk()->assertJsonCount($messages, 'data');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    expect($count(40))->toBe($count(2));
});

it('shows the chat filter and mode on the admin family page', function () {
    [$owner] = safetyFamily(Family::TIER_CLASSROOM, ['chat_mode' => Family::CHAT_MODE_ANNOUNCEMENTS, 'chat_filter' => false]);

    $this->actingAs(User::factory()->staff()->create())
        ->get("/admin/families/{$owner->family_id}")
        ->assertOk()
        ->assertSee('Chat bad-words filter')
        ->assertSee('Announcements');
});
