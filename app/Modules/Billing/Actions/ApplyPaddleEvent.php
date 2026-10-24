<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Events\RevenueRecorded;
use App\Modules\Billing\Models\CommissionEntry;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\WebhookEvent;
use App\Modules\Billing\Support\PaddleSubscriptionState;
use App\Modules\Family\Models\Family;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies one Paddle Billing webhook (docs/API.md "Web checkout (Paddle)").
 *
 * - `subscription.created|activated|updated|past_due|paused|resumed|canceled` → the family's
 *   `paddle` subscription row ({@see PaddleSubscriptionState}) and tier ({@see ApplyFamilyTier});
 *   events older than the last applied one are `stale`.
 * - `transaction.completed` → a sale ({@see RevenueRecorded}; attribution from `custom_data`).
 * - `adjustment.created|updated` → an approved refund / chargeback (negative) or chargeback
 *   reversal ({@see RevenueRecorded}); the subscription state comes from subscription events.
 *
 * The family comes from `custom_data.family_id` (set when the server created the transaction;
 * Paddle copies it to the subscription), else from the stored Paddle subscription id. Idempotent
 * by `event_id` (`webhook_events`).
 */
class ApplyPaddleEvent
{
    public const array SUBSCRIPTION_EVENTS = [
        'subscription.created', 'subscription.activated', 'subscription.updated', 'subscription.past_due',
        'subscription.paused', 'subscription.resumed', 'subscription.canceled',
    ];

    public const array ADJUSTMENT_EVENTS = ['adjustment.created', 'adjustment.updated'];

    /** Adjustment actions that move money for commissions, and the revenue kind they map to. */
    private const array ADJUSTMENT_KINDS = [
        'refund' => RevenueRecorded::KIND_REFUND,
        'chargeback' => RevenueRecorded::KIND_CHARGEBACK,
        'chargeback_reverse' => RevenueRecorded::KIND_CHARGEBACK_REVERSE,
    ];

    public function __construct(private readonly ApplyFamilyTier $applyTier) {}

