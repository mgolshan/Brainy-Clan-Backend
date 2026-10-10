<?php

namespace App\Modules\Family\Actions;

use App\Modules\Family\Models\Invite;
use App\Modules\Policy\PolicyResolver;

/**
 * Support tool: gives an unused invite a fresh code and a new expiry date
 * (e.g. when a parent lost the code or it expired).
 */
class ResetInvite
{
    public function __construct(
        private readonly PolicyResolver $policy,
        private readonly GenerateInviteCode $generateCode,
    ) {}

    public function handle(Invite $invite): Invite
    {
        $invite->forceFill([
            'code' => $this->generateCode->handle(),
            'expires_at' => now()->addDays($this->policy->inviteTtlDays()),
        ])->save();

        return $invite;
    }
}
