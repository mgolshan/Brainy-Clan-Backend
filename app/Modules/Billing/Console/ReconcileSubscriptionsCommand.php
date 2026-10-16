<?php

namespace App\Modules\Billing\Console;

use App\Modules\Billing\Actions\ReconcileSubscriptions;
use Illuminate\Console\Command;

class ReconcileSubscriptionsCommand extends Command
{
    protected $signature = 'billing:reconcile-subscriptions';

    protected $description = 'Re-sync or expire subscriptions whose expiry passed without an EXPIRATION webhook';

    public function handle(ReconcileSubscriptions $reconcile): int
    {
        $this->info('Reconciled '.$reconcile->handle().' subscription(s).');

        return self::SUCCESS;
    }
}
