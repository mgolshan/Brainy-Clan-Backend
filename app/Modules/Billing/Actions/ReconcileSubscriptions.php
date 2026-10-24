<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Contracts\PaddleClient;
use App\Modules\Billing\Contracts\RevenueCatClient;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Family\Models\Family;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Safety net for a missed EXPIRATION webhook (scheduled hourly): subscriptions still `active` /
 * `grace` whose expiry (`grace_expires_at` in the grace period, else `expires_at`) passed more than
 * `services.revenuecat.reconcile_after_minutes` ago. With `REVENUECAT_API_KEY` each is re-synced
 * from RevenueCat (a missed RENEWAL then keeps the family on its plan; an unreachable API leaves
 * it for the next run); without a key it is marked `expired` and the family goes back to `free`.
 * Paddle (web) rows are re-read from the Paddle API the same way (`PADDLE_API_KEY`), else expired.
 * All paths switch the tier through {@see ApplyFamilyTier}.
 */
class ReconcileSubscriptions
{
    public function __construct(
        private readonly RevenueCatClient $client,
        private readonly SyncSubscription $sync,
        private readonly ApplyFamilyTier $applyTier,
        private readonly PaddleClient $paddle,
        private readonly SyncPaddleSubscription $syncPaddle,
    ) {}

    /**
     * @return int how many subscriptions were reconciled (synced or expired)
     */
    public function handle(): int
    {
        $reconciled = 0;

        $this->overdue()->with('family')->chunkById(100, function ($subscriptions) use (&$reconciled): void {
            foreach ($subscriptions as $subscription) {
                /** @var Subscription $subscription */
                $done = match (true) {
                    $subscription->isPaddle() => $this->paddle->isConfigured() && $subscription->external_id !== null
                        ? $this->syncPaddle->handle($subscription)
                        : $this->expire($subscription),
                    $this->client->isConfigured() => $this->sync->handle($subscription->family, ApplyFamilyTier::SOURCE_RECONCILE),
                    default => $this->expire($subscription),
                };

                $reconciled += $done ? 1 : 0;
            }
        });

        return $reconciled;
    }

    /**
     * @return Builder<Subscription>
     */
    public function overdue(): Builder
    {
        $cutoff = now()->subMinutes((int) config('services.revenuecat.reconcile_after_minutes', 60));

        return Subscription::query()->where(function (Builder $query) use ($cutoff): void {
            $query->where(fn (Builder $q) => $q->where('status', Subscription::STATUS_ACTIVE)->where('expires_at', '<', $cutoff))
                ->orWhere(fn (Builder $q) => $q->where('status', Subscription::STATUS_GRACE)
                    ->whereRaw('coalesce(grace_expires_at, expires_at) < ?', [$cutoff]));
        });
    }

    private function expire(Subscription $subscription): bool
    {
        return DB::transaction(function () use ($subscription): bool {
            /** @var Family $family */
            $family = Family::query()->lockForUpdate()->findOrFail($subscription->family_id);
            $locked = $this->overdue()->whereKey($subscription->id)->lockForUpdate()->first();

            // A webhook renewed it meanwhile.
            if ($locked === null) {
                return false;
            }

            $locked->fill([
                'status' => Subscription::STATUS_EXPIRED,
                'will_renew' => false,
                'grace_expires_at' => null,
                'last_event_type' => 'RECONCILED_EXPIRATION',
            ]);

            $this->applyTier->handle($family, $locked, ApplyFamilyTier::SOURCE_RECONCILE);

            return true;
        });
    }
}