    /**
     * Returns the stored event; `wasRecentlyCreated` is false for a duplicate delivery.
     *
     * @param  array<string, mixed>  $payload  `{ event_id, event_type, occurred_at, data }`
     */
    public function handle(array $payload): WebhookEvent
    {
        $eventId = is_scalar($payload['event_id'] ?? null) ? (string) $payload['event_id'] : '';
        $type = is_string($payload['event_type'] ?? null) ? $payload['event_type'] : null;

        $existing = $this->find($eventId);

        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($payload, $eventId, $type): WebhookEvent {
                $record = WebhookEvent::query()->create([
                    'provider' => WebhookEvent::PROVIDER_PADDLE,
                    'event_id' => $eventId,
                    'type' => $type,
                    'payload' => $payload,
                ]);

                /** @var array<string, mixed> $data */
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                $occurredAt = PaddleSubscriptionState::time($payload['occurred_at'] ?? null) ?? now();

                [$outcome, $note, $familyId] = match (true) {
                    in_array($type, self::SUBSCRIPTION_EVENTS, true) => $this->subscription($data, (string) $type, $occurredAt),
                    $type === 'transaction.completed' => $this->transaction($data, $eventId, $occurredAt),
                    in_array($type, self::ADJUSTMENT_EVENTS, true) => $this->adjustment($data, $occurredAt),
                    default => [WebhookEvent::OUTCOME_IGNORED, 'Unhandled event type.', null],
                };

                $record->forceFill([
                    'family_id' => $familyId,
                    'outcome' => $outcome,
                    'note' => $note,
                    'processed_at' => now(),
                ])->save();

                return $record;
            });
        } catch (UniqueConstraintViolationException) {
            return $this->find($eventId) ?? throw new \RuntimeException('Webhook event vanished.');
        }
    }

    private function find(string $eventId): ?WebhookEvent
    {
        return WebhookEvent::query()
            ->where('provider', WebhookEvent::PROVIDER_PADDLE)
            ->where('event_id', $eventId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: string|null, 2: int|null}
     */
    private function subscription(array $data, string $type, Carbon $occurredAt): array
    {
        $subscriptionId = is_string($data['id'] ?? null) ? $data['id'] : null;
        $family = $this->family($data, $subscriptionId);

        if ($family === null) {
            return [WebhookEvent::OUTCOME_IGNORED, 'Unknown family.', null];
        }

        $subscription = Subscription::query()->where('family_id', $family->id)->where('provider', Subscription::PROVIDER_PADDLE)->lockForUpdate()->first()
            ?? new Subscription(['family_id' => $family->id, 'provider' => Subscription::PROVIDER_PADDLE]);

        $incomingEntitled = in_array($data['status'] ?? null, PaddleSubscriptionState::ENTITLED_STATUSES, true);

        // An old Paddle subscription ending must not end the family's newer one.
        if ($subscription->exists && $subscription->external_id !== null && $subscription->external_id !== $subscriptionId
            && $subscription->isEntitled() && ! $incomingEntitled) {
            return [WebhookEvent::OUTCOME_IGNORED, 'Another Paddle subscription of this family is active.', $family->id];
        }

        $eventMs = $occurredAt->getTimestampMs();

        if ($subscription->last_event_ms !== null && $eventMs < $subscription->last_event_ms) {
            return [WebhookEvent::OUTCOME_STALE, 'Older than the last applied event.', $family->id];
        }

        PaddleSubscriptionState::fill($subscription, $data, $family->id, $eventMs, $type);
        $this->applyTier->handle($family, $subscription, ApplyFamilyTier::SOURCE_WEBHOOK);

        $note = null;

        if ($subscription->isEntitled() && Subscription::entitledElsewhere($family->id, Subscription::PROVIDER_PADDLE) !== null) {
            // Web checkout refuses a family with a store plan; this only happens in a race. Both are kept.
            $note = 'Conflict: the family also has an active store plan.';
            Log::warning('Web plan while a store plan is active', ['family_id' => $family->id, 'subscription_id' => $subscriptionId]);
        }

        return [WebhookEvent::OUTCOME_APPLIED, $note, $family->id];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: string|null, 2: int|null}
     */
    private function transaction(array $data, string $eventId, Carbon $occurredAt): array
    {
        $subscriptionId = is_string($data['subscription_id'] ?? null) ? $data['subscription_id'] : null;
        $family = $this->family($data, $subscriptionId);

        if ($family === null) {
            return [WebhookEvent::OUTCOME_IGNORED, 'Unknown family.', null];
        }

        $details = is_array($data['details'] ?? null) ? $data['details'] : [];
        $totals = is_array($details['totals'] ?? null) ? $details['totals'] : [];
        $payoutTotals = is_array($details['payout_totals'] ?? null) ? $details['payout_totals'] : [];
        $customData = is_array($data['custom_data'] ?? null) ? $data['custom_data'] : [];
        $currency = $this->currency($totals['currency_code'] ?? $data['currency_code'] ?? null);

        if ($currency === null) {
            return [WebhookEvent::OUTCOME_IGNORED, 'No totals.', $family->id];
        }

        $affiliateCode = is_string($customData['affiliate_code'] ?? null) && $customData['affiliate_code'] !== '' ? $customData['affiliate_code'] : null;
        $affiliateSource = is_string($customData['affiliate_source'] ?? null) ? $customData['affiliate_source'] : null;

        RevenueRecorded::dispatch(
            $family->id,
            CommissionEntry::SOURCE_PADDLE,
            'paddle:'.$eventId,
            RevenueRecorded::KIND_SALE,
            is_string($data['id'] ?? null) ? $data['id'] : null,
            (PaddleSubscriptionState::time($data['billed_at'] ?? null) ?? $occurredAt)->toIso8601String(),
            $currency,
            $this->amount($totals['total'] ?? null),
            $this->amount($totals['tax'] ?? null),
            $this->amount($totals['fee'] ?? null),
            $this->amount($totals['earnings'] ?? null),
            $this->usdEarnings($totals, $payoutTotals),
            null,
            $affiliateCode,
            $affiliateSource,
        );

        return [WebhookEvent::OUTCOME_APPLIED, null, $family->id];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: string|null, 2: int|null}
     */
    private function adjustment(array $data, Carbon $occurredAt): array
    {
        $action = is_string($data['action'] ?? null) ? $data['action'] : '';
        $kind = self::ADJUSTMENT_KINDS[$action] ?? null;

        if ($kind === null || ($data['status'] ?? null) !== 'approved') {
            return [WebhookEvent::OUTCOME_IGNORED, 'Not an approved refund or chargeback.', null];
        }

        $transactionId = is_string($data['transaction_id'] ?? null) ? $data['transaction_id'] : null;
        $subscriptionId = is_string($data['subscription_id'] ?? null) ? $data['subscription_id'] : null;
        $family = $this->family($data, $subscriptionId) ?? $this->familyForTransaction($transactionId);

        if ($family === null) {
            return [WebhookEvent::OUTCOME_IGNORED, 'Unknown family.', null];
        }

        $totals = is_array($data['totals'] ?? null) ? $data['totals'] : [];
        $payoutTotals = is_array($data['payout_totals'] ?? null) ? $data['payout_totals'] : [];
        $currency = $this->currency($totals['currency_code'] ?? null);
        $adjustmentId = is_string($data['id'] ?? null) ? $data['id'] : null;

        if ($currency === null || $adjustmentId === null) {
            return [WebhookEvent::OUTCOME_IGNORED, 'No totals.', $family->id];
        }

        $sign = $kind === RevenueRecorded::KIND_CHARGEBACK_REVERSE ? 1 : -1;
        $usd = $this->usdEarnings($totals, $payoutTotals);

        // One revenue event per adjustment (keyed by its id): `created` and `updated` both approved add it once.
        RevenueRecorded::dispatch(
            $family->id,
            CommissionEntry::SOURCE_PADDLE,
            'paddle:'.$adjustmentId,
            $kind,
            $transactionId,
            $occurredAt->toIso8601String(),
            $currency,
            $sign * abs($this->amount($totals['total'] ?? null)),
            $sign * abs($this->amount($totals['tax'] ?? null)),
            $sign * abs($this->amount($totals['fee'] ?? null)),
            $sign * abs($this->amount($totals['earnings'] ?? null)),
            $usd !== null ? $sign * abs($usd) : null,
        );

        return [WebhookEvent::OUTCOME_APPLIED, null, $family->id];
    }

    /**
     * The family from `custom_data.family_id`, else the one whose Paddle subscription this is.
     *
     * @param  array<string, mixed>  $data
     */
    private function family(array $data, ?string $subscriptionId): ?Family
    {
        $customData = is_array($data['custom_data'] ?? null) ? $data['custom_data'] : [];
        $familyId = $customData['family_id'] ?? null;

        if ((is_int($familyId) || (is_string($familyId) && ctype_digit($familyId))) && (int) $familyId > 0) {
            $family = Family::query()->lockForUpdate()->find((int) $familyId);

            if ($family !== null) {
                return $family;
            }
        }

        if ($subscriptionId === null) {
            return null;
        }

        $owner = Subscription::query()->where('provider', Subscription::PROVIDER_PADDLE)->where('external_id', $subscriptionId)->value('family_id');

        return $owner !== null ? Family::query()->lockForUpdate()->find($owner) : null;
    }

    /**
     * The family of an earlier `transaction.completed` webhook for this transaction.
     */
    private function familyForTransaction(?string $transactionId): ?Family
    {
        if ($transactionId === null) {
            return null;
        }

        $familyId = WebhookEvent::query()
            ->where('provider', WebhookEvent::PROVIDER_PADDLE)
            ->where('type', 'transaction.completed')
            ->whereNotNull('family_id')
            ->where('payload->data->id', $transactionId)
            ->value('family_id');

        return $familyId !== null ? Family::query()->lockForUpdate()->find($familyId) : null;
    }

    private function currency(mixed $code): ?string
    {
        return is_string($code) && strlen($code) === 3 ? strtoupper($code) : null;
    }

    /**
     * Paddle amounts are strings in the currency's lowest unit ("4999").
     */
    private function amount(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Earnings in USD when Paddle gives them: the payout totals in USD, or totals already in USD.
     *
     * @param  array<string, mixed>  $totals
     * @param  array<string, mixed>  $payoutTotals
     */
    private function usdEarnings(array $totals, array $payoutTotals): ?int
    {
        if (($payoutTotals['currency_code'] ?? null) === 'USD' && is_numeric($payoutTotals['earnings'] ?? null)) {
            return (int) $payoutTotals['earnings'];
        }

        if (($totals['currency_code'] ?? null) === 'USD' && is_numeric($totals['earnings'] ?? null)) {
            return (int) $totals['earnings'];
        }

        return null;
    }
}
