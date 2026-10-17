<?php

namespace App\Modules\Play\Console;

use App\Modules\Play\Actions\AbandonIdleSessions;
use Illuminate\Console\Command;

class AbandonIdleSessionsCommand extends Command
{
    protected $signature = 'play:abandon-idle-sessions';

    protected $description = 'Abandon unfinished play sessions with no activity for 24 hours';

    public function handle(AbandonIdleSessions $abandon): int
    {
        $abandoned = $abandon->handle();
        $this->info('Abandoned '.$abandoned.' idle session(s).');

        return self::SUCCESS;
    }
}
