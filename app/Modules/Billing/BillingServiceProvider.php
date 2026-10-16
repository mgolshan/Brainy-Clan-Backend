<?php

namespace App\Modules\Billing;

use App\Modules\Billing\Console\ReconcileSubscriptionsCommand;
use App\Modules\Billing\Contracts\AdMobVerifierKeys;
use App\Modules\Billing\Contracts\RevenueCatClient;
use App\Modules\Billing\Support\HttpAdMobVerifierKeys;
use App\Modules\Billing\Support\HttpRevenueCatClient;
use App\Modules\Core\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class BillingServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RevenueCatClient::class, HttpRevenueCatClient::class);
        $this->app->bind(AdMobVerifierKeys::class, HttpAdMobVerifierKeys::class);
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([ReconcileSubscriptionsCommand::class]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command('billing:reconcile-subscriptions')->hourly()->withoutOverlapping()->onOneServer();
            });
        }
    }
}
