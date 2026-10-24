<?php

use App\Modules\Content\Models\Category;
use App\Modules\Family\Models\Invite;
use App\Modules\Goals\Models\GoalPlan;
use App\Modules\Identity\Actions\RecordConsent;
use App\Modules\Identity\Models\Consent;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\SocialAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Scoring\Models\PointEntry;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Legal, consent and privacy (docs/API.md "Legal, consent and privacy (B9)").
 */
function acceptCurrent(User $user): void
{
    Consent::factory()->create(['user_id' => $user->id]);
    Consent::factory()->privacy()->create(['user_id' => $user->id]);
}

describe('GET /legal and the pages', function () {
    it('returns the current versions, page URLs and summaries', function () {
        $this->getJson('/api/v1/legal')
            ->assertOk()
            ->assertJsonPath('data.terms.version', config('legal.documents.terms.version'))
            ->assertJsonPath('data.terms.url', url('/terms'))
            ->assertJsonPath('data.terms.summary', 'First version of the Brainy Clan terms of service.')
            ->assertJsonPath('data.privacy.version', config('legal.documents.privacy.version'))
            ->assertJsonPath('data.privacy.url', url('/privacy'));
    });

    it('localizes summaries and URLs with ?lang=fr and falls back to English otherwise', function () {
        $this->getJson('/api/v1/legal?lang=fr')
            ->assertOk()
            ->assertJsonPath('data.privacy.url', url('/privacy?lang=fr'))
            ->assertJsonPath('data.privacy.summary', config('legal.documents.privacy.summary.fr'));

        $this->getJson('/api/v1/legal?lang=de')->assertOk()->assertJsonPath('data.terms.url', url('/terms'));
    });

    it('returns a null summary when none is configured', function () {
        config()->set('legal.documents.terms.summary', []);

        $this->getJson('/api/v1/legal')->assertOk()->assertJsonPath('data.terms.summary', null);
    });

    it('serves the terms and privacy pages in English and French with the draft banner and strict headers', function (string $path, string $lang, string $title) {
        $response = $this->get($path.($lang === 'fr' ? '?lang=fr' : ''))
            ->assertOk()
            ->assertSee($title, false)
            ->assertSee($lang === 'fr' ? 'Ébauche — en cours de révision juridique' : 'Draft — under legal review', false)
            ->assertSee('name="viewport"', false)
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        expect($response->headers->get('Content-Security-Policy'))->toContain("default-src 'none'")->toContain("frame-ancestors 'none'")
            ->and($response->headers->getCookies())->toBe([]);
    })->with([
        ['/terms', 'en', 'Terms of Service'],
        ['/terms', 'fr', 'Conditions d’utilisation'],
        ['/privacy', 'en', 'Privacy Policy'],
        ['/privacy', 'fr', 'Politique de confidentialité'],
    ]);

    it('hides the draft banner once LEGAL_DRAFT is off', function () {
        config()->set('legal.draft', false);

        $this->get('/privacy')->assertOk()->assertDontSee('Draft — under legal review');
    });
});

