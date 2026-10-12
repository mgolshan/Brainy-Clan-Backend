<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\SignInCode;
use App\Modules\Identity\Actions\IssueDeviceToken;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * `POST /auth/code`: redeems an admin-issued sign-in code on a new device and returns a
 * device-bound token for the member's user. Unknown, used, voided or expired codes, and codes
 * of members who left the family, are all `sign_in_code_invalid`.
 */
class SignInWithCode
{
    public function __construct(private readonly IssueDeviceToken $issueToken) {}

    /**
     * @return array{user: User, token: string}
     */
    public function handle(string $code, string $deviceName, string $platform, ?string $appVersion = null): array
    {
        return DB::transaction(function () use ($code, $deviceName, $platform, $appVersion): array {
            /** @var SignInCode|null $signInCode */
            $signInCode = SignInCode::query()
                ->with('member.user')
                ->where('code_hash', SignInCode::hashCode($code))
                ->usable()
                ->lockForUpdate()
                ->first();

            if ($signInCode === null || $signInCode->member->status !== FamilyMember::STATUS_ACTIVE) {
                throw ApiException::signInCodeInvalid();
            }

            $user = $signInCode->member->user;
            $issued = $this->issueToken->issue($user, $deviceName, $platform, $appVersion);

            $signInCode->forceFill(['used_at' => now(), 'device_id' => $issued['device']->id])->save();

            return ['user' => $user, 'token' => $issued['token']];
        });
    }
}
