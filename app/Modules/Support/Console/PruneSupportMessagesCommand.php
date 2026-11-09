<?php

namespace App\Modules\Support\Console;

use App\Modules\Support\Actions\PruneSupportMessages;
use Illuminate\Console\Command;

class PruneSupportMessagesCommand extends Command
{
    protected $signature = 'support:prune';

    protected $description = 'Delete support messages 1 year after closing, and any support message 2 years after its last activity';

    public function handle(PruneSupportMessages $prune): int
    {
        $this->info('Deleted '.$prune->handle().' support message(s).');

        return self::SUCCESS;
    }
}
