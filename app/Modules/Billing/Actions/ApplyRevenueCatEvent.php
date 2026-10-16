<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\WebhookEvent;
use App\Modules\Family\Models\Family;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Applies one RevenueCat webhook to the family's subscription and tier.
 *
 * Idempotent: every event is stored once in `webhook_events` (unique provider + event id), so a
 * redelivery is a no-op. Events older than the last applied one are recorded as `stale`. The
 * family tier is `family` while the entitlement is active (incl. the billing grace period).
 */
class ApplyRevenueCatEvent
{
    /** Events that (re)activate the entitlement. */
    private const array ACTIVATING = ['INITIAL_PURCHASE', 'RENEWAL', 'UNCANCELLATION', 'PRODUCT_CHANGE', 'NON_RENEWING_PURCHASE'];

    private const array HANDLED = [...self::ACTIVATING, 'CANCELLATION', 'BILLING_ISSUE', 'EXPIRATION'];

    public function __construct(private readonly ApplyFamilyTier $applyTier) {}

    /**
     * Returns the stored event; `wasRecentlyCreated` is false for a duplicate delivery.
     *
     * @param  array<string, mixed>  $payload  The whole webhook body (`{ api_version, event: {...} }`).
     */
    public function handle(array $payload): WebhookEvent
    {
        /** @var array<string, mixed> $event */
        $event = is_array($payload['event'] ?? null) ? $payload['event'] : [];
        $eventId = is_scalar($event['id'] ?? null) ? (string) $event['id'] : '';
        $type = is_string($event['type'] ?? null) ? $event['type'] : null;

        $existing = $this->find($eventId);

        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($payload, $event, $eventId, $type): WebhookEvent {
                $record = WebhookEvent::query()->create([
                    'provider' => WebhookEvent::PROVIDER_REVENUECAT,
                    'event_id' => $eventId,
                    'type' => $type,
                    'payload' => $payload,
                ]);

                [$outcome, $note, $familyId] = $this->apply($event, $type);

                $record->forceFill([
                    'family_id' => $familyId,
                    'outcome' => $outcome,
                    'note' => $note,
                    'processed_at' => now(),
                ])->save();

                return $record;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent delivery of the same event won the race.
            return $this->find($eventId) ?? throw new \RuntimeException('Webhook event vanished.');
        }
    }

    private function find(string $eventId): ?WebhookEvent
    {
        return WebhookEvent::query()
            ->where('provider', WebhookEvent::PROVIDER_REVENUECAT)
            ->where('event_id', $eventId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array{0: string, 1: string|null, 2: int|null}
     */
    private function apply(array $event, ?string $type): array
    {
        if ($type === null || ! in_array($type, self::HANDLED, true)) {
            return [WebhookEvent::OUTCOME_IGNORED, 'Unhandled event type.', null];
        }

        $family = $this->family($event);

        if ($family === null) {
            return [WebhookEvent::OUTCOME_IGNORED, 'Unknown family.', null];
        }

        $entitlement = (string) config('services.revenuecat.entitlement', 'family');
        $entitlements = is_array($event['entitlement_ids'] ?? null) ? $event['entitlement_ids'] : [];

        if ($entitlements !== [] && ! in_array($entitlement, $entitlements, true)) {
            return [WebhookEvent::OUTCOME_IGNORED, 'Other entitlement.', $family->id];
        }

        $eventMs = is_numeric($event['event_timestamp_ms'] ?? null) ? (int) $event['event_timestamp_ms'] : null;

        $subscription = Subscription::query()->where('family_id', $family->id)->lockForUpdate()->first()
            ?? new Subscription([
                'family_id' => $family->id,
                'provider' => WebhookEvent::PROVIDER_REVENUECAT,
                'app_user_id' => 'family-'.$family->id,
                'entitlement' => $entitlement,
                'status' => Subscription::STATUS_ACTIVE,
            ]);

        if ($eventMs !== null && $subscription->last_event_ms !== null && $eventMs < $subscription->last_event_ms) {
            return [WebhookEvent::OUTCOME_STALE, 'Older than the last applied event.', $family->id];
        }

        $expiresAt = $this->time($event['expiration_at_ms'] ?? null);

        $subscription->fill([
            'store' => $this->lower($event['store'] ?? null) ?? $subscription->store,
            'product_id' => is_string($event['product_id'] ?? null) ? $event['product_id'] : $subscription->product_id,
            'period_type' => $this->lower($event['period_type'] ?? null) ?? $subscription->period_type,
            'environment' => is_string($event['environment'] ?? null) ? $event['environment'] : $subscription->environment,
            'purchased_at' => $this->time($event['purchased_at_ms'] ?? null) ?? $subscription->purchased_at,
            'expires_at' => $expiresAt ?? $subscription->expires_at,
            'last_event_ms' => $eventMs ?? $subscription->last_event_ms,
            'last_event_type' => $type,
            'raw' => $event,
        ]);

        match ($type) {
            // Still entitled until expires_at; RevenueCat sends EXPIRATION when it ends.
            'CANCELLATION' => $subscription->fill(['will_renew' => false]),
            'BILLING_ISSUE' => $subscription->fill([
                'status' => Subscription::STATUS_GRACE,
                'grace_expires_at' => $this->time($event['grace_period_expiration_at_ms'] ?? null) ?? $expiresAt,
            ]),
            'EXPIRATION' => $subscription->fill(['status' => Subscription::STATUS_EXPIRED, 'will_renew' => false]),
            default => $subscription->fill([
                'status' => Subscription::STATUS_ACTIVE,
                'will_renew' => $type !== 'NON_RENEWING_PURCHASE',
                'grace_expires_at' => null,
            ]),
        };

        $this->applyTier->handle($family, $subscription, ApplyFamilyTier::SOURCE_WEBHOOK);

        return [WebhookEvent::OUTCOME_APPLIED, null, $family->id];
    }

    /**
     * The family from `app_user_id` (`family-{id}`), falling back to the original id and aliases.
     *
     * @param  array<string, mixed>  $event
     */
    private function family(array $event): ?Family
    {
        $candidates = [$event['app_user_id'] ?? null, $event['original_app_user_id'] ?? null];

        if (is_array($event['aliases'] ?? null)) {
            array_push($candidates, ...array_values($event['aliases']));
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && preg_match('/^family-(\d+)$/', $candidate, $matches) === 1) {
                $family = Family::query()->lockForUpdate()->find((int) $matches[1]);

                if ($family !== null) {
                    return $family;
                }
            }
        }

        return null;
    }

    private function lower(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? strtolower($value) : null;
    }

    private function time(mixed $ms): ?Carbon
    {
        return is_numeric($ms) ? Carbon::createFromTimestampMs((int) $ms) : null;
    }
}
