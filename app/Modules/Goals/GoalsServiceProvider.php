<?php

namespace App\Modules\Goals;

use App\Modules\Core\ModuleServiceProvider;
use App\Modules\Goals\Console\CloseGoalPeriodsCommand;
use Illuminate\Console\Scheduling\Schedule;

class GoalsServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([CloseGoalPeriodsCommand::class]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command('goals:close-periods')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
            });
        }
    }
}
