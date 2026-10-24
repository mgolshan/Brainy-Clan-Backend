<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Events\RevenueRecorded;
use App\Modules\Billing\Models\CommissionEntry;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\WebhookEvent;
use App\Modules\Billing\Support\Money;
use App\Modules\Family\Models\Family;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    /** Events that can carry money: purchases and renewals, and refunds (CANCELLATION with a negative price). */
    private const array REVENUE = ['INITIAL_PURCHASE', 'RENEWAL', 'NON_RENEWING_PURCHASE', 'PRODUCT_CHANGE', 'CANCELLATION'];

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

        // Money moved (purchase, renewal, refund): commissions, independent of the state ordering below.
        $this->recordRevenue($event, $type, $family, $eventMs);

        $subscription = Subscription::query()->where('family_id', $family->id)->where('provider', Subscription::PROVIDER_REVENUECAT)->lockForUpdate()->first()
            ?? new Subscription([
                'family_id' => $family->id,
                'provider' => Subscription::PROVIDER_REVENUECAT,
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

        $note = null;

        if (in_array($type, self::ACTIVATING, true) && Subscription::entitledElsewhere($family->id, Subscription::PROVIDER_REVENUECAT) !== null) {
            // The store can't be blocked: both plans are kept; support refunds one (docs/API.md `subscription_exists`).
            $note = 'Conflict: the family also has an active web (Paddle) plan.';
            Log::warning('Store purchase while a web plan is active', ['family_id' => $family->id, 'event_id' => $event['id'] ?? null]);
        }

        return [WebhookEvent::OUTCOME_APPLIED, $note, $family->id];
    }

    /**
     * Dispatches {@see RevenueRecorded} for an event that moved money: a purchase / renewal with a
     * price, or a refund (negative price, sent as CANCELLATION). Net = gross − tax − store fee, with
     * RevenueCat's estimated `tax_percentage` and `commission_percentage` (falling back to
     * `billing.affiliates.store_fee_rate`); `price` gives the USD figure.
     *
     * @param  array<string, mixed>  $event
     */
    private function recordRevenue(array $event, string $type, Family $family, ?int $eventMs): void
    {
        $price = $event['price_in_purchased_currency'] ?? null;
        $currency = is_string($event['currency'] ?? null) && strlen($event['currency']) === 3 ? strtoupper($event['currency']) : null;

        $offerCode = is_string($event['offer_code'] ?? null) && $event['offer_code'] !== '' ? $event['offer_code'] : null;

        // A free trial (price 0) still matters when it carries an offer code: it attributes the family.
        if (! in_array($type, self::REVENUE, true) || ! is_numeric($price) || $currency === null || ((float) $price === 0.0 && $offerCode === null)) {
            return;
        }

        $sign = (float) $price < 0 ? -1 : 1;
        $taxRate = is_numeric($event['tax_percentage'] ?? null) ? (float) $event['tax_percentage'] : 0.0;
        $feeRate = is_numeric($event['commission_percentage'] ?? null)
            ? (float) $event['commission_percentage']
            : (float) config('billing.affiliates.store_fee_rate', 0.15);

        $gross = Money::toMinor(abs((float) $price), $currency);
        $tax = (int) round($gross * $taxRate);
        $fee = (int) round(($gross - $tax) * $feeRate);
        $usd = is_numeric($event['price'] ?? null) ? Money::toMinor(abs((float) $event['price']), 'USD') : null;
        $netUsd = $usd !== null ? $usd - (int) round($usd * $taxRate) - (int) round(($usd - (int) round($usd * $taxRate)) * $feeRate) : null;
        $occurredMs = $eventMs ?? (is_numeric($event['purchased_at_ms'] ?? null) ? (int) $event['purchased_at_ms'] : now()->getTimestampMs());

        RevenueRecorded::dispatch(
            $family->id,
            CommissionEntry::SOURCE_REVENUECAT,
            'revenuecat:'.(string) $event['id'],
            $sign < 0 ? RevenueRecorded::KIND_REFUND : RevenueRecorded::KIND_SALE,
            is_scalar($event['transaction_id'] ?? null) ? (string) $event['transaction_id'] : null,
            Carbon::createFromTimestampMs($occurredMs)->toIso8601String(),
            $currency,
            $sign * $gross,
            $sign * $tax,
            $sign * $fee,
            $sign * ($gross - $tax - $fee),
            $netUsd !== null ? $sign * $netUsd : null,
            $offerCode,
        );
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
