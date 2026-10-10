<?php

namespace App\Modules\Family\Models;

use App\Modules\Identity\Models\User;
use Database\Factories\FamilyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $owner_user_id
 * @property string $name
 * @property string $timezone
 * @property string $country_code
 * @property string $tier
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'timezone', 'country_code', 'tier'])->logOnlyDirty()->useLogName('family');
    }
}
