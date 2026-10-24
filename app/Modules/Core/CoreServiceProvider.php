<?php

namespace App\Modules\Core;

use App\Modules\Core\Console\FlushReferenceCachesCommand;
use App\Modules\Core\Console\SchedulerHeartbeatCommand;
use App\Modules\Core\Listeners\ForwardLongWaitToWebhook;
use App\Modules\Core\Listeners\SendFailedJobAlert;
use App\Modules\Core\Support\ReferenceCache;
use App\Modules\Identity\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Horizon\Events\LongWaitDetected;

/**
 * Shared API plumbing: health endpoints, rate limiters
 * (`auth/*` 10/min per IP and per email, everything else 120/min per token), the reference
 * cache plumbing (ReferenceCache, `reference-cache:flush`) and ops monitoring (B11): failed-job
 * and long-wait alerts, the scheduler heartbeat and `horizon:snapshot`.
 */
class CoreServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        JobFailed::class => [SendFailedJobAlert::class],
        LongWaitDetected::class => [ForwardLongWaitToWebhook::class],
    ];

    public function boot(): void
    {
        // 10/min per IP, plus 10/min per email address so a password guesser spread over many
        // IPs is still slowed down on one account.
        RateLimiter::for('auth', function (Request $request) {
            $limits = [Limit::perMinute(10)->by('auth|'.$request->ip())];
            $email = $request->input('email');

            if (is_string($email) && trim($email) !== '') {
                $limits[] = Limit::perMinute(10)->by('auth-email|'.hash('sha256', mb_strtolower(trim($email))));
            }

            return $limits;
        });

        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();

            return Limit::perMinute(120)->by($user instanceof User ? 'user|'.$user->id : 'ip|'.$request->ip());
        });

        // GET /me/export builds a full copy of a user's data: 1 per hour per user.
        RateLimiter::for('export', function (Request $request) {
            $user = $request->user();

            return Limit::perHour(1)->by($user instanceof User ? 'export|'.$user->id : 'export-ip|'.$request->ip());
        });

        // Provider webhooks (RevenueCat, AdMob SSV): generous, only a guard against floods.
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(600)->by('webhooks|'.$request->ip()));

        // Public account deletion page: 5 form posts per hour per IP.
        RateLimiter::for('account-deletion', fn (Request $request) => Limit::perHour(5)->by('account-deletion|'.$request->ip()));

        // Password reset web form (/password/reset): 10 posts per hour per IP.
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perHour((int) config('auth.password_forgot.reset_per_ip', 10))->by('password-reset|'.$request->ip()));

        // Deep health check (uptime monitor): 30/min per IP by default.
        RateLimiter::for('health-deep', fn (Request $request) => Limit::perMinute((int) config('ops.health.deep_rate_per_minute', 30))->by('health-deep|'.$request->ip()));

        // A migration may change reference rows with raw SQL: start from fresh reference caches.
        Event::listen(MigrationsEnded::class, fn () => ReferenceCache::flush(...ReferenceCache::ALL));

        if ($this->app->runningInConsole()) {
            $this->commands([FlushReferenceCachesCommand::class, SchedulerHeartbeatCommand::class]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                // Heartbeat for the deep health check (no onOneServer: every scheduler should beat).
                $schedule->command('ops:heartbeat')->everyMinute();
                // Horizon metrics graphs (throughput / runtime per job and queue).
                $schedule->command('horizon:snapshot')->everyFiveMinutes()->onOneServer();
            });
        }

        parent::boot();
    }
}
