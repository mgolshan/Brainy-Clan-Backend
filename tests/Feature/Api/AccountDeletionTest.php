<?php

use App\Modules\Billing\Models\Subscription;
use App\Modules\Content\Models\Category;
use App\Modules\Family\Actions\IssueSignInCode;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\Invite;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Apple\AppleTokenClient;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\SocialAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Social\SocialIdentity;
use App\Modules\Identity\Social\SocialTokenVerifier;
use App\Modules\Identity\Social\SocialTokenVerifiers;
use App\Modules\Scoring\Actions\AppendPoints;
use App\Modules\Scoring\Models\PointEntry;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Account deletion (docs/API.md "Account deletion"): GET /me/deletion-check, DELETE /me,
 * DELETE /family, child erasure on member removal, Apple token exchange + revocation.
 */
function adFakeAppleTokens(): object
{
    $fake = new class implements AppleTokenClient
    {
        /** @var list<array{string, string}> */
        public array $exchanged = [];

        /** @var list<array{string, string}> */
        public array $revoked = [];

        public function exchangeCode(string $authorizationCode, string $clientId): ?string
        {
            $this->exchanged[] = [$authorizationCode, $clientId];

            return 'refresh-for-'.$authorizationCode;
        }

        public function revoke(string $refreshToken, string $clientId): void
        {
            $this->revoked[] = [$refreshToken, $clientId];
        }
    };

    app()->instance(AppleTokenClient::class, $fake);

    return $fake;
}

function adBearer(User $user): string
{
    return app(IssueDeviceToken::class)->handle($user, 'Phone', 'ios');
}

it('reports what deleting my account means', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    $adult = addMember($owner->family, 38);
    Subscription::factory()->create(['family_id' => $owner->family_id]);

    actingAsMember($owner);
    $this->getJson('/api/v1/me/deletion-check')->assertOk()
        ->assertJsonPath('data.can_delete', false)
        ->assertJsonPath('data.blockers', ['family_owner'])
        ->assertJsonPath('data.active_subscription', true)
        ->assertJsonPath('data.store', 'app_store');

    app('auth')->forgetGuards();
    actingAsMember($adult);
    $this->getJson('/api/v1/me/deletion-check')->assertOk()
        ->assertJsonPath('data.can_delete', true)
        ->assertJsonPath('data.deletes_family', false);

    app('auth')->forgetGuards();
    actingAsMember($kid);
    $this->getJson('/api/v1/me/deletion-check')->assertOk()
        ->assertJsonPath('data.can_delete', false)
        ->assertJsonPath('data.blockers', ['child_account']);
});

it('lets an adult member delete their account and keeps the ledger', function () {
    $owner = familyOwner();
    $adult = addMember($owner->family, 38);
    $user = $adult->user;
    $email = $user->email;
    $token = adBearer($user);
    $category = Category::factory()->create();
    app(AppendPoints::class)->handle($owner->family_id, $adult->id, $category->id, 5, 'test');
    SocialAccount::factory()->create(['user_id' => $user->id, 'provider' => SocialAccount::GOOGLE]);
    Invite::factory()->create(['family_id' => $owner->family_id, 'created_by' => $user->id]);

    $this->deleteJson('/api/v1/me', [], ['Authorization' => "Bearer {$token}"])->assertUnprocessable();
    $this->deleteJson('/api/v1/me', ['confirm' => 'DELETE'], ['Authorization' => "Bearer {$token}"])->assertNoContent();

    $user->refresh();
    expect($user->display_name)->toBe('Deleted player')
        ->and($user->email)->toBeNull()
        ->and($user->password)->toBeNull()
        ->and($user->birth_year)->toBeNull()
        ->and($user->erased_at)->not->toBeNull()
        ->and($adult->refresh()->status)->toBe(FamilyMember::STATUS_REMOVED)
        ->and($adult->nickname)->toBe('Deleted player')
        ->and(PointEntry::query()->where('member_id', $adult->id)->sum('amount'))->toEqual(5)
        ->and(SocialAccount::query()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(Device::query()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(PersonalAccessToken::query()->count())->toBe(0)
        ->and(Invite::query()->where('created_by', $user->id)->exists())->toBeFalse();

    expect(DB::table('activity_log')->where('log_name', 'account')->where('event', 'erased')->exists())->toBeTrue()
        ->and(DB::table('activity_log')->where('properties', 'like', '%'.$email.'%')->exists())->toBeFalse();

    // The old token is gone and the email login no longer exists.
    app('auth')->forgetGuards();
    $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
    $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'password', 'device_name' => 'x', 'platform' => 'ios'])
        ->assertUnauthorized();
});

