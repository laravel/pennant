<?php

namespace Laravel\Feature;

use Illuminate\Support\ServiceProvider;
use Laravel\Feature\Commands\PruneCommand;

class FeatureServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
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
                PruneCommand::class,
            ]);
        }
    }
}
