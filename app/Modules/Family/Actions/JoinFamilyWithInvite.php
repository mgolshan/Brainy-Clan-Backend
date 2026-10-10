<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\Invite;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Support\Facades\DB;

/**
 * Join a family with an invite code on a device. Creates the user from the invite
 * (nickname, birth data), the membership and a device-bound token. Email + password are
 * only accepted when the invitee's age band allows collecting them (adults).
 */
class JoinFamilyWithInvite
{
    public function __construct(
        private readonly PolicyResolver $policy,
        private readonly IssueDeviceToken $issueToken,
    ) {}

    /**
     * @return array{user: User, token: string}
     */
    public function handle(
        string $code,
        string $deviceName,
        string $platform,
        ?string $email = null,
        ?string $password = null,
        ?string $appVersion = null,
    ): array {
        return DB::transaction(function () use ($code, $deviceName, $platform, $email, $password, $appVersion): array {
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

            $user = User::query()->create([
                'display_name' => $invite->nickname,
                'avatar' => $invite->avatar,
                'email' => $email !== null ? strtolower($email) : null,
                'password' => $email !== null ? $password : null,
                'birth_year' => $invite->birth_year,
                'birth_month' => $invite->birth_month,
                'locale' => 'en',
            ]);

            FamilyMember::query()->create([
                'family_id' => $invite->family_id,
                'user_id' => $user->id,
                'role' => FamilyMember::ROLE_MEMBER,
                'nickname' => $invite->nickname,
                'status' => FamilyMember::STATUS_ACTIVE,
                'joined_at' => now(),
            ]);

            $invite->forceFill(['used_by' => $user->id, 'used_at' => now()])->save();

            return ['user' => $user, 'token' => $this->issueToken->handle($user, $deviceName, $platform, $appVersion)];
        });
    }
}