it('blocks the owner of a family with other members', function () {
    $owner = familyOwner();
    addMember($owner->family, 9);
    actingAsMember($owner);

    $this->deleteJson('/api/v1/me', ['confirm' => 'DELETE'])->assertStatus(409)->assertJsonPath('code', 'family_owner_cannot_leave');
    expect($owner->user->refresh()->erased_at)->toBeNull();
});

it('forbids children deleting themselves', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    actingAsMember($kid);

    $this->deleteJson('/api/v1/me', ['confirm' => 'DELETE'])->assertForbidden();
});

it('deletes the family with the account of its only member', function () {
    $owner = familyOwner();
    GoalPlan::factory()->create(['family_id' => $owner->family_id]);
    actingAsMember($owner);

    $this->getJson('/api/v1/me/deletion-check')->assertJsonPath('data.deletes_family', true);
    $this->deleteJson('/api/v1/me', ['confirm' => 'DELETE'])->assertNoContent();

    $family = $owner->family->refresh();
    expect($family->deleted_at)->not->toBeNull()
        ->and($family->name)->toBe('Deleted family')
        ->and($owner->refresh()->status)->toBe(FamilyMember::STATUS_REMOVED)
        ->and($owner->user->refresh()->erased_at)->not->toBeNull()
        ->and(GoalPlan::query()->where('family_id', $family->id)->count())->toBe(0);
});

it('deletes a family: children erased, adults detached, owner kept unless asked', function () {
    $owner = familyOwner(['name' => 'The Golshans']);
    $kid = addMember($owner->family, 9);
    $adult = addMember($owner->family, 38);
    $kidToken = adBearer($kid->user);
    ['code' => $code] = app(IssueSignInCode::class)->handle($kid);
    Subscription::factory()->create(['family_id' => $owner->family_id]);
    actingAsMember($owner);

    $this->deleteJson('/api/v1/family', ['confirm' => 'wrong'])->assertUnprocessable();
    $this->deleteJson('/api/v1/family', ['confirm' => 'The Golshans'])->assertNoContent();

    expect($kid->user->refresh()->erased_at)->not->toBeNull()
        ->and($adult->user->refresh()->erased_at)->toBeNull()
        ->and($adult->refresh()->status)->toBe(FamilyMember::STATUS_REMOVED)
        ->and($owner->user->refresh()->erased_at)->toBeNull()
        ->and(Subscription::query()->where('family_id', $owner->family_id)->exists())->toBeTrue();

    app('auth')->forgetGuards();
    $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$kidToken}"])->assertUnauthorized();
    $this->postJson('/api/v1/auth/code', ['code' => $code, 'device_name' => 'iPad', 'platform' => 'ios'])
        ->assertUnprocessable()->assertJsonPath('code', 'sign_in_code_invalid');
});

it('only lets the owner delete the family', function () {
    $owner = familyOwner(['name' => 'Fam']);
    $admin = addMember($owner->family, 38, FamilyMember::ROLE_ADMIN);
    actingAsMember($admin);

    $this->deleteJson('/api/v1/family', ['confirm' => 'Fam'])->assertForbidden();
});

