<?php

namespace App\Modules\Billing\Models;

use Database\Factories\AffiliateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An influencer / partner in the in-house affiliate program (docs/PLAN.md B10). Earns
 * `commission_rate` of net revenue for `commission_months` after an attributed family's first
 * paid transaction. Staff-only data: influencers never see family identities.
 *
 * @property int $id
 * @property string $name
 * @property string|null $contact_email
 * @property string|null $contact_note
 * @property string|null $payout_method_note
 * @property string $commission_rate decimal string, e.g. "0.2500"
 * @property int $commission_months
 * @property string $status
 * @property Carbon|null $created_at
 */
#[Fillable(['name', 'contact_email', 'contact_note', 'payout_method_note', 'commission_rate', 'commission_months', 'status'])]
#[UseFactory(AffiliateFactory::class)]
class Affiliate extends Model
{
    /** @use HasFactory<AffiliateFactory> */
    use HasFactory, LogsActivity;

    public const string STATUS_ACTIVE = 'active';

    /** Codes stop attributing new families; existing families keep earning. */
    public const string STATUS_PAUSED = 'paused';

    /** Codes stop attributing and no new commission is earned. */
    public const string STATUS_ENDED = 'ended';

    public const array STATUSES = [self::STATUS_ACTIVE, self::STATUS_PAUSED, self::STATUS_ENDED];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:4',
            'commission_months' => 'integer',
        ];
    }

    /**
     * @return HasMany<AffiliateCode, $this>
     */
    public function codes(): HasMany
    {
        return $this->hasMany(AffiliateCode::class);
    }

    /**
     * @return HasMany<FamilyAttribution, $this>
     */
    public function attributions(): HasMany
    {
        return $this->hasMany(FamilyAttribution::class);
    }

    /**
     * @return HasMany<CommissionEntry, $this>
     */
    public function commissionEntries(): HasMany
    {
        return $this->hasMany(CommissionEntry::class);
    }

    public function earnsCommission(): bool
    {
        return $this->status !== self::STATUS_ENDED;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'contact_email', 'commission_rate', 'commission_months', 'status'])
            ->logOnlyDirty()
            ->useLogName('affiliates');
    }
}
