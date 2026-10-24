<?php

use App\Modules\Billing\Contracts\AdMobVerifierKeys;
use App\Modules\Billing\Models\AdReward;
use App\Modules\Billing\Support\HttpAdMobVerifierKeys;
use App\Modules\Billing\Support\WebhookRejections;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Play\Models\PlaySession;
use App\Modules\Play\Models\SecondChance;
use App\Modules\Play\Models\SessionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * B8: AdMob rewarded-ad server-side verification (`GET /webhooks/admob-ssv`) and the `ad` second
 * chance that consumes the stored reward (docs/API.md "Rewarded ads"). Google's verifier keys are
 * faked with a freshly generated P-256 key.
 */
class FakeAdMobVerifierKeys implements AdMobVerifierKeys
{
    public int $refreshes = 0;

    /** @param array<string, string> $keys */
    public function __construct(public array $keys) {}

    public function keys(bool $refresh = false): array
    {
        $this->refreshes += $refresh ? 1 : 0;

        return $this->keys;
    }
}

const SSV_KEY_ID = '3335741209';

beforeEach(function () {
    $this->ecKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    $this->keys = new FakeAdMobVerifierKeys([SSV_KEY_ID => openssl_pkey_get_details($this->ecKey)['key']]);
    app()->instance(AdMobVerifierKeys::class, $this->keys);
});

/**
 * The SSV query string AdMob would send: the signed content, then `signature` and `key_id`.
 *
 * @param  array<string, string>  $params
 */
function ssvQuery(array $params, ?OpenSSLAsymmetricKey $key = null, string $keyId = SSV_KEY_ID): string
{
    $message = http_build_query([
        'ad_network' => '5450213213286189855',
        'ad_unit' => '1234567890',
        'reward_amount' => '1',
        'reward_item' => 'Second chance',
        'timestamp' => (string) now()->getTimestampMs(),
        'transaction_id' => Str::random(32),
        ...$params,
    ], '', '&', PHP_QUERY_RFC3986);

    openssl_sign($message, $signature, $key ?? test()->ecKey, OPENSSL_ALGO_SHA256);

    return $message.'&signature='.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=').'&key_id='.$keyId;
}

function ssv(string $query): TestResponse
{
    return test()->getJson('/api/v1/webhooks/admob-ssv?'.$query);
}

/**
 * A free-tier adult (ads allowed) signed in with a failed session.
 *
 * @return array{0: FamilyMember, 1: int}
 */
function adFailedSession(string $tier = 'free', int $age = 40): array
{
    $category = Category::factory()->create();
    ContentItem::factory()->count(25)->create(['category_id' => $category->id, 'difficulty' => 2]);
    $owner = familyOwner(['tier' => $tier]);
    $member = $age === 40 ? $owner : addMember($owner->family, $age);
    actingAsMember($member);

    $id = test()->postJson('/api/v1/play/sessions', ['category_id' => $category->id])->assertCreated()->json('data.session.id');
    adFail($id);

    return [$member, $id];
}

function adFail(int $sessionId): void
{
    for ($i = 0; $i < 3; $i++) {
        $item = SessionItem::query()->where('play_session_id', $sessionId)->where('is_redemption', false)->whereNull('answered_at')->orderBy('position')->firstOrFail();
        test()->postJson("/api/v1/play/sessions/{$sessionId}/answers", ['session_item_id' => $item->id, 'response' => ['choice_id' => 'b'], 'time_ms' => 900])->assertOk();
    }

    test()->getJson("/api/v1/play/sessions/{$sessionId}")->assertJsonPath('data.session.status', 'failed');
}

function adSecondChance(int $sessionId, ?string $rewardRef): TestResponse
{
    return test()->postJson("/api/v1/play/sessions/{$sessionId}/second-chance", array_filter(['method' => 'ad', 'reward_ref' => $rewardRef]));
}

// ---------------------------------------------------------------------------------------------
// GET /webhooks/admob-ssv
// ---------------------------------------------------------------------------------------------

it('stores a reward from a correctly signed callback', function () {
    [$member, $sessionId] = adFailedSession();
    $ref = (string) Str::uuid();

    ssv(ssvQuery(['custom_data' => "{$ref}:{$sessionId}", 'user_id' => (string) $member->id, 'transaction_id' => 'tx-1']))
        ->assertOk()
        ->assertExactJson(['data' => ['transaction_id' => 'tx-1', 'outcome' => 'stored']]);

    $reward = AdReward::query()->sole();
    expect($reward->reward_ref)->toBe($ref)
        ->and($reward->member_id)->toBe($member->id)
        ->and($reward->play_session_id)->toBe($sessionId)
        ->and($reward->reward_amount)->toBe(1)
        ->and($reward->reward_item)->toBe('Second chance')
        ->and($reward->payload['custom_data'])->toBe("{$ref}:{$sessionId}")
        ->and($reward->used_at)->toBeNull();
});