describe('accept_terms on sign-ups', function () {
    it('requires accept_terms on register and records terms + privacy consents with a hashed IP', function () {
        $payload = ['display_name' => 'Moe', 'email' => 'moe@example.com', 'password' => 'secret123', 'birth_year' => 1985, 'birth_month' => 6, 'device_name' => 'Pixel', 'platform' => 'android'];

        $this->postJson('/api/v1/auth/register', $payload)->assertStatus(422)->assertJsonValidationErrors('accept_terms');
        $this->postJson('/api/v1/auth/register', [...$payload, 'accept_terms' => false])->assertStatus(422)->assertJsonValidationErrors('accept_terms');
        expect(User::query()->count())->toBe(0);

        $this->postJson('/api/v1/auth/register', [...$payload, 'accept_terms' => true], ['REMOTE_ADDR' => '203.0.113.7'])
            ->assertCreated()
            ->assertJsonPath('data.legal.needs_acceptance', false)
            ->assertJsonPath('data.legal.accepted_terms_version', config('legal.documents.terms.version'));

        $consents = Consent::query()->orderBy('id')->get();
        expect($consents->pluck('type')->all())->toBe(['terms', 'privacy'])
            ->and($consents->first()->ip_hash)->toBe(RecordConsent::hashIp('203.0.113.7'))
            ->and($consents->first()->ip_hash)->not->toContain('203.0.113.7');
    });

    it('requires accept_terms when an adult joins by invite with an email, not without', function () {
        Invite::factory()->adult()->create(['code' => 'DADS2345']);
        $join = ['code' => 'DADS2345', 'device_name' => 'Phone', 'platform' => 'android', 'email' => 'dad@example.com', 'password' => 'secret123'];

        $this->postJson('/api/v1/auth/join', $join)->assertStatus(422)->assertJsonValidationErrors('accept_terms');
        $this->postJson('/api/v1/auth/join', [...$join, 'accept_terms' => true])->assertCreated()->assertJsonPath('data.legal.needs_acceptance', false);
        expect(Consent::query()->orderBy('id')->pluck('type')->all())->toBe(['terms', 'privacy']);

        // Without an email (sign-in codes only) nothing is required now; the app asks via me.legal.
        Invite::factory()->adult()->create(['code' => 'GRAN2345']);
        $this->postJson('/api/v1/auth/join', ['code' => 'GRAN2345', 'device_name' => 'Tablet', 'platform' => 'ios'])
            ->assertCreated()
            ->assertJsonPath('data.legal.needs_acceptance', true);
    });
});

describe('parental consent on child invites', function () {
    it('previews the age band of an invitee (admin only)', function () {
        $owner = familyOwner();
        actingAsMember($owner);

        $this->getJson('/api/v1/family/invites/preview?birth_year='.(now()->year - 8).'&birth_month=1')
            ->assertOk()
            ->assertExactJson(['data' => ['age_band' => 'child', 'is_child' => true, 'parental_consent_required' => true, 'email_login_allowed' => false]]);

        $this->getJson('/api/v1/family/invites/preview?birth_year='.(now()->year - 15).'&birth_month=1')
            ->assertOk()
            ->assertJsonPath('data.age_band', 'teen')
            ->assertJsonPath('data.parental_consent_required', false)
            ->assertJsonPath('data.email_login_allowed', true);

        $this->getJson('/api/v1/family/invites/preview?birth_year=1800&birth_month=13')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['birth_year', 'birth_month']);

        actingAsMember(addMember($owner->family, 40));
        $this->getJson('/api/v1/family/invites/preview?birth_year=2018&birth_month=1')->assertForbidden();
    });

    it('requires parental_consent for a child invite and records it, then links it to the member on join', function () {
        $owner = familyOwner(['tier' => 'family']);
        actingAsMember($owner);
        $child = ['nickname' => 'Robin', 'birth_year' => now()->year - 7, 'birth_month' => 1];

        $this->postJson('/api/v1/family/invites', $child)->assertStatus(422)->assertJsonValidationErrors('parental_consent');
        $this->postJson('/api/v1/family/invites', [...$child, 'parental_consent' => false])->assertStatus(422)->assertJsonValidationErrors('parental_consent');
        expect(Invite::query()->count())->toBe(0);

        $code = $this->postJson('/api/v1/family/invites', [...$child, 'parental_consent' => true])->assertCreated()->json('data.code');

        $consent = Consent::query()->sole();
        expect($consent->type)->toBe(Consent::PARENTAL)
            ->and($consent->user_id)->toBe($owner->user_id)
            ->and($consent->version)->toBe(config('legal.documents.privacy.version'))
            ->and($consent->subject_invite_id)->toBe(Invite::query()->sole()->id)
            ->and($consent->subject_member_id)->toBeNull();

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/join', ['code' => $code, 'device_name' => 'Tablet', 'platform' => 'ios'])
            ->assertCreated()
            ->assertJsonPath('data.legal.needs_acceptance', false);

        expect($consent->refresh()->subject_member_id)->not->toBeNull()
            ->and($consent->subjectMember->nickname)->toBe('Robin');
    });

    it('does not ask parental consent for a teen or adult invite', function () {
        $owner = familyOwner(['tier' => 'family']);
        actingAsMember($owner);

        $this->postJson('/api/v1/family/invites', ['nickname' => 'Jordan', 'birth_year' => now()->year - 15, 'birth_month' => 1])->assertCreated();
        $this->postJson('/api/v1/family/invites', ['nickname' => 'Gran', 'birth_year' => 1950, 'birth_month' => 1])->assertCreated();

        expect(Consent::query()->count())->toBe(0);
    });
});

