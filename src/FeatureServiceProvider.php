<?php

namespace Laravel\Feature;

use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;

class FeatureServiceProvider extends ServiceProvider
{
    /**
     * Register the package's services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(FeatureManager::class, fn ($app) => new FeatureManager($app));

        $this->mergeConfigFrom(__DIR__.'/../config/features.php', 'features');
    }

    /**
     * Bootstrap the package's services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'laravel-feature-migrations');

            $this->commands([
                \Laravel\Feature\Commands\PurgeCommand::class,
            ]);
        }

        $this->app['events']->listen([
            \Laravel\Octane\Events\RequestReceived::class,
            \Laravel\Octane\Events\TaskReceived::class,
            \Laravel\Octane\Events\TickReceived::class,
        ], function () {
            $this->app[FeatureManager::class]->setContainer(Container::getInstance());
            $this->app[FeatureManager::class]->forgetDrivers();
        });

        $this->app['events']->listen([
            \Illuminate\Queue\Events\JobProcessed::class,
        ], fn () => $this->app[FeatureManager::class]->flushCache());
    }
}
