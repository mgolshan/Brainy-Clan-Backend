<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\Invite;
use App\Modules\Identity\Actions\AcceptLegalTerms;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Lockout\LoginLockout;
use App\Modules\Identity\Models\Consent;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Support\Facades\DB;

/**
 * Join a family with an invite code on a device. Creates the user from the invite
 * (nickname, birth data), the membership and a device-bound token. Email + password are
 * only accepted when the invitee's age band allows collecting them (adults), and then only with
 * `accept_terms` (recorded as terms + privacy consents). A parental consent given for the invite is
 * linked to the new member.
 */
class JoinFamilyWithInvite
{
    public function __construct(
        private readonly PolicyResolver $policy,
        private readonly IssueDeviceToken $issueToken,
        private readonly AcceptLegalTerms $acceptLegalTerms,
        private readonly LoginLockout $lockout,
    ) {}

    /**
     * Lockout (docs/API.md Auth → "Lockout"): a locked IP gets 429 `login_locked` before the code
     * is looked up; unknown, used or expired codes count towards the IP's locks, a valid one
     * resets the code count.
     *
     * @return array{user: User, token: string}
     */
    public function handle(
        string $code,
        string $deviceName,
        string $platform,
        ?string $email = null,
        ?string $password = null,
        ?string $appVersion = null,
        bool $acceptedTerms = false,
        ?string $ipAddress = null,
    ): array {
        $this->lockout->ensureCodeAllowed($ipAddress);

        try {
            $result = $this->join($code, $deviceName, $platform, $email, $password, $appVersion, $acceptedTerms, $ipAddress);
        } catch (ApiException $e) {
            if ($e->errorCode === 'invite_invalid' && ($retryAfter = $this->lockout->recordCodeFailure($ipAddress)) > 0) {
                throw ApiException::loginLocked($retryAfter);
            }

            throw $e;
        }

        $this->lockout->recordCodeSuccess($ipAddress);

        return $result;
    }

    /**
     * @return array{user: User, token: string}
     */
    private function join(
        string $code,
        string $deviceName,
        string $platform,
        ?string $email,
        ?string $password,
        ?string $appVersion,
        bool $acceptedTerms,
        ?string $ipAddress,
    ): array {
        return DB::transaction(function () use ($code, $deviceName, $platform, $email, $password, $appVersion, $acceptedTerms, $ipAddress): array {
            $invite = Invite::query()
                ->where('code', Invite::normalizeCode($code))
                ->lockForUpdate()
                ->first();

            if ($invite === null || ! $invite->isPending()) {
                throw ApiException::inviteInvalid();
            }

            $band = $this->policy->bandForBirth($invite->birth_year, $invite->birth_month, $invite->family->country_code);
            $collectEmail = $this->policy->collectsEmail($band);

            if (! $collectEmail && ($email !== null || $password !== null)) {
                throw ApiException::validation('email', 'Email and password are not collected for this member.');
            }

            if ($email !== null && User::query()->where('email', strtolower($email))->exists()) {
                throw ApiException::validation('email', 'The email has already been taken.');
            }

            if ($email !== null && ! $acceptedTerms) {
                throw ApiException::validation('accept_terms', 'Please accept the terms of service and the privacy policy to create your login.');
            }

            $user = User::query()->create([
                'display_name' => $invite->nickname,
                'avatar' => $invite->avatar,
                'email' => $email !== null ? strtolower($email) : null,
                'password' => $email !== null ? $password : null,
                'birth_year' => $invite->birth_year,
                'birth_month' => $invite->birth_month,
                'locale' => 'en',
            ]);

            $member = FamilyMember::query()->create([
                'family_id' => $invite->family_id,
                'user_id' => $user->id,
                'role' => FamilyMember::ROLE_MEMBER,
                'nickname' => $invite->nickname,
                'status' => FamilyMember::STATUS_ACTIVE,
                'joined_at' => now(),
            ]);

            $invite->forceFill(['used_by' => $user->id, 'used_at' => now()])->save();

            // The parental consent given for the invite now also points at the member.
            Consent::query()
                ->where('subject_invite_id', $invite->id)
                ->where('type', Consent::PARENTAL)
                ->update(['subject_member_id' => $member->id]);

            if ($email !== null) {
                $this->acceptLegalTerms->handle($user, $ipAddress);
            }

            return ['user' => $user, 'token' => $this->issueToken->handle($user, $deviceName, $platform, $appVersion)];
        });
    }
}
