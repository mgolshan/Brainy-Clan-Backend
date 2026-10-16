<?php

use App\Modules\Billing\Models\AdReward;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Play\Models\MemberItemHistory;
use App\Modules\Play\Models\PlaySession;
use App\Modules\Play\Models\SecondChance;
use App\Modules\Play\Models\SessionItem;
use App\Modules\Scoring\Models\PointEntry;
use App\Modules\Scoring\Models\Streak;
use Illuminate\Testing\TestResponse;

/**
 * A playable category (multiple-choice items, "a" correct) and a signed-in member.
 *
 * @return array{0: FamilyMember, 1: Category}
 */
function playSetup(string $tier = 'family', int $age = 40): array
{
    $category = Category::factory()->create();
    ContentItem::factory()->count(20)->create(['category_id' => $category->id, 'difficulty' => 2]);
    ContentItem::factory()->count(5)->create(['category_id' => $category->id, 'difficulty' => 1]);

    $owner = familyOwner(['tier' => $tier]);
    $member = $age === 40 ? $owner : addMember($owner->family, $age);
    actingAsMember($member);

    return [$member, $category];
}

/**
 * @return array<string, mixed> the started session payload
 */
function startSession(Category $category): array
{
    return test()->postJson('/api/v1/play/sessions', ['category_id' => $category->id])->assertCreated()->json('data');
}

function answer(int $sessionId, int $itemId, bool $correct): TestResponse
{
    return test()->postJson("/api/v1/play/sessions/{$sessionId}/answers", [
        'session_item_id' => $itemId,
        'response' => ['choice_id' => $correct ? 'a' : 'b'],
        'time_ms' => 1200,
    ]);
}

/**
 * Answer the next unanswered regular items.
 */
function answerNext(int $sessionId, int $count, bool $correct): TestResponse
{
    $response = null;

    for ($i = 0; $i < $count; $i++) {
        $item = SessionItem::query()->where('play_session_id', $sessionId)->where('is_redemption', false)->whereNull('answered_at')->orderBy('position')->firstOrFail();
        $response = answer($sessionId, $item->id, $correct)->assertOk();
    }

    return $response;
}

function failSession(int $sessionId): void
{
    answerNext($sessionId, 3, false)->assertJsonPath('data.session.status', 'failed');
}

it('needs auth and a family', function () {
    $this->postJson('/api/v1/play/sessions', ['category_id' => 1])->assertStatus(401);
});

it('starts a session with target + cap items and no answers', function () {
    [$member, $category] = playSetup();

    $data = startSession($category);

    expect(array_intersect_key($data['session'], array_flip(['category_id', 'status', 'target_points', 'blunder_cap', 'mistakes', 'points', 'answered', 'second_chances_used', 'completed_at'])))->toBe([
        'category_id' => $category->id, 'status' => 'active', 'target_points' => 10, 'blunder_cap' => 3,
        'mistakes' => 0, 'points' => 0, 'answered' => 0, 'second_chances_used' => 0, 'completed_at' => null,
    ])->and($data['session']['second_chance'])->toBe(['free_remaining_today' => 3, 'ad_available' => false, 'redemption_available' => true])
        ->and($data['items'])->toHaveCount(13)
        ->and($data['items'][0])->toHaveKeys(['id', 'position', 'type', 'is_redemption', 'payload', 'answered'])
        ->and($data['items'][0]['payload'])->not->toHaveKey('answer')
        ->and(json_encode($data['items']))->not->toContain('Because.')
        ->and(MemberItemHistory::query()->where('member_id', $member->id)->count())->toBe(13);
});

it('rejects locked categories and empty categories', function () {
    $roots = Category::factory()->count(4)->sequence(fn ($s) => ['sort' => $s->index])->create();
    $roots->each(fn (Category $c) => ContentItem::factory()->create(['category_id' => $c->id]));
    $empty = Category::factory()->create(['sort' => -1]);
    actingAsMember(familyOwner(['tier' => 'free']));

    $this->postJson('/api/v1/play/sessions', ['category_id' => $roots[3]->id])
        ->assertForbidden()->assertJsonPath('code', 'category_not_allowed');
    $this->postJson('/api/v1/play/sessions', ['category_id' => $empty->id])
        ->assertUnprocessable()->assertJsonValidationErrors('category_id');
});

it('only shows the member their own sessions', function () {
    [$member, $category] = playSetup();
    $data = startSession($category);

    $this->getJson("/api/v1/play/sessions/{$data['session']['id']}")->assertOk()->assertJsonCount(13, 'data.items');

    actingAsMember(addMember($member->family, 30));
    $this->getJson("/api/v1/play/sessions/{$data['session']['id']}")->assertNotFound();
});

