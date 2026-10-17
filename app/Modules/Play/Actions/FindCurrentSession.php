<?php

namespace App\Modules\Play\Actions;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Play\Models\PlaySession;

/**
 * The member's most recent unfinished session (`active`, `failed` or `redemption`), the game the
 * app offers to continue; null when there is none.
 */
class FindCurrentSession
{
    public function handle(FamilyMember $member): ?PlaySession
    {
        $session = PlaySession::query()
            ->where('member_id', $member->id)
            ->whereIn('status', PlaySession::UNFINISHED_STATUSES)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();

        $session?->setRelation('member', $member);

        return $session;
    }
}
