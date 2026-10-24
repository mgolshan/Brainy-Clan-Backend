<?php

namespace App\Modules\Billing\Models;

use App\Modules\Family\Models\Family;
use Database\Factories\CommissionEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of the affiliate commission ledger, append-only like the points ledger: amounts are
 * never changed or deleted; refunds and chargebacks are new negative entries. Only `status` moves
 * pending → payable (after the refund window) → paid (staff "mark paid"). Amounts are integers in
 * the currency's minor unit (cents); `*_usd_minor` when the source gave a USD amount.
 *
 * @property int $id
 * @property int $affiliate_id
 * @property int|null $affiliate_code_id
 * @property int|null $family_id
 * @property string $source
 * @property string $source_event_id
 * @property string|null $source_reference
 * @property string $kind
 * @property Carbon $occurred_at
 * @property string $currency
 * @property int $gross_minor
 * @property int $tax_minor
 * @property int $fee_minor
 * @property int $net_minor
 * @property string $commission_rate
 * @property int $commission_minor
 * @property int|null $net_usd_minor
 * @property int|null $commission_usd_minor
 * @property string $status
 * @property Carbon $payable_at
 * @property Carbon|null $paid_at
 * @property int|null $payout_id
 * @property-read Affiliate $affiliate
 * @property-read AffiliateCode|null $affiliateCode
 * @property-read Family|null $family
 * @property-read CommissionPayout|null $payout
 */
#[Fillable([
    'affiliate_id', 'affiliate_code_id', 'family_id', 'source', 'source_event_id', 'source_reference', 'kind',
    'occurred_at', 'currency', 'gross_minor', 'tax_minor', 'fee_minor', 'net_minor', 'commission_rate',
    'commission_minor', 'net_usd_minor', 'commission_usd_minor', 'status', 'payable_at', 'paid_at', 'payout_id',
])]
#[UseFactory(CommissionEntryFactory::class)]
class CommissionEntry extends Model
{
    /** @use HasFactory<CommissionEntryFactory> */
    use HasFactory;

    public const string SOURCE_REVENUECAT = 'revenuecat';

    public const string SOURCE_PADDLE = 'paddle';

    public const string KIND_SALE = 'sale';

    public const string KIND_REFUND = 'refund';

    public const string KIND_CHARGEBACK = 'chargeback';

    public const string KIND_CHARGEBACK_REVERSE = 'chargeback_reverse';

    public const array KINDS = [self::KIND_SALE, self::KIND_REFUND, self::KIND_CHARGEBACK, self::KIND_CHARGEBACK_REVERSE];

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_PAYABLE = 'payable';

    public const string STATUS_PAID = 'paid';

    public const array STATUSES = [self::STATUS_PENDING, self::STATUS_PAYABLE, self::STATUS_PAID];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'payable_at' => 'datetime',
            'paid_at' => 'datetime',
            'gross_minor' => 'integer',
            'tax_minor' => 'integer',
            'fee_minor' => 'integer',
            'net_minor' => 'integer',
            'commission_rate' => 'decimal:4',
            'commission_minor' => 'integer',
            'net_usd_minor' => 'integer',
            'commission_usd_minor' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * @return BelongsTo<AffiliateCode, $this>
     */
    public function affiliateCode(): BelongsTo
    {
        return $this->belongsTo(AffiliateCode::class);
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * @return BelongsTo<CommissionPayout, $this>
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(CommissionPayout::class, 'payout_id');
    }
}
