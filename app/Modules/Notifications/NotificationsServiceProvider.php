<?php

namespace App\Modules\Notifications;

use App\Modules\Chat\Events\MessagePosted;
use App\Modules\Chat\Events\MessageReported;
use App\Modules\Core\ModuleServiceProvider;
use App\Modules\Goals\Events\GoalReached;
use App\Modules\Goals\Events\PeriodClosed;
use App\Modules\Notifications\Console\SendDailyRemindersCommand;
use App\Modules\Notifications\Contracts\ExpoPushSender;
use App\Modules\Notifications\Listeners\NotifyFamilyActivity;
use App\Modules\Notifications\Listeners\NotifyFamilyChat;
use App\Modules\Notifications\Listeners\NotifyFamilyChatReport;
use App\Modules\Notifications\Listeners\NotifyGoalReached;
use App\Modules\Notifications\Listeners\NotifyPeriodClosed;
use App\Modules\Notifications\Support\HttpExpoPushSender;
use App\Modules\Play\Events\SessionCompleted;
use Illuminate\Console\Scheduling\Schedule;

class NotificationsServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        GoalReached::class => [NotifyGoalReached::class],
        PeriodClosed::class => [NotifyPeriodClosed::class],
        SessionCompleted::class => [NotifyFamilyActivity::class],
        MessagePosted::class => [NotifyFamilyChat::class],
        MessageReported::class => [NotifyFamilyChatReport::class],
    ];

    public function register(): void
    {
        $this->app->bind(ExpoPushSender::class, HttpExpoPushSender::class);
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([SendDailyRemindersCommand::class]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command('notifications:daily-reminders')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
            });
        }
    }
}
