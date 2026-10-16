<?php

namespace App\Modules\Billing\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A family's subscription was updated (webhook, sync or reconciliation) and its tier applied.
 */
class SubscriptionChanged
{
    use Dispatchable;

    public function __construct(
        public readonly int $familyId,
        public readonly string $status,
        public readonly string $tier,
        public readonly bool $tierChanged,
        public readonly string $source,
    ) {}
}
