<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Identity\Models\SocialAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Social\AppleIdTokenVerifier;
use App\Modules\Identity\Social\SocialIdentity;
use App\Modules\Identity\Social\SocialTokenVerifiers;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * `POST /auth/social/{provider}`: verifies the provider token, then
 * 1. a linked account → sign in;
 * 2. no link, but a user with the same provider-verified email → link and sign in;
 * 3. otherwise create an adult user (birth data required, age checked like `/auth/register`,
 *    `accept_terms` required and recorded as terms + privacy consents);
 *    its email is the provider-verified one, or null when the provider shares none.
 * Returns a device-bound Sanctum token like `/auth/login`. Adults only.
 */
class SignInWithSocial
{
    public function __construct(
        private readonly SocialTokenVerifiers $verifiers,
        private readonly PolicyResolver $policy,
        private readonly EnsureSocialSignInAllowed $ensureAllowed,
        private readonly IssueDeviceToken $issueToken,
        private readonly StoreAppleRefreshToken $storeAppleToken,
        private readonly AcceptLegalTerms $acceptLegalTerms,
    ) {}

    /**
     * @param  array{birth_year?: int|null, birth_month?: int|null, display_name?: string|null, locale?: string|null, accept_terms?: bool}  $profile
     * @return array{user: User, token: string, created: bool}
     */
    public function handle(string $provider, string $token, array $profile, string $deviceName, string $platform, ?string $appVersion = null, ?string $nonce = null, ?string $authorizationCode = null, ?string $ipAddress = null): array
    {
        $identity = $this->verifiers->for($provider)->verify($token, $nonce);

        $result = DB::transaction(function () use ($identity, $profile, $deviceName, $platform, $appVersion, $ipAddress): array {
            $created = false;
            $account = SocialAccount::query()
                ->with('user')
                ->where('provider', $identity->provider)
                ->where('provider_user_id', $identity->providerUserId)
                ->first();

            if ($account !== null) {
                $user = $account->user;

                if ($identity->email !== null && $account->email !== $identity->email) {
                    $account->forceFill(['email' => $identity->email])->save();
                }
            } elseif ($identity->email !== null && ($existing = User::query()->where('email', $identity->email)->first()) !== null) {
                $this->ensureAllowed->handle($existing);
                $this->link($existing, $identity);
                $user = $existing;
            } else {
                $user = $this->createUser($identity, $profile, $ipAddress);
                $created = true;
            }

            return [
                'user' => $user,
                'token' => $this->issueToken->handle($user, $deviceName, $platform, $appVersion),
                'created' => $created,
            ];
        });

        if ($authorizationCode !== null) {
            $account = SocialAccount::query()->where('user_id', $result['user']->id)->where('provider', $identity->provider)->first();

            if ($account !== null) {
                $this->storeAppleToken->handle($account, $authorizationCode, $identity->audience);
            }
        }

        return $result;
    }

    /**
     * @param  array{birth_year?: int|null, birth_month?: int|null, display_name?: string|null, locale?: string|null, accept_terms?: bool}  $profile
     */
    private function createUser(SocialIdentity $identity, array $profile, ?string $ipAddress): User
    {
        $birthYear = $profile['birth_year'] ?? null;
        $birthMonth = $profile['birth_month'] ?? null;

        if ($birthYear === null || $birthMonth === null) {
            throw ApiException::socialSignupIncomplete();
        }

        $band = $this->policy->bandForBirth((int) $birthYear, (int) $birthMonth);

        if (! $this->policy->collectsEmail($band)) {
            throw ApiException::validation('birth_year', 'Only adults can create an account. Ask a parent to invite you to the family.');
        }

        if (($profile['accept_terms'] ?? false) !== true) {
            throw ApiException::validation('accept_terms', 'Please accept the terms of service and the privacy policy to create your account.');
        }

        $displayName = $profile['display_name'] ?? null;
        // An Apple private relay address has a random local part: no use as a name.
        $emailName = $identity->email !== null && ! AppleIdTokenVerifier::isPrivateRelay($identity->email) ? Str::before($identity->email, '@') : null;
        $displayName = collect([$displayName, $identity->name, $emailName, 'Player'])
            ->first(fn ($value) => is_string($value) && trim($value) !== '');

        $user = User::query()->create([
            'display_name' => Str::limit(trim((string) $displayName), 60, ''),
            'email' => $identity->email,
            'password' => null,
            'birth_year' => (int) $birthYear,
            'birth_month' => (int) $birthMonth,
            'locale' => $profile['locale'] ?? 'en',
        ]);

        $this->link($user, $identity);
        $this->acceptLegalTerms->handle($user, $ipAddress);

        return $user;
    }

    /**
     * One account per provider per user: a different account of the same provider replaces the old link.
     */
    private function link(User $user, SocialIdentity $identity): SocialAccount
    {
        return SocialAccount::query()->updateOrCreate(
            ['user_id' => $user->id, 'provider' => $identity->provider],
            ['provider_user_id' => $identity->providerUserId, 'email' => $identity->email, 'linked_at' => now()],
        );
    }
}
