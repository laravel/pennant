<?php

namespace Laravel\Pennant;

use Illuminate\Container\Container;
use Illuminate\Foundation\Events\PublishingStubs;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\TaskReceived;
use Laravel\Octane\Events\TickReceived;
use Laravel\Pennant\Commands\FeatureMakeCommand;
use Laravel\Pennant\Commands\PurgeCommand;

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
                FeatureMakeCommand::class,
                PurgeCommand::class,
            ]);
        }

        $this->callAfterResolving('blade.compiler', function ($blade) {
            $blade->if('feature', function ($feature, $value = null) {
                if (func_num_args() === 2) {
                    return Feature::value($feature) === $value;
                }

                return Feature::active($feature);
            });

            $blade->if('featureany', function ($features) {
                return Feature::someAreActive($features);
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
            RequestReceived::class,
            TaskReceived::class,
            TickReceived::class,
        ], fn () => $this->app[FeatureManager::class]
            ->setContainer(Container::getInstance())
            ->flushCache());

        $this->app['events']->listen([
            JobProcessed::class,
        ], fn () => $this->app[FeatureManager::class]->flushCache());

        $this->app['events']->listen([
            PublishingStubs::class,
        ], fn ($event) => $event->add(__DIR__.'/../stubs/feature.stub', 'feature.stub'));
    }

    /**
     * Register the migrations and publishing for the package.
     *
     * @return void
     */
    protected function offerPublishing()
    {
        $this->publishes([
            __DIR__.'/../config/pennant.php' => config_path('pennant.php'),
        ], 'pennant-config');

        $method = method_exists($this, 'publishesMigrations') ? 'publishesMigrations' : 'publishes';

        $this->{$method}([
            __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
        ], 'pennant-migrations');
    }
}
