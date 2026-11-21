<?php

namespace App\Modules\Family\Models;

use App\Modules\Billing\Models\FamilyAttribution;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Models\Consent;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\PolicyResolver;
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
 * @property bool $chat_filter Profanity filter for the family chat (every plan, default on)
 * @property string $chat_mode Stored chat mode ({@see self::CHAT_MODES}); the effective one is {@see PolicyResolver::effectiveChatMode()}
 * @property Carbon|null $deleted_at Set by `DELETE /family` (row kept for the ledger)
 * @property Carbon|null $first_paid_at First paid transaction, store or web (Billing; locks the referral code)
 * @property-read Subscription|null $subscription
 * @property-read FamilyAttribution|null $attribution
 */
#[Fillable(['owner_user_id', 'name', 'timezone', 'country_code', 'tier', 'chat_filter', 'chat_mode'])]
#[UseFactory(FamilyFactory::class)]
class Family extends Model
{
    /** @use HasFactory<FamilyFactory> */
    use HasFactory, LogsActivity;

    public const string TIER_FREE = 'free';

    /** Chat modes (docs/API.md "Chat safety"): everyone posts. */
    public const string CHAT_MODE_NORMAL = 'normal';

    /** Only family admins post; everyone else reads, reacts and reports. */
    public const string CHAT_MODE_ANNOUNCEMENTS = 'announcements';

    /** No chat at all: every chat endpoint answers 403 `chat_off`, no pushes. */
    public const string CHAT_MODE_OFF = 'off';

    public const array CHAT_MODES = [self::CHAT_MODE_NORMAL, self::CHAT_MODE_ANNOUNCEMENTS, self::CHAT_MODE_OFF];

    /**
     * Column defaults, so a new model has them before it is refreshed.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'chat_filter' => true,
        'chat_mode' => self::CHAT_MODE_NORMAL,
    ];

    public const string TIER_FAMILY = 'family';

    public const string TIER_BIG_CLAN = 'big_clan';

    public const string TIER_CLASSROOM = 'classroom';

    /** All tiers, cheapest first (docs/API.md "Plans and tiers"). */
    public const array TIERS = [self::TIER_FREE, self::TIER_FAMILY, self::TIER_BIG_CLAN, self::TIER_CLASSROOM];

    /** Paid tiers in rank order: `family` < `big_clan` < `classroom`. */
    public const array PAID_TIERS = [self::TIER_FAMILY, self::TIER_BIG_CLAN, self::TIER_CLASSROOM];

    /** Plan names for the admin, emails and the website. */
    public const array TIER_LABELS = [
        self::TIER_FREE => 'Free',
        self::TIER_FAMILY => 'Family',
        self::TIER_BIG_CLAN => 'Big Clan',
        self::TIER_CLASSROOM => 'Classroom',
    ];

    /** French plan names (website, help center, emails). */
    public const array TIER_LABELS_FR = [
        self::TIER_FREE => 'Gratuit',
        self::TIER_FAMILY => 'Famille',
        self::TIER_BIG_CLAN => 'Grand Clan',
        self::TIER_CLASSROOM => 'Classe',
    ];

    public static function isPaidTier(string $tier): bool
    {
        return in_array($tier, self::PAID_TIERS, true);
    }

    /**
     * Rank of a tier: 0 for `free` (and unknown values), 1 `family`, 2 `big_clan`, 3 `classroom`.
     * When several subscriptions are entitled the highest rank wins.
     */
    public static function tierRank(string $tier): int
    {
        $index = array_search($tier, self::PAID_TIERS, true);

        return $index === false ? 0 : $index + 1;
    }

    /**
     * The highest-ranked tier of the given ones; `free` when none is paid.
     *
     * @param  iterable<string|null>  $tiers
     */
    public static function highestTier(iterable $tiers): string
    {
        $best = self::TIER_FREE;

        foreach ($tiers as $tier) {
            if ($tier !== null && self::tierRank($tier) > self::tierRank($best)) {
                $best = $tier;
            }
        }

        return $best;
    }

    public static function tierLabel(string $tier, string $locale = 'en'): string
    {
        $labels = $locale === 'fr' ? self::TIER_LABELS_FR : self::TIER_LABELS;

        return $labels[$tier] ?? ucwords(str_replace('_', ' ', $tier));
    }

    public function isPaid(): bool
    {
        return self::isPaidTier($this->tier);
    }

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'first_paid_at' => 'datetime',
            'chat_filter' => 'boolean',
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
        return LogOptions::defaults()->logOnly(['name', 'timezone', 'country_code', 'tier', 'chat_filter', 'chat_mode'])->logOnlyDirty()->useLogName('family');
    }
}
