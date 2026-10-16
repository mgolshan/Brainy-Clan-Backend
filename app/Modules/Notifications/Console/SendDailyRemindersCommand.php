<?php

namespace App\Modules\Notifications\Console;

use App\Modules\Notifications\Actions\SendDailyReminders;
use Illuminate\Console\Command;

class SendDailyRemindersCommand extends Command
{
    protected $signature = 'notifications:daily-reminders';

    protected $description = 'Send the daily_reminder push to members whose reminder time is now (family timezone) and who have not played today';

    public function handle(SendDailyReminders $send): int
    {
        $this->info('Reminded '.$send->handle().' member(s).');

        return self::SUCCESS;
    }
}
