<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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
         * No N+1 queries (docs/PLAN.md): outside production a lazy-loaded relation throws, so a
         * missing eager load fails the test suite. Laravel only flags models that were loaded
         * together with others (a single fetched model may still load relations lazily).
         * Mass-assignment behaviour is unchanged (no shouldBeStrict / silent-discard checks).
         */
        Model::preventLazyLoading(! $this->app->isProduction());

        /*
         * API docs (Scramble, /docs/api): its RestrictedDocsAccess middleware always allows the
         * local environment; everywhere else this gate decides. Closed outside local for now.
         */
        Gate::define('viewApiDocs', fn ($user = null): bool => $this->app->environment('local'));
    }
}
