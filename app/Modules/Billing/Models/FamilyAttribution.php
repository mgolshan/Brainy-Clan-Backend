<?php

namespace App\Modules\Billing\Models;

use App\Modules\Family\Models\Family;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Which affiliate code a family came with (one per family, first touch). Locked at the family's
 * first paid transaction; afterwards only staff change it (with a reason, in the activity log).
 *
 * @property int $id
 * @property int $family_id
 * @property int $affiliate_code_id
 * @property int $affiliate_id
 * @property string $source
 * @property Carbon $attributed_at
 * @property Carbon|null $locked_at
 * @property int|null $changed_by_user_id
 * @property string|null $change_reason
 * @property-read Family $family
 * @property-read AffiliateCode $affiliateCode
 * @property-read Affiliate $affiliate
 * @property-read User|null $changedBy
 */
#[Fillable(['family_id', 'affiliate_code_id', 'affiliate_id', 'source', 'attributed_at', 'locked_at', 'changed_by_user_id', 'change_reason'])]
class FamilyAttribution extends Model
{
    /** `/r/{code}` landing page → web checkout. */
    public const string SOURCE_LINK = 'link';

    /** Entered in the app (`POST /family/referral-code`). */
    public const string SOURCE_CODE = 'code';

    /** A store offer code mapped to the affiliate code (RevenueCat `offer_code`). */
    public const string SOURCE_OFFER_CODE = 'offer_code';

    /** Typed on the website subscribe form (Paddle `custom_data.affiliate_code`). */
    public const string SOURCE_WEB_CHECKOUT = 'web_checkout';

    public const array SOURCES = [self::SOURCE_LINK, self::SOURCE_CODE, self::SOURCE_OFFER_CODE, self::SOURCE_WEB_CHECKOUT];

    protected function casts(): array
    {
        return [
            'attributed_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * @return BelongsTo<AffiliateCode, $this>
     */
    public function affiliateCode(): BelongsTo
    {
        return $this->belongsTo(AffiliateCode::class);
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
