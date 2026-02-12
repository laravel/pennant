<?php

namespace Tests;

use Laravel\Pennant\FeatureManager;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, string>
     */
    protected function getPackageProviders($app)
    {
        return [
            \Laravel\Pennant\PennantServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');

        if (file_exists(__DIR__.'/../workbench/database/migrations')) {
            $app['config']->set('database.migrations', [
                realpath(__DIR__.'/../database/migrations'),
                realpath(__DIR__.'/../workbench/database/migrations'),
            ]);
        }
    }

    /**
     * Create an instance of the manager.
     *
     * @return \Laravel\Pennant\FeatureManager
     */
    protected function createManager()
    {
        return new FeatureManager($this->app);
    }
}
