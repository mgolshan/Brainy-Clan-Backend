<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\Invite;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Support\Facades\DB;

/**
 * An admin invites a new member (nickname + birth data). Refused with 403 `member_limit_reached`
 * when active members + pending invites already fill the tier's member limit.
 */
class CreateInvite
{
    public function __construct(
        private readonly PolicyResolver $policy,
        private readonly GenerateInviteCode $generateCode,
    ) {}

    /**
     * @param  array{nickname: string, birth_year: int, birth_month: int, avatar?: string|null}  $data
     */
    public function handle(FamilyMember $admin, array $data): Invite
    {
        $family = $admin->family;

        return DB::transaction(function () use ($admin, $family, $data): Invite {
            $used = $family->members()->count() + $family->pendingInvites()->count();

            if ($used >= $this->policy->maxMembers($family)) {
                throw ApiException::memberLimitReached();
            }

            return Invite::query()->create([
                'family_id' => $family->id,
                'code' => $this->generateCode->handle(),
                'created_by' => $admin->user_id,
                'nickname' => $data['nickname'],
                'avatar' => $data['avatar'] ?? null,
                'birth_year' => (int) $data['birth_year'],
                'birth_month' => (int) $data['birth_month'],
                'expires_at' => now()->addDays($this->policy->inviteTtlDays()),
            ]);
        });
    }
}
