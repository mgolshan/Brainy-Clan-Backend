<?php

namespace App\Modules\Goals\Console;

use App\Modules\Goals\Actions\CloseGoalPeriods;
use Illuminate\Console\Command;

class CloseGoalPeriodsCommand extends Command
{
    protected $signature = 'goals:close-periods';

    protected $description = 'Close ended goal periods, write their results and open the next periods';

    public function handle(CloseGoalPeriods $close): int
    {
        $closed = $close->handle();
        $this->info('Closed '.$closed.' goal period(s).');

        return self::SUCCESS;
    }
}
