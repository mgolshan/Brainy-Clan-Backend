<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * API docs (Scramble, /docs/api): its RestrictedDocsAccess middleware always allows the
         * local environment; everywhere else this gate decides. Closed outside local for now.
         */
        Gate::define('viewApiDocs', fn ($user = null): bool => $this->app->environment('local'));
    }
}
