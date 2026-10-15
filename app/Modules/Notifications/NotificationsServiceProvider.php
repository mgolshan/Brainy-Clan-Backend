<?php

namespace App\Modules\Notifications;

use App\Modules\Core\ModuleServiceProvider;
use App\Modules\Goals\Events\GoalReached;
use App\Modules\Goals\Events\PeriodClosed;
use App\Modules\Notifications\Contracts\ExpoPushSender;
use App\Modules\Notifications\Listeners\NotifyGoalReached;
use App\Modules\Notifications\Listeners\NotifyPeriodClosed;
use App\Modules\Notifications\Support\HttpExpoPushSender;

class NotificationsServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        GoalReached::class => [NotifyGoalReached::class],
        PeriodClosed::class => [NotifyPeriodClosed::class],
    ];

    public function register(): void
    {
        $this->app->bind(ExpoPushSender::class, HttpExpoPushSender::class);
    }
}