it('scores a correct answer and appends one ledger row', function () {
    [$member, $category] = playSetup();
    $data = startSession($category);
    $item = $data['items'][0];

    answer($data['session']['id'], $item['id'], true)
        ->assertOk()
        ->assertJsonPath('data.is_correct', true)
        ->assertJsonPath('data.correct_response', ['choice_id' => 'a'])
        ->assertJsonPath('data.explanation', 'Because.')
        ->assertJsonPath('data.points_awarded', 1)
        ->assertJsonPath('data.session.points', 1)
        ->assertJsonPath('data.session.answered', 1);

    $entry = PointEntry::query()->sole();
    expect($entry->member_id)->toBe($member->id)
        ->and($entry->amount)->toBe(1)
        ->and($entry->reason)->toBe(PointEntry::REASON_CORRECT_ANSWER)
        ->and($entry->category_id)->toBe($category->id)
        ->and($entry->session_item_id)->toBe($item['id'])
        ->and(Streak::query()->where('member_id', $member->id)->value('current'))->toBe(1)
        ->and((int) MemberItemHistory::query()->where('member_id', $member->id)->sum('times_correct'))->toBe(1);
});

it('counts a wrong answer as a mistake without points', function () {
    [, $category] = playSetup();
    $data = startSession($category);

    answer($data['session']['id'], $data['items'][0]['id'], false)
        ->assertOk()
        ->assertJsonPath('data.is_correct', false)
        ->assertJsonPath('data.points_awarded', 0)
        ->assertJsonPath('data.session.mistakes', 1);

    expect(PointEntry::query()->count())->toBe(0);
});

it('answers 409 for an item answered twice and 422 for a foreign item', function () {
    [, $category] = playSetup();
    $data = startSession($category);
    $id = $data['session']['id'];

    answer($id, $data['items'][0]['id'], true)->assertOk();
    answer($id, $data['items'][0]['id'], true)->assertStatus(409)->assertJsonPath('code', 'item_already_answered');
    answer($id, 999999, true)->assertUnprocessable()->assertJsonValidationErrors('session_item_id');

    expect(PointEntry::query()->count())->toBe(1);
});

