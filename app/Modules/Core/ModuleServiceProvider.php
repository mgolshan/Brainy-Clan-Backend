<?php

namespace App\Modules\Core;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

/**
 * Base class for every module's service provider.
 *
 * - Loads the module's `Http/routes.php` under `/api/v1` with the `api` middleware group.
 * - Registers the module's event listeners from `$listen` (modules talk through events).
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Event class => listener classes.
     *
     * @var array<class-string, list<class-string>>
     */
    protected array $listen = [];

    public function boot(): void
    {
        $routes = $this->modulePath('Http/routes.php');

        if (is_file($routes) && ! $this->app->routesAreCached()) {
            Route::prefix('api/v1')
                ->middleware('api')
                ->group($routes);
        }

        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }

    protected function modulePath(string $path = ''): string
    {
        $file = (string) (new ReflectionClass(static::class))->getFileName();

        return dirname($file).($path !== '' ? '/'.$path : '');
    }
}
