<?php

namespace App\Modules\Goals;

use App\Modules\Core\ModuleServiceProvider;
use App\Modules\Family\Events\FamilyDeleted;
use App\Modules\Family\Events\MemberJoined;
use App\Modules\Family\Events\MemberRemoved;
use App\Modules\Goals\Console\CloseGoalPeriodsCommand;
use App\Modules\Goals\Listeners\AddToEveryoneGoalPlans;
use App\Modules\Goals\Listeners\DeleteFamilyGoalPlans;
use App\Modules\Goals\Listeners\RemoveFromGoalPlans;
use Illuminate\Console\Scheduling\Schedule;

class GoalsServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        MemberJoined::class => [AddToEveryoneGoalPlans::class],
        MemberRemoved::class => [RemoveFromGoalPlans::class],
        FamilyDeleted::class => [DeleteFamilyGoalPlans::class],
    ];

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
