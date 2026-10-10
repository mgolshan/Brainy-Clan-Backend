<?php

namespace App\Modules\Scoring\Actions;

use App\Modules\Scoring\Models\PointEntry;

/**
 * The only way points enter the ledger: one new row per award (never an update).
 */
class AppendPoints
{
    public function handle(
        int $familyId,
        int $memberId,
        int $categoryId,
        int $amount,
        string $reason,
        ?int $sessionId = null,
        ?int $sessionItemId = null,
    ): PointEntry {
        return PointEntry::query()->create([
            'family_id' => $familyId,
            'member_id' => $memberId,
            'category_id' => $categoryId,
            'play_session_id' => $sessionId,
            'session_item_id' => $sessionItemId,
            'amount' => $amount,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
