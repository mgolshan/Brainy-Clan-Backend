<?php

namespace App\Modules\Billing\Models;

use App\Modules\Family\Models\Family;
use Database\Factories\WebhookEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One received webhook (unique per provider + event id), so a redelivery is a no-op.
 *
 * @property int $id
 * @property string $provider
 * @property string $event_id
 * @property string|null $type
 * @property int|null $family_id
 * @property array<string, mixed> $payload
 * @property string|null $outcome
 * @property string|null $note
 * @property Carbon|null $processed_at
 * @property Carbon $created_at
 * @property-read Family|null $family
 */
#[Fillable(['provider', 'event_id', 'type', 'family_id', 'payload', 'outcome', 'note', 'processed_at'])]
#[UseFactory(WebhookEventFactory::class)]
class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory;

    public const string PROVIDER_REVENUECAT = 'revenuecat';

    public const string PROVIDER_PADDLE = 'paddle';

    public const string OUTCOME_APPLIED = 'applied';

    public const string OUTCOME_IGNORED = 'ignored';

    /** Older than the last event already applied to the subscription (out-of-order delivery). */
    public const string OUTCOME_STALE = 'stale';

    public const string OUTCOME_FAILED = 'failed';

    public const array OUTCOMES = [self::OUTCOME_APPLIED, self::OUTCOME_IGNORED, self::OUTCOME_STALE, self::OUTCOME_FAILED];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }
}
