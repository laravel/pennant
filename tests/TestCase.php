<?php

namespace Tests;

use Laravel\Pennant\FeatureManager;
use Laravel\Pennant\PennantServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app)
    {
        return [
            PennantServiceProvider::class,
        ];
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
