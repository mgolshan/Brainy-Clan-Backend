<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Contracts\RevenueCatClient;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\WebhookEvent;
use App\Modules\Family\Models\Family;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Pulls the family's subscriber (`family-{id}`) from the RevenueCat REST API and applies the
 * `family` entitlement to `subscriptions` and the family tier (`POST /family/subscription/sync`,
 * scheduled reconciliation). Without `REVENUECAT_API_KEY`, or when RevenueCat cannot be reached,
 * nothing changes. The API reflects the current state, so the sync counts as the newest event
 * (older webhooks delivered afterwards are recorded as `stale`).
 */
class SyncSubscription
{
    public const string EVENT_TYPE = 'SYNC';

    public function __construct(
        private readonly RevenueCatClient $client,
        private readonly ApplyFamilyTier $applyTier,
    ) {}

    /**
     * @return bool whether the RevenueCat state was fetched and applied
     */
    public function handle(Family $family, string $source = ApplyFamilyTier::SOURCE_SYNC): bool
    {
        if (! $this->client->isConfigured()) {
            return false;
        }

        try {
            $subscriber = $this->client->subscriber('family-'.$family->id);
        } catch (RuntimeException $exception) {
            Log::warning('RevenueCat subscriber sync failed', ['family_id' => $family->id, 'error' => $exception->getMessage()]);

            return false;
        }

        $fetchedAt = now();

        DB::transaction(function () use ($family, $subscriber, $fetchedAt, $source): void {
            /** @var Family $family */
            $family = Family::query()->lockForUpdate()->findOrFail($family->id);
            $subscription = Subscription::query()->where('family_id', $family->id)->lockForUpdate()->first();
            $entitlementId = (string) config('services.revenuecat.entitlement', 'family');
            $entitlements = is_array($subscriber['entitlements'] ?? null) ? $subscriber['entitlements'] : [];
            $entitlement = is_array($entitlements[$entitlementId] ?? null) ? $entitlements[$entitlementId] : null;

            if ($entitlement === null) {
                // Never had the entitlement: keep a missing row missing; end a stored one.
                if ($subscription === null) {
                    return;
                }

                $subscription->fill(['status' => Subscription::STATUS_EXPIRED, 'will_renew' => false]);
            } else {
                $subscription ??= new Subscription([
                    'family_id' => $family->id,
                    'provider' => WebhookEvent::PROVIDER_REVENUECAT,
                    'app_user_id' => 'family-'.$family->id,
                    'entitlement' => $entitlementId,
                ]);

                $this->fill($subscription, $entitlement, $subscriber, $fetchedAt);
            }

            $subscription->fill([
                'last_event_ms' => max($fetchedAt->getTimestampMs(), (int) $subscription->last_event_ms),
                'last_event_type' => self::EVENT_TYPE,
            ]);

            $this->applyTier->handle($family, $subscription, $source);
        });

        return true;
    }

    /**
     * @param  array<string, mixed>  $entitlement  `subscriber.entitlements.{id}`
     * @param  array<string, mixed>  $subscriber
     */
    private function fill(Subscription $subscription, array $entitlement, array $subscriber, Carbon $now): void
    {
        $productId = is_string($entitlement['product_identifier'] ?? null) ? $entitlement['product_identifier'] : $subscription->product_id;
        $subscriptions = is_array($subscriber['subscriptions'] ?? null) ? $subscriber['subscriptions'] : [];
        $product = $productId !== null && is_array($subscriptions[$productId] ?? null) ? $subscriptions[$productId] : [];

        $expiresAt = $this->time($entitlement['expires_date'] ?? null);
        $graceExpiresAt = $this->time($entitlement['grace_period_expires_date'] ?? $product['grace_period_expires_date'] ?? null);
        $billingIssue = ($product['billing_issues_detected_at'] ?? null) !== null;
        $nonRenewing = ($product === [] && $expiresAt === null) || ($product['period_type'] ?? null) === 'prepaid';

        $status = match (true) {
            $billingIssue && $graceExpiresAt !== null && $graceExpiresAt->greaterThan($now) => Subscription::STATUS_GRACE,
            $expiresAt === null || $expiresAt->greaterThan($now) => Subscription::STATUS_ACTIVE,
            default => Subscription::STATUS_EXPIRED,
        };

        $subscription->fill([
            'status' => $status,
            'store' => is_string($product['store'] ?? null) ? strtolower($product['store']) : $subscription->store,
            'product_id' => $productId,
            'period_type' => is_string($product['period_type'] ?? null) ? strtolower($product['period_type']) : $subscription->period_type,
            'environment' => array_key_exists('is_sandbox', $product) ? ($product['is_sandbox'] ? 'SANDBOX' : 'PRODUCTION') : $subscription->environment,
            'will_renew' => $status !== Subscription::STATUS_EXPIRED && ! $nonRenewing && ($product['unsubscribe_detected_at'] ?? null) === null,
            'purchased_at' => $this->time($entitlement['purchase_date'] ?? null) ?? $subscription->purchased_at,
            'expires_at' => $expiresAt,
            'grace_expires_at' => $status === Subscription::STATUS_GRACE ? $graceExpiresAt : null,
            'raw' => ['entitlement' => $entitlement, 'subscription' => $product],
        ]);
    }

    private function time(mixed $value): ?Carbon
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value)->utc() : null;
    }
}
