<?php

namespace App\Modules\Billing\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Money moved for a family (a store purchase/renewal/refund from RevenueCat, or a Paddle
 * transaction/adjustment). Dispatched once per new webhook event after it is stored; the queued
 * `RecordCommission` listener turns it into the family's first-paid date, attribution lock and
 * commission ledger entry. Amounts are signed integers in the currency's minor unit (refunds and
 * chargebacks negative): net = gross − tax − fee.
 */
class RevenueRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public const string KIND_SALE = 'sale';

    public const string KIND_REFUND = 'refund';

    public const string KIND_CHARGEBACK = 'chargeback';

    public const string KIND_CHARGEBACK_REVERSE = 'chargeback_reverse';

    /**
     * @param  string  $source  `revenuecat` | `paddle`
     * @param  string  $eventId  unique per source event (`revenuecat:<id>`, `paddle:<evt_…>`)
     * @param  string|null  $reference  the store / Paddle transaction id (for a refund: the refunded one)
     * @param  string|null  $offerCode  RevenueCat `offer_code`
     * @param  string|null  $affiliateCode  Paddle `custom_data.affiliate_code`
     * @param  string|null  $affiliateSource  Paddle `custom_data.affiliate_source` (`link` | `web_checkout`)
     */
    public function __construct(
        public readonly int $familyId,
        public readonly string $source,
        public readonly string $eventId,
        public readonly string $kind,
        public readonly ?string $reference,
        public readonly string $occurredAt,
        public readonly string $currency,
        public readonly int $grossMinor,
        public readonly int $taxMinor,
        public readonly int $feeMinor,
        public readonly int $netMinor,
        public readonly ?int $netUsdMinor = null,
        public readonly ?string $offerCode = null,
        public readonly ?string $affiliateCode = null,
        public readonly ?string $affiliateSource = null,
    ) {}

    public function isPaidSale(): bool
    {
        return $this->kind === self::KIND_SALE && $this->grossMinor > 0;
    }
}
