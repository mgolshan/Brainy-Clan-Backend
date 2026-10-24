<?php

namespace App\Modules\Billing;

use App\Modules\Billing\Console\ReconcileSubscriptionsCommand;
use App\Modules\Billing\Console\ReleasePayableCommissionsCommand;
use App\Modules\Billing\Contracts\AdMobVerifierKeys;
use App\Modules\Billing\Contracts\PaddleClient;
use App\Modules\Billing\Contracts\RevenueCatClient;
use App\Modules\Billing\Events\RevenueRecorded;
use App\Modules\Billing\Listeners\RecordCommission;
use App\Modules\Billing\Support\HttpAdMobVerifierKeys;
use App\Modules\Billing\Support\HttpPaddleClient;
use App\Modules\Billing\Support\HttpRevenueCatClient;
use App\Modules\Core\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class BillingServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        RevenueRecorded::class => [RecordCommission::class],
    ];

    public function register(): void
    {
        $this->app->bind(RevenueCatClient::class, HttpRevenueCatClient::class);
        $this->app->bind(PaddleClient::class, HttpPaddleClient::class);
        $this->app->bind(AdMobVerifierKeys::class, HttpAdMobVerifierKeys::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Website subscribe form (docs/API.md): 5 posts per hour per IP and 3 per hour per email.
        RateLimiter::for('web-subscribe', function (Request $request) {
            $limits = [Limit::perHour((int) config('billing.web_checkout.per_ip_per_hour', 5))->by('web-subscribe|'.$request->ip())];
            $email = $request->input('email');

            if (is_string($email) && trim($email) !== '') {
                $limits[] = Limit::perHour((int) config('billing.web_checkout.per_email_per_hour', 3))
                    ->by('web-subscribe-email|'.hash('sha256', mb_strtolower(trim($email))));
            }

            return $limits;
        });

        // The emailed checkout links: generous, only a guard against token guessing.
        RateLimiter::for('web-checkout', fn (Request $request) => Limit::perHour(30)->by('web-checkout|'.$request->ip()));

        if ($this->app->runningInConsole()) {
            $this->commands([ReconcileSubscriptionsCommand::class, ReleasePayableCommissionsCommand::class]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command('billing:reconcile-subscriptions')->hourly()->withoutOverlapping()->onOneServer();
                $schedule->command('billing:release-commissions')->dailyAt('03:30')->withoutOverlapping()->onOneServer();
            });
        }
    }
}
