<?php

namespace Laravel\Pennant;

use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;

class PennantServiceProvider extends ServiceProvider
{
    /**
     * Register the package's services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(FeatureManager::class, fn ($app) => new FeatureManager($app));

        $this->mergeConfigFrom(__DIR__.'/../config/pennant.php', 'pennant');
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
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'laravel-pennant-migrations');

            $this->commands([
                \Laravel\Pennant\Commands\PurgeCommand::class,
            ]);
        }

        $this->app['events']->listen([
            \Laravel\Octane\Events\RequestReceived::class,
            \Laravel\Octane\Events\TaskReceived::class,
            \Laravel\Octane\Events\TickReceived::class,
        ], fn () => $this->app[FeatureManager::class]
            ->setContainer(Container::getInstance())
            ->flushCache());

        $this->app['events']->listen([
            \Illuminate\Queue\Events\JobProcessed::class,
        ], fn () => $this->app[FeatureManager::class]->flushCache());
    }
}
