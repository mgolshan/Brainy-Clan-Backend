<?php

namespace App\Modules\Play;

use App\Modules\Core\ModuleServiceProvider;
use App\Modules\Play\Console\AbandonIdleSessionsCommand;
use App\Modules\Play\Support\FreeSecondChanceCounter;
use App\Modules\Policy\Contracts\SecondChanceUsage;
use Illuminate\Console\Scheduling\Schedule;

class PlayServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SecondChanceUsage::class, FreeSecondChanceCounter::class);
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([AbandonIdleSessionsCommand::class]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command('play:abandon-idle-sessions')->hourly()->withoutOverlapping()->onOneServer();
            });
        }
    }
}
