<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\CommissionEntry;

/**
 * Daily: commission entries whose refund window passed (`payable_at`) move pending → payable.
 * Only the status changes; amounts are never touched.
 */
class ReleasePayableCommissions
{
    /**
     * @return int how many entries became payable
     */
    public function handle(): int
    {
        return CommissionEntry::query()
            ->where('status', CommissionEntry::STATUS_PENDING)
            ->where('payable_at', '<=', now())
            ->update(['status' => CommissionEntry::STATUS_PAYABLE, 'updated_at' => now()]);
    }
}
