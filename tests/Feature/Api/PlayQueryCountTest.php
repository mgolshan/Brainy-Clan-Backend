<?php

use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Play\Models\MemberItemHistory;
use App\Modules\Play\Models\SessionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Starting a session and answering run a fixed, small number of queries (docs/PLAN.md "No N+1"):
 * the batch of session items is one insert and the member's item history one upsert.
 */

/**
 * @return array{0: FamilyMember, 1: Category}
 */
function pqcSetup(int $items = 20): array
{
    $category = Category::factory()->create();
    ContentItem::factory()->count($items)->create(['category_id' => $category->id, 'difficulty' => 2]);
    $owner = familyOwner(['tier' => 'family']);
    actingAsMember($owner);

    return [$owner, $category];
}

/**
 * @param  callable(): TestResponse  $request
 * @return array{0: int, 1: TestResponse}
 */
function pqcCount(callable $request): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $request();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return [$count, $response];
}

it('starts a session with a constant number of queries', function () {
    [, $category] = pqcSetup();

    // Warm-up (policy and category caches); also leaves an unfinished session to abandon.
    test()->postJson('/api/v1/play/sessions', ['category_id' => $category->id])->assertCreated();

    [$count, $response] = pqcCount(fn () => test()->postJson('/api/v1/play/sessions', ['category_id' => $category->id]));

    $response->assertCreated()->assertJsonCount(13, 'data.items');
    // Was 53: one insert per session item plus a lookup and a write per history row.
    expect($count)->toBe(12);
});

it('answers with a constant number of queries', function () {
    [, $category] = pqcSetup();
    $sessionId = test()->postJson('/api/v1/play/sessions', ['category_id' => $category->id])->assertCreated()->json('data.session.id');
    $items = SessionItem::query()->where('play_session_id', $sessionId)->orderBy('position')->get();

    answerOnce($sessionId, $items[0]->id)->assertOk();

    [$count, $response] = pqcCount(fn () => answerOnce($sessionId, $items[1]->id));

    $response->assertOk()->assertJsonPath('data.is_correct', true);
    // Was 17 (session and family loaded twice). Includes the queued streak listener, which runs
    // synchronously in tests (4 queries).
    expect($count)->toBe(12);
});

it('still answers 404 for another member\'s session', function () {
    [, $category] = pqcSetup();
    $sessionId = test()->postJson('/api/v1/play/sessions', ['category_id' => $category->id])->assertCreated()->json('data.session.id');
    $itemId = SessionItem::query()->where('play_session_id', $sessionId)->value('id');

    actingAsMember(familyOwner(['tier' => 'family']));

    answerOnce($sessionId, (int) $itemId)->assertNotFound()->assertJsonPath('code', 'not_found');
});

it('counts items seen again in the bulk history upsert', function () {
    [$member, $category] = pqcSetup(13);

    test()->travelTo(now()->subHour());
    test()->postJson('/api/v1/play/sessions', ['category_id' => $category->id])->assertCreated();
    test()->travelBack();
    $before = MemberItemHistory::query()->where('member_id', $member->id)->pluck('last_seen_at', 'content_item_id');

    $sessionId = test()->postJson('/api/v1/play/sessions', ['category_id' => $category->id])->assertCreated()->json('data.session.id');

    $history = MemberItemHistory::query()->where('member_id', $member->id)->get();
    $positions = SessionItem::query()->where('play_session_id', $sessionId)->orderBy('position')->pluck('position')->all();

    expect($history)->toHaveCount(13)
        ->and($history->every(fn (MemberItemHistory $row) => $row->times_seen === 2))->toBeTrue()
        ->and($history->every(fn (MemberItemHistory $row) => $row->last_seen_at->gt($before[$row->content_item_id])))->toBeTrue()
        ->and($positions)->toBe(range(1, 13));
});

function answerOnce(int $sessionId, int $itemId): TestResponse
{
    return test()->postJson("/api/v1/play/sessions/{$sessionId}/answers", [
        'session_item_id' => $itemId,
        'response' => ['choice_id' => 'a'],
        'time_ms' => 900,
    ]);
}
