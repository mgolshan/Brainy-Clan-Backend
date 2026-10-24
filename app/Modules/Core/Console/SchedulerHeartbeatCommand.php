<?php

namespace App\Modules\Core\Console;

use App\Modules\Core\Support\SchedulerHeartbeat;
use Illuminate\Console\Command;

/**
 * Scheduled every minute: proves the scheduler runs (checked by `GET /api/v1/health/deep`).
 */
class SchedulerHeartbeatCommand extends Command
{
    protected $signature = 'ops:heartbeat';

    protected $description = 'Write the scheduler heartbeat timestamp (checked by the deep health check)';

    public function handle(SchedulerHeartbeat $heartbeat): int
    {
        $heartbeat->beat();

        return self::SUCCESS;
    }
}
