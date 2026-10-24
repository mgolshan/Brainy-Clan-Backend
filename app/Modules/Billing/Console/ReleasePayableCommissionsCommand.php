<?php

namespace App\Modules\Billing\Console;

use App\Modules\Billing\Actions\ReleasePayableCommissions;
use Illuminate\Console\Command;

class ReleasePayableCommissionsCommand extends Command
{
    protected $signature = 'billing:release-commissions';

    protected $description = 'Move affiliate commission entries past their refund window from pending to payable';

    public function handle(ReleasePayableCommissions $release): int
    {
        $this->info('Released '.$release->handle().' commission entr(y/ies).');

        return self::SUCCESS;
    }
}
