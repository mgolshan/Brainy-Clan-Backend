<?php

namespace App\Modules\Family\Models;

use App\Modules\Billing\Models\FamilyAttribution;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Models\Consent;
use App\Modules\Identity\Models\User;
use Database\Factories\FamilyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $owner_user_id
 * @property string $name
 * @property string $timezone
 * @property string $country_code
 * @property string $tier
 * @property Carbon|null $deleted_at Set by `DELETE /family` (row kept for the ledger)
 * @property Carbon|null $first_paid_at First paid transaction, store or web (Billing; locks the referral code)
 * @property-read Subscription|null $subscription
 * @property-read FamilyAttribution|null $attribution
 */
#[Fillable(['owner_user_id', 'name', 'timezone', 'country_code', 'tier'])]
#[UseFactory(FamilyFactory::class)]
class Family extends Model
{
    /** @use HasFactory<FamilyFactory> */
    use HasFactory, LogsActivity;

    public const string TIER_FREE = 'free';

    public const string TIER_FAMILY = 'family';

    public const array TIERS = [self::TIER_FREE, self::TIER_FAMILY];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'first_paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * All memberships, including removed ones.
     *
     * @return HasMany<FamilyMember, $this>
     */
    public function allMembers(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    /**
     * @return HasMany<FamilyMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(FamilyMember::class)->where('status', FamilyMember::STATUS_ACTIVE);
    }

    /**
     * Legal consents given by the family's members (incl. removed ones): their terms/privacy
     * acceptances and the parental consents admins gave for children. Read-only in the admin.
     *
     * @return HasManyThrough<Consent, FamilyMember, $this>
     */
    public function consents(): HasManyThrough
    {
        return $this->hasManyThrough(Consent::class, FamilyMember::class, 'family_id', 'user_id', 'id', 'user_id');
    }

    /**
     * @return HasMany<Invite, $this>
     */
    public function invites(): HasMany
    {
        return $this->hasMany(Invite::class);
    }

    /**
     * @return HasMany<Invite, $this>
     */
    public function pendingInvites(): HasMany
    {
        return $this->hasMany(Invite::class)->whereNull('used_at')->where('expires_at', '>', now());
    }

    /**
     * The most recently updated subscription row (Billing; RevenueCat or Paddle), for display and
     * eager loading; null when the family never subscribed. Rules use `Subscription::currentFor()`
     * and `subscriptions`.
     *
     * @return HasOne<Subscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany('updated_at');
    }

    /**
     * One row per billing provider (RevenueCat, Paddle).
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * The affiliate code the family came with (Billing), if any.
     *
     * @return HasOne<FamilyAttribution, $this>
     */
    public function attribution(): HasOne
    {
        return $this->hasOne(FamilyAttribution::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'timezone', 'country_code', 'tier'])->logOnlyDirty()->useLogName('family');
    }
}
