<?php

namespace Laravel\Feature;

use Illuminate\Support\ServiceProvider;
use Laravel\Feature\Commands\PurgeCommand;

class FeatureServiceProvider extends ServiceProvider
{
    /**
     * The singletons to register into the container.
     *
     * @var array
     */
    public $singletons = [
        FeatureManager::class,
    ];

    /**
     * Register the package's services.
     *
     * @return void
     */
    public function register()
    {
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
                PurgeCommand::class,
            ]);
        }
    }
}
