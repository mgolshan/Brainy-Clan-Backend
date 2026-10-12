<?php

namespace App\Modules\Billing\Models;

use App\Modules\Family\Models\Family;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A family's subscription, mirrored from RevenueCat webhooks (one row per family).
 *
 * @property int $id
 * @property int $family_id
 * @property string $provider
 * @property string $app_user_id
 * @property string $entitlement
 * @property string $status
 * @property string|null $store
 * @property string|null $product_id
 * @property string|null $period_type
 * @property string|null $environment
 * @property bool $will_renew
 * @property Carbon|null $purchased_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $grace_expires_at
 * @property int|null $last_event_ms
 * @property string|null $last_event_type
 * @property array<string, mixed>|null $raw
 * @property-read Family $family
 */
#[Fillable([
    'family_id', 'provider', 'app_user_id', 'entitlement', 'status', 'store', 'product_id', 'period_type',
    'environment', 'will_renew', 'purchased_at', 'expires_at', 'grace_expires_at', 'last_event_ms',
    'last_event_type', 'raw',
])]
#[UseFactory(SubscriptionFactory::class)]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    public const string STATUS_ACTIVE = 'active';

    /** Billing issue: the store keeps retrying; the entitlement stays on until the grace period ends. */
    public const string STATUS_GRACE = 'grace';

    public const string STATUS_EXPIRED = 'expired';

    /** Shown by the API when a family never had a subscription (no row). */
    public const string STATUS_NONE = 'none';

    public const array STATUSES = [self::STATUS_ACTIVE, self::STATUS_GRACE, self::STATUS_EXPIRED];

    /** Stores the contract exposes; others (promotional, amazon) are reported as null. */
    public const array API_STORES = ['app_store', 'play_store', 'stripe'];

    protected function casts(): array
    {
        return [
            'will_renew' => 'boolean',
            'purchased_at' => 'datetime',
            'expires_at' => 'datetime',
            'grace_expires_at' => 'datetime',
            'last_event_ms' => 'integer',
            'raw' => 'array',
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
     * Whether the family entitlement is on right now: active or in the billing grace period,
     * and not past its (grace) expiry.
     */
    public function isEntitled(?Carbon $at = null): bool
    {
        $at ??= now();

        return match ($this->status) {
            self::STATUS_ACTIVE => $this->expires_at === null || $this->expires_at->greaterThan($at),
            self::STATUS_GRACE => ($this->grace_expires_at ?? $this->expires_at) === null
                || ($this->grace_expires_at ?? $this->expires_at)->greaterThan($at),
            default => false,
        };
    }

    public function apiStore(): ?string
    {
        return in_array($this->store, self::API_STORES, true) ? $this->store : null;
    }
}
