<?php

namespace App\Modules\Billing\Listeners;

use App\Modules\Billing\Actions\RecordRevenue;
use App\Modules\Billing\Events\RevenueRecorded;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued: first-paid date, attribution and commission for every revenue event.
 */
class RecordCommission implements ShouldQueue
{
    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(private readonly RecordRevenue $record) {}

    public function handle(RevenueRecorded $event): void
    {
        $this->record->handle($event);
    }
}
