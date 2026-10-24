<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\Affiliate;
use App\Modules\Billing\Models\CommissionEntry;
use App\Modules\Billing\Models\CommissionPayout;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Staff "mark paid" on the monthly payout report: records a payout for the payable entries of one
 * affiliate, month (`YYYY-MM` of the revenue events) and currency, moves them to `paid` and writes
 * the activity log. Entries are never edited otherwise; a negative month (refunds) is settled the
 * same way and deducted from the payment by staff.
 */
class MarkCommissionsPaid
{
    public function handle(int $affiliateId, string $month, string $currency, User $staff, ?string $note = null): ?CommissionPayout
    {
        $start = Carbon::createFromFormat('!Y-m', $month, 'UTC');

        if ($start === null) {
            return null;
        }

        $payout = DB::transaction(function () use ($affiliateId, $month, $currency, $staff, $note, $start): ?CommissionPayout {
            $entries = CommissionEntry::query()
                ->where('affiliate_id', $affiliateId)
                ->where('currency', $currency)
                ->where('status', CommissionEntry::STATUS_PAYABLE)
                ->where('occurred_at', '>=', $start)
                ->where('occurred_at', '<', $start->copy()->addMonth())
                ->lockForUpdate()
                ->get(['id', 'commission_minor']);

            if ($entries->isEmpty()) {
                return null;
            }

            $paidAt = now();
            $payout = CommissionPayout::query()->create([
                'affiliate_id' => $affiliateId,
                'month' => $month,
                'currency' => $currency,
                'amount_minor' => (int) $entries->sum('commission_minor'),
                'entries_count' => $entries->count(),
                'paid_at' => $paidAt,
                'paid_by_user_id' => $staff->id,
                'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 255) : null,
            ]);

            CommissionEntry::query()->whereKey($entries->modelKeys())->update([
                'status' => CommissionEntry::STATUS_PAID,
                'paid_at' => $paidAt,
                'payout_id' => $payout->id,
                'updated_at' => $paidAt,
            ]);

            return $payout;
        });

        if ($payout !== null) {
            activity('affiliates')
                ->performedOn($payout)
                ->causedBy($staff)
                ->event('paid')
                ->withProperties([
                    'affiliate_id' => $affiliateId,
                    'affiliate' => Affiliate::query()->whereKey($affiliateId)->value('name'),
                    'month' => $month,
                    'currency' => $currency,
                    'amount_minor' => $payout->amount_minor,
                    'entries' => $payout->entries_count,
                    'note' => $payout->note,
                ])
                ->log('Commission payout marked paid');
        }

        return $payout;
    }
}
