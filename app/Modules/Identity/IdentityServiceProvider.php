<?php

namespace App\Modules\Identity;

use App\Modules\Core\ModuleServiceProvider;
use App\Modules\Identity\Apple\AppleTokenClient;
use App\Modules\Identity\Apple\HttpAppleTokenClient;
use App\Modules\Identity\Console\SetStaffRoleCommand;
use App\Modules\Identity\Console\UnlockSignInCommand;
use Illuminate\Console\Scheduling\Schedule;

class IdentityServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->bind(AppleTokenClient::class, HttpAppleTokenClient::class);
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([UnlockSignInCommand::class, SetStaffRoleCommand::class]);

            // Expired password reset links (password_reset_tokens).
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command('auth:clear-resets')->daily()->onOneServer();
            });
        }
    }
}
