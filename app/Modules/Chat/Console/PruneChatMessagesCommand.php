<?php

namespace App\Modules\Chat\Console;

use App\Modules\Chat\Actions\PruneChatMessages;
use Illuminate\Console\Command;

class PruneChatMessagesCommand extends Command
{
    protected $signature = 'chat:prune';

    protected $description = 'Delete family chat messages older than each family\'s retention (free 7 days, Family plan 365 days)';

    public function handle(PruneChatMessages $prune): int
    {
        $this->info('Deleted '.$prune->handle().' chat message(s).');

        return self::SUCCESS;
    }
}