it('rejects a bad, missing or unknown-key signature with 400', function () {
    [$member, $sessionId] = adFailedSession();
    $query = ssvQuery(['custom_data' => "ref-1:{$sessionId}", 'user_id' => (string) $member->id]);

    // Tampered content (the reward amount) with the original signature.
    ssv(str_replace('reward_amount=1', 'reward_amount=9', $query))->assertStatus(400)->assertJsonPath('code', 'ssv_signature_invalid');
    // Signed with another key.
    $otherKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    ssv(ssvQuery(['custom_data' => "ref-1:{$sessionId}", 'user_id' => (string) $member->id], $otherKey))->assertStatus(400);
    // No signature at all.
    ssv(Str::before($query, '&signature='))->assertStatus(400);
    // Garbage signature.
    ssv(Str::before($query, '&signature=').'&signature=not*base64&key_id='.SSV_KEY_ID)->assertStatus(400);
    // Unknown key id: the keys are refetched once, then 400.
    ssv(ssvQuery(['custom_data' => "ref-1:{$sessionId}", 'user_id' => (string) $member->id], keyId: '42'))->assertStatus(400);

    expect($this->keys->refreshes)->toBe(1)
        ->and(AdReward::query()->count())->toBe(0);
});

it('answers duplicates of the same transaction with 200 and stores them once', function () {
    [$member, $sessionId] = adFailedSession();
    $query = ssvQuery(['custom_data' => "ref-1:{$sessionId}", 'user_id' => (string) $member->id, 'transaction_id' => 'tx-dup']);

    ssv($query)->assertOk()->assertJsonPath('data.outcome', 'stored');
    ssv($query)->assertOk()->assertJsonPath('data.outcome', 'duplicate');

    expect(AdReward::query()->count())->toBe(1);
});

it('acknowledges signed callbacks without usable custom data without storing them', function (array $params) {
    [$member, $sessionId] = adFailedSession();
    $params = array_map(fn (string $value) => str_replace(['{session}', '{member}'], [(string) $sessionId, (string) $member->id], $value), $params);

    ssv(ssvQuery($params))->assertOk()->assertJsonPath('data.outcome', 'ignored');

    expect(AdReward::query()->count())->toBe(0);
})->with([
    'AdMob URL verification (no custom data)' => [[]],
    'custom data without a session' => [['custom_data' => 'ref-1', 'user_id' => '{member}']],
    'unknown session' => [['custom_data' => 'ref-1:999999', 'user_id' => '{member}']],
    'unknown member' => [['custom_data' => 'ref-1:{session}', 'user_id' => '999999']],
    'no user id' => [['custom_data' => 'ref-1:{session}']],
]);

it('verifies with Google\'s published keys, cached and refetched for an unknown key id', function () {
    app()->forgetInstance(AdMobVerifierKeys::class);
    app()->bind(AdMobVerifierKeys::class, HttpAdMobVerifierKeys::class);
    $pem = openssl_pkey_get_details($this->ecKey)['key'];
    Http::fake(['www.gstatic.com/admob/reward/verifier-keys.json' => Http::response(['keys' => [
        ['keyId' => (int) SSV_KEY_ID, 'pem' => $pem, 'base64' => 'ignored'],
    ]])]);
    [$member, $sessionId] = adFailedSession();

    ssv(ssvQuery(['custom_data' => "ref-1:{$sessionId}", 'user_id' => (string) $member->id]))->assertOk()->assertJsonPath('data.outcome', 'stored');
    ssv(ssvQuery(['custom_data' => "ref-2:{$sessionId}", 'user_id' => (string) $member->id]))->assertOk();
    Http::assertSentCount(1);

    ssv(ssvQuery(['custom_data' => "ref-3:{$sessionId}", 'user_id' => (string) $member->id], keyId: '7'))->assertStatus(400);
    Http::assertSentCount(2);
});

// ---------------------------------------------------------------------------------------------
// POST /play/sessions/{session}/second-chance with method "ad"
// ---------------------------------------------------------------------------------------------

it('grants an ad second chance once the SSV callback stored the reward, and only once', function () {
    [$member, $sessionId] = adFailedSession();
    $ref = (string) Str::uuid();

    // The callback has not arrived yet.
    adSecondChance($sessionId, $ref)->assertStatus(409)->assertJsonPath('code', 'ad_reward_pending');
    $this->getJson("/api/v1/play/sessions/{$sessionId}")->assertJsonPath('data.session.status', 'failed');

    ssv(ssvQuery(['custom_data' => "{$ref}:{$sessionId}", 'user_id' => (string) $member->id]))->assertOk();

    adSecondChance($sessionId, $ref)->assertOk()
        ->assertJsonPath('data.session.status', 'active')
        ->assertJsonPath('data.session.second_chances_used', 1);

    expect(AdReward::query()->sole()->used_at)->not->toBeNull()
        ->and(SecondChance::query()->where('method', 'ad')->sole()->reward_ref)->toBe($ref);

    // Failing again: the same reward can't be used twice.
    adFail($sessionId);
    adSecondChance($sessionId, $ref)->assertForbidden()->assertJsonPath('code', 'second_chance_unavailable');
});

