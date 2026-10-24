<?php

namespace App\Modules\Billing\Models;

use App\Modules\Family\Models\Family;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A request made with the website subscribe form (`POST /web/subscribe`). Holds no plain email:
 * `email_hash` (HMAC) and `token_hash` (SHA-256 of the emailed link's token). A link creates at
 * most one Paddle transaction: opening it again within its lifetime returns to the same checkout,
 * so a mail scanner opening it first does no harm.
 *
 * @property int $id
 * @property string $email_hash
 * @property int|null $user_id
 * @property int|null $family_id
 * @property string $plan
 * @property string|null $affiliate_code
 * @property string|null $affiliate_source
 * @property string|null $token_hash
 * @property string $status
 * @property string|null $reason
 * @property string $locale
 * @property string|null $transaction_id
 * @property Carbon $expires_at
 * @property Carbon|null $opened_at
 * @property string|null $ip_hash
 * @property-read User|null $user
 * @property-read Family|null $family
 */
#[Fillable([
    'email_hash', 'user_id', 'family_id', 'plan', 'affiliate_code', 'affiliate_source', 'token_hash', 'status',
    'reason', 'locale', 'transaction_id', 'expires_at', 'opened_at', 'ip_hash',
])]
class WebCheckoutRequest extends Model
{
    /** Link emailed, not opened yet. */
    public const string STATUS_PENDING = 'pending';

    /** Link opened: the Paddle transaction exists (`transaction_id`). */
    public const string STATUS_OPENED = 'opened';

    /** A newer request for the same email replaced it. */
    public const string STATUS_SUPERSEDED = 'superseded';

    /** The email has an account that can't buy now; `reason` says why (and the email explains). */
    public const string STATUS_INELIGIBLE = 'ineligible';

    public const string STATUS_NO_ACCOUNT = 'no_account';

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'opened_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function isUsable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_OPENED], true)
            && $this->user_id !== null && $this->family_id !== null && $this->expires_at->isFuture();
    }

    public static function hashEmail(string $email): string
    {
        return hash_hmac('sha256', 'web-checkout|'.strtolower(trim($email)), (string) config('app.key'));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
