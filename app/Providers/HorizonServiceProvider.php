<?php

namespace App\Providers;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * "Long wait" notifications (thresholds: `waits` in config/horizon.php) are mailed to
     * `ops.alert_email` (OPS_ALERT_EMAIL); Horizon sends at most one per queue every 5 minutes.
     * The optional ops webhook gets them through `ForwardLongWaitToWebhook`, failed jobs through
     * `SendFailedJobAlert` (Core module) — Horizon has no failure notification and its Slack
     * channel needs a package this app does not install.
     */
    public function boot(): void
    {
        parent::boot();

        $email = config('ops.alert_email');

        if (is_string($email) && $email !== '') {
            Horizon::routeMailNotificationsTo($email);
        }
    }

    /**
     * Register the Horizon gate.
     *
     * Outside the local environment only admin staff (role `admin`) can see `/horizon` — it can
     * retry and delete jobs, so the read-only `support` role cannot. They sign in through `/admin` first.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user = null): bool => $user !== null && $user->isStaffAdmin());
    }
}
