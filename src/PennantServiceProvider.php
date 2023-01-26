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
            $this->offerPublishing();

            $this->commands([
                \Laravel\Pennant\Commands\PurgeCommand::class,
            ]);
        }

        $this->app->afterResolving('blade', function ($blade) {
            $blade->if('feature', function ($value) {
                return Feature::active($value);
            });
        });


        $this->listenForEvents();
    }

    /**
     * Listen for the events relevant to the package.
     *
     * @return void
     */
    protected function listenForEvents()
    {
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

    /**
     * Register the migrations and publishing for the package.
     *
     * @return void
     */
    protected function offerPublishing()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/pennant.php' => config_path('pennant.php'),
        ], 'pennant-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
        ], 'laravel-pennant-migrations');
    }
}