describe('me.legal and POST /me/legal-acceptance', function () {
    it('asks adults and teens to accept until they accepted the current versions; never children', function () {
        $owner = familyOwner();
        $teen = addMember($owner->family, 15);
        $child = addMember($owner->family, 9);

        actingAsMember($owner);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.legal', [
            'needs_acceptance' => true,
            'terms_version' => config('legal.documents.terms.version'),
            'privacy_version' => config('legal.documents.privacy.version'),
            'accepted_terms_version' => null,
            'accepted_privacy_version' => null,
        ]);

        acceptCurrent($owner->user);
        $this->getJson('/api/v1/me')->assertJsonPath('data.legal.needs_acceptance', false);

        actingAsMember($teen);
        $this->getJson('/api/v1/me')->assertJsonPath('data.legal.needs_acceptance', true);

        actingAsMember($child);
        $this->getJson('/api/v1/me')->assertJsonPath('data.legal.needs_acceptance', false);
    });

    it('asks again after a version bump and accepts only the current versions', function () {
        $owner = familyOwner();
        acceptCurrent($owner->user);
        actingAsMember($owner);

        $acceptedPrivacy = config('legal.documents.privacy.version');
        config()->set('legal.documents.privacy.version', '2027-01-01');
        $this->getJson('/api/v1/me')
            ->assertJsonPath('data.legal.needs_acceptance', true)
            ->assertJsonPath('data.legal.privacy_version', '2027-01-01')
            ->assertJsonPath('data.legal.accepted_privacy_version', $acceptedPrivacy);

        $terms = config('legal.documents.terms.version');
        $this->postJson('/api/v1/me/legal-acceptance', ['terms_version' => $terms, 'privacy_version' => $acceptedPrivacy])
            ->assertStatus(422)->assertJsonValidationErrors('privacy_version');
        $this->postJson('/api/v1/me/legal-acceptance', ['terms_version' => 'old', 'privacy_version' => '2027-01-01'])
            ->assertStatus(422)->assertJsonValidationErrors('terms_version');
        $this->postJson('/api/v1/me/legal-acceptance', [])->assertStatus(422)->assertJsonValidationErrors(['terms_version', 'privacy_version']);

        $this->postJson('/api/v1/me/legal-acceptance', ['terms_version' => $terms, 'privacy_version' => '2027-01-01'])->assertNoContent();
        $this->getJson('/api/v1/me')
            ->assertJsonPath('data.legal.needs_acceptance', false)
            ->assertJsonPath('data.legal.accepted_privacy_version', '2027-01-01');
        expect(Consent::query()->where('user_id', $owner->user_id)->count())->toBe(4);
    });

    it('refuses legal acceptance for children and needs auth', function () {
        $this->postJson('/api/v1/me/legal-acceptance', ['terms_version' => 'x', 'privacy_version' => 'y'])->assertUnauthorized();

        $owner = familyOwner();
        actingAsMember(addMember($owner->family, 9));

        $this->postJson('/api/v1/me/legal-acceptance', [
            'terms_version' => config('legal.documents.terms.version'),
            'privacy_version' => config('legal.documents.privacy.version'),
        ])->assertForbidden()->assertJsonPath('code', 'forbidden');
        expect(Consent::query()->count())->toBe(0);
    });
});

