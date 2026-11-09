<?php

namespace App\Modules\Support;

use App\Modules\Core\ModuleServiceProvider;
use App\Modules\Support\Console\PruneSupportMessagesCommand;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Support messages (docs/API.md "Support messages", docs/PLAN.md "Support channel without a public
 * mailto", 2026-11-08): `POST/GET /support/messages` for family admins, the website contact form on
 * `/support` (routes/web.php → WebsiteController), mails to the support inbox, staff replies from
 * the Filament "Support inbox". `support:prune` runs daily (retention: closed 1 year after
 * closing, anything 2 years after the last activity).
 */
class SupportServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        // Website contact form: a coarse flood guard on every POST (bots included). The real
        // limit — 5 stored messages per hour per IP — is checked by SubmitWebsiteSupportMessage
        // and answered with the form and a note, not an error page.
        RateLimiter::for('support-form', fn (Request $request) => Limit::perHour(30)->by('support-form|'.$request->ip()));

        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([PruneSupportMessagesCommand::class]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command('support:prune')->dailyAt('03:40')->withoutOverlapping()->onOneServer();
            });
        }
    }
}
