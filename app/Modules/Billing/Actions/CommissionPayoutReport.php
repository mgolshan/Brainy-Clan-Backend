<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\Affiliate;
use App\Modules\Billing\Models\CommissionEntry;
use App\Modules\Billing\Support\Money;
use Illuminate\Support\Collection;

/**
 * The monthly payout report (Filament "Monthly payouts", CSV): payable commission per affiliate,
 * month (of the revenue event) and currency. Counts and amounts only — never family identities,
 * so the CSV can be shared with an affiliate.
 *
 * @phpstan-type PayoutRow array{key: string, affiliate_id: int, affiliate: string, month: string, currency: string, entries: int, net: string, commission: string, commission_minor: int, commission_usd: string|null, payout_method: string|null}
 */
class CommissionPayoutReport
{
    /**
     * @return Collection<string, PayoutRow>
     */
    public function payable(): Collection
    {
        $entries = CommissionEntry::query()
            ->where('status', CommissionEntry::STATUS_PAYABLE)
            ->orderBy('occurred_at')
            ->get(['id', 'affiliate_id', 'occurred_at', 'currency', 'net_minor', 'commission_minor', 'commission_usd_minor']);

        $affiliates = Affiliate::query()->whereIn('id', $entries->pluck('affiliate_id')->unique())->get(['id', 'name', 'payout_method_note'])->keyBy('id');

        /** @var Collection<string, PayoutRow> $rows */
        $rows = $entries
            ->groupBy(fn (CommissionEntry $entry): string => self::key($entry->affiliate_id, $entry->occurred_at->format('Y-m'), $entry->currency))
            ->map(function (Collection $group, string $key) use ($affiliates): array {
                /** @var CommissionEntry $first */
                $first = $group->first();
                $affiliate = $affiliates->get($first->affiliate_id);
                $commission = (int) $group->sum('commission_minor');
                $usdKnown = $group->every(fn (CommissionEntry $entry): bool => $entry->commission_usd_minor !== null);

                return [
                    'key' => $key,
                    'affiliate_id' => $first->affiliate_id,
                    'affiliate' => $affiliate !== null ? $affiliate->name : '#'.$first->affiliate_id,
                    'month' => $first->occurred_at->format('Y-m'),
                    'currency' => $first->currency,
                    'entries' => $group->count(),
                    'net' => Money::format((int) $group->sum('net_minor'), $first->currency),
                    'commission' => Money::format($commission, $first->currency),
                    'commission_minor' => $commission,
                    'commission_usd' => $usdKnown ? Money::format((int) $group->sum('commission_usd_minor'), 'USD') : null,
                    'payout_method' => $affiliate?->payout_method_note,
                ];
            })
            ->sortBy(fn (array $row): string => $row['month'].'|'.$row['affiliate'].'|'.$row['currency']);

        return $rows;
    }

    /**
     * CSV of {@see payable()} (UTF-8, comma-separated, header row).
     */
    public function csv(): string
    {
        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);
        fputcsv($handle, ['affiliate_id', 'affiliate', 'month', 'currency', 'entries', 'net', 'commission', 'commission_usd'], escape: '');

        foreach ($this->payable() as $row) {
            fputcsv($handle, [$row['affiliate_id'], $row['affiliate'], $row['month'], $row['currency'], $row['entries'], $row['net'], $row['commission'], $row['commission_usd'] ?? ''], escape: '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    public static function key(int $affiliateId, string $month, string $currency): string
    {
        return "{$affiliateId}|{$month}|{$currency}";
    }
}
