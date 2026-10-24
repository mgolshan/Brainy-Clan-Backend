<?php

namespace App\Modules\Billing\Models;

use Database\Factories\AffiliateCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A referral code of an affiliate (`MAYA`): stored upper case, matched case-insensitively.
 * Optionally mapped to store offer codes (what RevenueCat reports as `offer_code`) and a Paddle
 * discount applied at web checkout.
 *
 * @property int $id
 * @property int $affiliate_id
 * @property string $code
 * @property list<string>|null $store_offer_codes
 * @property string|null $paddle_discount_id
 * @property bool $active
 * @property string|null $notes
 * @property-read Affiliate $affiliate
 */
#[Fillable(['affiliate_id', 'code', 'store_offer_codes', 'paddle_discount_id', 'active', 'notes'])]
#[UseFactory(AffiliateCodeFactory::class)]
class AffiliateCode extends Model
{
    /** @use HasFactory<AffiliateCodeFactory> */
    use HasFactory, LogsActivity;

    /** Letters, digits and dashes, at most 20 (docs/API.md). */
    public const string PATTERN = '/^[A-Za-z0-9-]{1,20}$/';

    protected function casts(): array
    {
        return [
            'store_offer_codes' => 'array',
            'active' => 'boolean',
        ];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function code(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => self::normalize($value));
    }

    public static function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    public static function isWellFormed(mixed $code): bool
    {
        return is_string($code) && preg_match(self::PATTERN, trim($code)) === 1;
    }

    /**
     * An active code of a non-ended affiliate, case-insensitive; null when unknown or inactive.
     */
    public static function findUsable(mixed $code): ?self
    {
        if (! self::isWellFormed($code)) {
            return null;
        }

        /** @var string $code */
        return self::query()->usable()->with('affiliate')->where('code', self::normalize($code))->first();
    }

    /**
     * Codes that can attribute new families: active, of an active affiliate.
     *
     * @param  Builder<AffiliateCode>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->where('active', true)
            ->whereHas('affiliate', fn (Builder $affiliate) => $affiliate->where('status', Affiliate::STATUS_ACTIVE));
    }

    /**
     * The usable code mapped to this store offer code (case-insensitive), if any.
     */
    public static function forOfferCode(string $offerCode): ?self
    {
        $needle = self::normalize($offerCode);

        if ($needle === '') {
            return null;
        }

        return self::query()->usable()->with('affiliate')->whereNotNull('store_offer_codes')->get()
            ->first(fn (self $code): bool => in_array($needle, array_map(self::normalize(...), $code->store_offer_codes ?? []), true));
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * @return HasMany<FamilyAttribution, $this>
     */
    public function attributions(): HasMany
    {
        return $this->hasMany(FamilyAttribution::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['affiliate_id', 'code', 'store_offer_codes', 'paddle_discount_id', 'active'])
            ->logOnlyDirty()
            ->useLogName('affiliates');
    }
}
