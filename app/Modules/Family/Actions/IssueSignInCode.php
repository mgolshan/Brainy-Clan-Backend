<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\SignInCode;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Support\Facades\DB;

/**
 * An admin (or support in the admin panel) issues a one-time sign-in code for a member's new
 * device. Older unused codes of that member are voided, so only the newest code works.
 * Returns the plain code; only its HMAC is stored.
 */
class IssueSignInCode
{
    public function __construct(private readonly PolicyResolver $policy) {}

    /**
     * @return array{code: string, sign_in_code: SignInCode}
     */
    public function handle(FamilyMember $member, ?User $issuer = null): array
    {
        if ($member->status !== FamilyMember::STATUS_ACTIVE) {
            throw ApiException::forbidden('This member is no longer in the family.');
        }

        return DB::transaction(function () use ($member, $issuer): array {
            SignInCode::query()
                ->where('member_id', $member->id)
                ->whereNull('used_at')
                ->whereNull('voided_at')
                ->update(['voided_at' => now()]);

            do {
                $code = GenerateInviteCode::random();
                $hash = SignInCode::hashCode($code);
            } while (SignInCode::query()->usable()->where('code_hash', $hash)->exists());

            $signInCode = SignInCode::query()->create([
                'member_id' => $member->id,
                'code_hash' => $hash,
                'created_by' => $issuer?->id,
                'expires_at' => now()->addMinutes($this->policy->signInCodeTtlMinutes()),
            ]);

            return ['code' => $code, 'sign_in_code' => $signInCode];
        });
    }
}