describe('GET /me/export', function () {
    it('exports the user data without secrets', function () {
        $owner = familyOwner();
        acceptCurrent($owner->user);
        Device::factory()->create(['user_id' => $owner->user_id, 'push_token' => 'ExponentPushToken[secret]']);
        SocialAccount::factory()->create(['user_id' => $owner->user_id, 'provider' => 'apple', 'refresh_token' => 'apple-secret']);
        $plan = GoalPlan::factory()->create(['family_id' => $owner->family_id, 'name' => 'Pizza week']);
        $plan->planMembers()->create(['member_id' => $owner->id]);
        $category = Category::factory()->create();
        PointEntry::query()->create(['family_id' => $owner->family_id, 'member_id' => $owner->id, 'category_id' => $category->id, 'amount' => 3, 'reason' => PointEntry::REASON_CORRECT_ANSWER, 'created_at' => now()]);
        actingAsMember($owner);

        $response = $this->getJson('/api/v1/me/export')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.user.email', $owner->user->email)
            ->assertJsonPath('data.memberships.0.family.id', $owner->family_id)
            ->assertJsonPath('data.point_entries.0.amount', 3)
            ->assertJsonPath('data.goals.0.name', 'Pizza week')
            ->assertJsonPath('data.consents.0.type', 'terms')
            ->assertJsonPath('data.devices.0.has_push_token', true)
            ->assertJsonPath('data.social_accounts.0.provider', 'apple')
            ->assertJsonStructure(['data' => ['exported_at', 'user', 'memberships', 'point_entries', 'sessions', 'goals', 'consents', 'devices', 'social_accounts']]);

        expect($response->headers->get('Content-Disposition'))->toContain('attachment; filename="brainy-clan-export-');

        $body = $response->getContent();
        expect($body)->not->toContain('ExponentPushToken')
            ->not->toContain('apple-secret')
            ->not->toContain('ip_hash')
            ->not->toContain('"password"')
            ->not->toContain((string) DB::table('users')->where('id', $owner->user_id)->value('password'));
    });

    it('is limited to one export per hour', function () {
        actingAsMember(familyOwner());

        $this->getJson('/api/v1/me/export')->assertOk();
        $this->getJson('/api/v1/me/export')->assertStatus(429)->assertJsonPath('code', 'rate_limited');

        $this->travel(61)->minutes();
        $this->getJson('/api/v1/me/export')->assertOk();
    });

    it('refuses children and guests', function () {
        $this->getJson('/api/v1/me/export')->assertUnauthorized();

        $owner = familyOwner();
        actingAsMember(addMember($owner->family, 9));
        $this->getJson('/api/v1/me/export')->assertForbidden()->assertJsonPath('code', 'forbidden');
    });

    it('builds the export with a constant number of queries', function () {
        $count = function (int $rows): int {
            $owner = familyOwner();
            $category = Category::factory()->create();

            for ($i = 0; $i < $rows; $i++) {
                acceptCurrent($owner->user);
                Device::factory()->create(['user_id' => $owner->user_id]);
                PointEntry::query()->create(['family_id' => $owner->family_id, 'member_id' => $owner->id, 'category_id' => $category->id, 'amount' => 1, 'reason' => PointEntry::REASON_CORRECT_ANSWER, 'created_at' => now()]);
                GoalPlan::factory()->create(['family_id' => $owner->family_id])->planMembers()->create(['member_id' => $owner->id]);
            }

            Sanctum::actingAs($owner->user->fresh());
            DB::flushQueryLog();
            DB::enableQueryLog();
            test()->getJson('/api/v1/me/export')->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $count(1); // warms the policy cache

        expect($count(1))->toBe($count(5));
    });
});