it('refuses a reward stored for another member or another session', function () {
    [$member, $sessionId] = adFailedSession();
    $other = addMember($member->family, 30);
    $otherSession = test()->postJson('/api/v1/play/sessions', ['category_id' => Category::query()->value('id')])->assertCreated()->json('data.session.id');
    adFail($otherSession);
    // Starting the other session abandoned the first one; put it back to `failed` (both unfinished).
    PlaySession::query()->whereKey($sessionId)->update(['status' => PlaySession::STATUS_FAILED]);

    AdReward::factory()->create(['reward_ref' => 'foreign-member', 'member_id' => $other->id, 'play_session_id' => $sessionId]);
    AdReward::factory()->create(['reward_ref' => 'other-session', 'member_id' => $member->id, 'play_session_id' => $otherSession]);

    adSecondChance($sessionId, 'foreign-member')->assertForbidden()->assertJsonPath('code', 'second_chance_unavailable');
    adSecondChance($sessionId, 'other-session')->assertForbidden()->assertJsonPath('code', 'second_chance_unavailable');

    expect(AdReward::query()->whereNotNull('used_at')->count())->toBe(0)
        ->and(SecondChance::query()->count())->toBe(0);
});

it('needs a reward_ref for an ad second chance', function () {
    [, $sessionId] = adFailedSession();

    adSecondChance($sessionId, null)->assertUnprocessable()->assertJsonPath('code', 'validation_failed')->assertJsonValidationErrors('reward_ref');
});

it('keeps the policy check: no ad second chance for children or the Family tier, even with a reward', function (string $tier, int $age) {
    [$member, $sessionId] = adFailedSession($tier, $age);
    AdReward::factory()->create(['reward_ref' => 'ref-ok', 'member_id' => $member->id, 'play_session_id' => $sessionId]);

    adSecondChance($sessionId, 'ref-ok')->assertForbidden()->assertJsonPath('code', 'second_chance_unavailable');

    expect(AdReward::query()->sole()->used_at)->toBeNull();
})->with([
    'child on the free tier' => ['free', 9],
    'adult on the Family tier' => ['family', 40],
]);

it('sends the ad request configuration in the policy', function () {
    $owner = familyOwner();
    actingAsMember(addMember($owner->family, 15));

    $this->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.policy.second_chance.ad_allowed', true)
        ->assertJsonPath('data.policy.ad_request', ['non_personalized' => true, 'under_age_of_consent' => true, 'max_ad_content_rating' => 'G']);
});

it('answers 400 (not 500) when Google\'s keys cannot be fetched', function () {
    app()->forgetInstance(AdMobVerifierKeys::class);
    app()->bind(AdMobVerifierKeys::class, HttpAdMobVerifierKeys::class);
    Http::fake(['www.gstatic.com/*' => Http::failedConnection('SSL certificate problem')]);
    [$member, $sessionId] = adFailedSession();

    ssv(ssvQuery(['custom_data' => "ref-1:{$sessionId}", 'user_id' => (string) $member->id]))
        ->assertStatus(400)->assertJsonPath('code', 'ssv_signature_invalid');

    // Failures are not cached: the next callback tries again.
    expect(cache()->has(HttpAdMobVerifierKeys::CACHE_KEY))->toBeFalse();
});

it('counts rejected SSV callbacks per reason and day for the dashboard, without storing payloads', function () {
    [$member, $sessionId] = adFailedSession();
    $hits = fn (string $reason): int => (int) DB::table('webhook_rejections')
        ->where(['provider' => WebhookRejections::PROVIDER_ADMOB, 'reason' => $reason, 'day' => now()->utc()->toDateString()])
        ->value('hits');

    $query = ssvQuery(['custom_data' => "ref-1:{$sessionId}", 'user_id' => (string) $member->id]);
    ssv(str_replace('reward_amount=1', 'reward_amount=9', $query))->assertStatus(400);
    ssv(Str::before($query, '&signature='))->assertStatus(400);
    ssv(ssvQuery(['custom_data' => 'ref-1:999999', 'user_id' => (string) $member->id]))->assertOk()->assertJsonPath('data.outcome', 'ignored');
    // AdMob's "Verify URL" test (no custom data) and stored rewards are not rejections.
    ssv(ssvQuery([]))->assertOk()->assertJsonPath('data.outcome', 'ignored');
    ssv($query)->assertOk()->assertJsonPath('data.outcome', 'stored');

    expect($hits(WebhookRejections::REASON_SIGNATURE_INVALID))->toBe(2)
        ->and($hits(WebhookRejections::REASON_INVALID_PAYLOAD))->toBe(1)
        ->and(DB::table('webhook_rejections')->count())->toBe(2);
});