it('rejects a response that is not one of the item choices without counting a mistake', function () {
    [, $category] = playSetup();
    $data = startSession($category);
    $id = $data['session']['id'];

    $this->postJson("/api/v1/play/sessions/{$id}/answers", ['session_item_id' => $data['items'][0]['id'], 'response' => ['choice_id' => 'zz']])
        ->assertUnprocessable()->assertJsonPath('code', 'validation_failed')->assertJsonValidationErrors('response.choice_id');
    $this->postJson("/api/v1/play/sessions/{$id}/answers", ['session_item_id' => $data['items'][0]['id'], 'response' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('response.choice_id');

    $this->getJson("/api/v1/play/sessions/{$id}")->assertJsonPath('data.session.mistakes', 0)->assertJsonPath('data.items.0.answered', false);
});

it('completes the session at the target', function () {
    [, $category] = playSetup();
    $id = startSession($category)['session']['id'];

    answerNext($id, 10, true)
        ->assertJsonPath('data.session.status', 'completed')
        ->assertJsonPath('data.session.points', 10);

    expect(PlaySession::query()->find($id)->completed_at)->not->toBeNull()
        ->and((int) PointEntry::query()->sum('amount'))->toBe(10);

    $item = SessionItem::query()->where('play_session_id', $id)->whereNull('answered_at')->firstOrFail();
    answer($id, $item->id, true)->assertStatus(409)->assertJsonPath('code', 'session_not_active');
});

it('fails the session at the blunder cap and blocks further answers', function () {
    [, $category] = playSetup();
    $id = startSession($category)['session']['id'];

    failSession($id);

    $item = SessionItem::query()->where('play_session_id', $id)->whereNull('answered_at')->firstOrFail();
    answer($id, $item->id, true)->assertStatus(409)->assertJsonPath('code', 'session_not_active');
});

it('grants a free second chance on the family plan and tops up items', function () {
    [$member, $category] = playSetup('family');
    $id = startSession($category)['session']['id'];
    answerNext($id, 2, true);
    failSession($id);

    $this->postJson("/api/v1/play/sessions/{$id}/second-chance", ['method' => 'free'])
        ->assertOk()
        ->assertJsonPath('data.session.status', 'active')
        ->assertJsonPath('data.session.mistakes', 0)
        ->assertJsonPath('data.session.second_chances_used', 1)
        ->assertJsonPath('data.session.second_chance.free_remaining_today', 2)
        ->assertJsonCount(16, 'data.items');

    // (10 - 2) + 3 = 11 unanswered regular items needed, 8 left, so 3 are added.
    expect(SessionItem::query()->where('play_session_id', $id)->whereNull('answered_at')->count())->toBe(11)
        ->and(SecondChance::query()->where('member_id', $member->id)->value('method'))->toBe('free');
});

it('refuses second chances when not failed or not allowed', function () {
    [, $category] = playSetup('family');
    $id = startSession($category)['session']['id'];

    $this->postJson("/api/v1/play/sessions/{$id}/second-chance", ['method' => 'free'])
        ->assertStatus(409)->assertJsonPath('code', 'session_not_active');

    failSession($id);

    $this->postJson("/api/v1/play/sessions/{$id}/second-chance", ['method' => 'ad'])
        ->assertForbidden()->assertJsonPath('code', 'second_chance_unavailable');
    $this->postJson("/api/v1/play/sessions/{$id}/second-chance", ['method' => 'other'])
        ->assertUnprocessable();
});

it('refuses a free second chance on the free tier', function () {
    [, $category] = playSetup('free');
    $id = startSession($category)['session']['id'];
    failSession($id);

    $this->postJson("/api/v1/play/sessions/{$id}/second-chance", ['method' => 'free'])
        ->assertForbidden()->assertJsonPath('code', 'second_chance_unavailable');
});

it('grants an ad second chance to a free-tier adult and records the reward ref', function () {
    [$member, $category] = playSetup('free');
    $id = startSession($category)['session']['id'];
    failSession($id);
    // Stored by AdMob's server-side verification callback (see AdRewardsTest).
    $reward = AdReward::factory()->create(['reward_ref' => 'admob-123', 'member_id' => $member->id, 'play_session_id' => $id]);

    $this->postJson("/api/v1/play/sessions/{$id}/second-chance", ['method' => 'ad', 'reward_ref' => 'admob-123'])
        ->assertOk()->assertJsonPath('data.session.status', 'active');

    expect(SecondChance::query()->where('member_id', $member->id)->value('reward_ref'))->toBe('admob-123')
        ->and($reward->fresh()?->used_at)->not->toBeNull();
});

it('never offers ads to children', function () {
    [, $category] = playSetup('free', 9);
    $data = startSession($category);
    expect($data['session']['second_chance']['ad_available'])->toBeFalse();
    failSession($data['session']['id']);

    $this->postJson("/api/v1/play/sessions/{$data['session']['id']}/second-chance", ['method' => 'ad'])
        ->assertForbidden()->assertJsonPath('code', 'second_chance_unavailable');
});

it('runs a successful redemption round', function () {
    [$member, $category] = playSetup('free', 9);
    $id = startSession($category)['session']['id'];
    failSession($id);

    $data = $this->postJson("/api/v1/play/sessions/{$id}/redemption")
        ->assertOk()
        ->assertJsonPath('data.session.status', 'redemption')
        ->assertJsonCount(16, 'data.items')
        ->json('data');

    $redemption = collect($data['items'])->where('is_redemption', true)->values();
    expect($redemption)->toHaveCount(3);

    // Regular items cannot be answered during the round.
    $regular = collect($data['items'])->where('is_redemption', false)->where('answered', false)->first();
    answer($id, $regular['id'], true)->assertStatus(409)->assertJsonPath('code', 'session_not_active');

    answer($id, $redemption[0]['id'], true)->assertOk()->assertJsonPath('data.points_awarded', 0)->assertJsonPath('data.session.status', 'redemption');
    answer($id, $redemption[1]['id'], true)->assertOk();
    answer($id, $redemption[2]['id'], true)->assertOk()
        ->assertJsonPath('data.session.status', 'active')
        ->assertJsonPath('data.session.mistakes', 0)
        ->assertJsonPath('data.session.second_chances_used', 1)
        ->assertJsonPath('data.session.answered', 3);

    expect(PointEntry::query()->count())->toBe(0)
        ->and(SecondChance::query()->where('member_id', $member->id)->value('method'))->toBe('redemption');
});

it('fails a redemption round on any wrong answer and does not offer it again', function () {
    [, $category] = playSetup('free', 9);
    $id = startSession($category)['session']['id'];
    failSession($id);

    $items = collect($this->postJson("/api/v1/play/sessions/{$id}/redemption")->json('data.items'))->where('is_redemption', true)->values();

    answer($id, $items[0]['id'], false)->assertOk()
        ->assertJsonPath('data.session.status', 'failed')
        ->assertJsonPath('data.session.second_chance.redemption_available', false);

    $this->postJson("/api/v1/play/sessions/{$id}/redemption")
        ->assertForbidden()->assertJsonPath('code', 'second_chance_unavailable');
    answer($id, $items[1]['id'], true)->assertStatus(409)->assertJsonPath('code', 'session_not_active');

    // The unanswered redemption items are void: only the answered one is still listed.
    $listed = collect($this->getJson("/api/v1/play/sessions/{$id}")->json('data.items'))->where('is_redemption', true);
    expect($listed->pluck('id')->all())->toBe([$items[0]['id']]);
});

it('refuses a redemption round while active', function () {
    [, $category] = playSetup();
    $id = startSession($category)['session']['id'];

    $this->postJson("/api/v1/play/sessions/{$id}/redemption")->assertStatus(409)->assertJsonPath('code', 'session_not_active');
});

it('abandons a session once', function () {
    [, $category] = playSetup();
    $id = startSession($category)['session']['id'];

    $this->postJson("/api/v1/play/sessions/{$id}/abandon")->assertOk()->assertJsonPath('data.status', 'abandoned');
    $this->postJson("/api/v1/play/sessions/{$id}/abandon")->assertStatus(409)->assertJsonPath('code', 'session_not_active');
});

it('keeps the ledger append-only', function () {
    [, $category] = playSetup();
    $data = startSession($category);
    answer($data['session']['id'], $data['items'][0]['id'], true);
    $entry = PointEntry::query()->sole();

    expect(fn () => $entry->update(['amount' => 5]))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);
});