it('erases the owner too with delete_my_account', function () {
    $owner = familyOwner(['name' => 'Fam']);
    addMember($owner->family, 9);
    actingAsMember($owner);

    $this->deleteJson('/api/v1/family', ['confirm' => 'Fam', 'delete_my_account' => true])->assertNoContent();
    expect($owner->user->refresh()->erased_at)->not->toBeNull();
});

it('erases a child removed by an admin', function () {
    $owner = familyOwner();
    $kid = addMember($owner->family, 9);
    actingAsMember($owner);

    $this->deleteJson("/api/v1/family/members/{$kid->id}")->assertNoContent();
    expect($kid->user->refresh()->display_name)->toBe('Deleted player')
        ->and($kid->user->erased_at)->not->toBeNull();
});

it('stores the Apple refresh token and revokes it on deletion', function () {
    $apple = adFakeAppleTokens();
    adFakeApple(new SocialIdentity(SocialAccount::APPLE, 'a-1', 'x@privaterelay.appleid.com', null, 'app.brainyclan'));

    $response = $this->postJson('/api/v1/auth/social/apple', adPayload(['birth_year' => 1985, 'birth_month' => 2, 'authorization_code' => 'code-1']))
        ->assertCreated();

    $account = SocialAccount::query()->sole();
    expect($apple->exchanged)->toBe([['code-1', 'app.brainyclan']])
        ->and($account->refresh_token)->toBe('refresh-for-code-1')
        ->and(DB::table('social_accounts')->value('refresh_token'))->not->toBe('refresh-for-code-1');

    $token = $response->json('data.token');
    $this->deleteJson('/api/v1/me', ['confirm' => 'DELETE'], ['Authorization' => "Bearer {$token}"])->assertNoContent();

    expect($apple->revoked)->toBe([['refresh-for-code-1', 'app.brainyclan']]);

    // Signing in with the same Apple account again creates a new user, never the erased one.
    $this->postJson('/api/v1/auth/social/apple', adPayload(['birth_year' => 1985, 'birth_month' => 2]))->assertCreated();
    expect(SocialAccount::query()->sole()->user_id)->not->toBe($account->user_id);
});

it('revokes the Apple token on unlink', function () {
    $apple = adFakeAppleTokens();
    $user = User::factory()->aged(40)->create();
    SocialAccount::factory()->create(['user_id' => $user->id, 'provider' => SocialAccount::APPLE, 'refresh_token' => 'rt-9', 'token_client_id' => 'app.brainyclan']);
    actingAsMember(FamilyMember::factory()->create(['user_id' => $user->id]));

    $this->deleteJson('/api/v1/me/social-accounts/apple')->assertNoContent();
    expect($apple->revoked)->toBe([['rt-9', 'app.brainyclan']]);
});

it('deletes a big family with a bounded number of queries', function () {
    $count = function (int $kids): int {
        $owner = familyOwner(['name' => 'Fam'.$kids]);

        foreach (range(1, $kids) as $i) {
            $kid = addMember($owner->family, 9);
            adBearer($kid->user);
        }
        addMember($owner->family, 38);

        app('auth')->forgetGuards();
        actingAsMember($owner);
        DB::flushQueryLog();
        DB::enableQueryLog();
        test()->deleteJson('/api/v1/family', ['confirm' => 'Fam'.$kids])->assertNoContent();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    expect($count(8))->toBe($count(2));
});

function adFakeApple(SocialIdentity $identity): void
{
    app()->instance(SocialTokenVerifiers::VERIFIERS[SocialAccount::APPLE], new class($identity) implements SocialTokenVerifier
    {
        public function __construct(private readonly SocialIdentity $identity) {}

        public function verify(string $token, ?string $nonce = null): SocialIdentity
        {
            return $this->identity;
        }
    });
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function adPayload(array $overrides = []): array
{
    return ['token' => 'provider-token', 'device_name' => 'iPhone', 'platform' => 'ios', 'accept_terms' => true, ...$overrides];
}
