<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\Subscription;
use Illuminate\Support\Carbon;

/**
 * Maps a Paddle subscription entity (webhook `data` or `GET /subscriptions/{id}`) onto the
 * family's `paddle` subscription row, with the same states as RevenueCat:
 *
 * | Paddle `status` | row `status` | notes |
 * | --- | --- | --- |
 * | `active` | active | `will_renew` false when a cancel/pause is scheduled |
 * | `trialing` | active | `period_type` trial |
 * | `past_due` | grace | until the period end + `billing.paddle.grace_days` (Paddle's dunning) |
 * | `paused`, `canceled` | expired | |
 */
final class PaddleSubscriptionState
{
    public const array ENTITLED_STATUSES = ['active', 'trialing', 'past_due'];

    /**
     * @param  array<string, mixed>  $data  the Paddle subscription entity
     */
    public static function fill(Subscription $subscription, array $data, int $familyId, int $eventMs, string $eventType): void
    {
        $status = is_string($data['status'] ?? null) ? $data['status'] : '';
        $period = is_array($data['current_billing_period'] ?? null) ? $data['current_billing_period'] : [];
        $scheduled = is_array($data['scheduled_change'] ?? null) ? $data['scheduled_change'] : [];
        $items = is_array($data['items'] ?? null) ? array_values($data['items']) : [];
        $firstItem = is_array($items[0] ?? null) ? $items[0] : [];
        $price = is_array($firstItem['price'] ?? null) ? $firstItem['price'] : [];

        $expiresAt = self::time($period['ends_at'] ?? null)
            ?? self::time($data['next_billed_at'] ?? null)
            ?? ($status === 'canceled' ? self::time($data['canceled_at'] ?? null) : null)
            ?? $subscription->expires_at;

        $rowStatus = match ($status) {
            'active', 'trialing' => Subscription::STATUS_ACTIVE,
            'past_due' => Subscription::STATUS_GRACE,
            default => Subscription::STATUS_EXPIRED,
        };

        $cancelScheduled = in_array($scheduled['action'] ?? null, ['cancel', 'pause'], true);

        $subscription->fill([
            'family_id' => $familyId,
            'provider' => Subscription::PROVIDER_PADDLE,
            'app_user_id' => 'family-'.$familyId,
            'entitlement' => (string) config('services.revenuecat.entitlement', 'family'),
            'external_id' => is_string($data['id'] ?? null) ? $data['id'] : $subscription->external_id,
            'customer_id' => is_string($data['customer_id'] ?? null) ? $data['customer_id'] : $subscription->customer_id,
            'status' => $rowStatus,
            'store' => Subscription::STORE_PADDLE,
            'product_id' => is_string($price['id'] ?? null) ? $price['id'] : $subscription->product_id,
            'period_type' => $status === 'trialing' ? 'trial' : 'normal',
            'environment' => strtoupper(PaddleConfig::environment()),
            'will_renew' => in_array($status, self::ENTITLED_STATUSES, true) && ! $cancelScheduled,
            'purchased_at' => self::time($data['started_at'] ?? null) ?? self::time($data['first_billed_at'] ?? null) ?? $subscription->purchased_at,
            'expires_at' => $expiresAt,
            'grace_expires_at' => $rowStatus === Subscription::STATUS_GRACE
                ? ($expiresAt ?? now())->copy()->addDays((int) config('billing.paddle.grace_days', 30))
                : null,
            'last_event_ms' => max($eventMs, (int) $subscription->last_event_ms),
            'last_event_type' => $eventType,
            'raw' => $data,
        ]);
    }

    public static function time(mixed $value): ?Carbon
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value)->utc() : null;
    }
}
