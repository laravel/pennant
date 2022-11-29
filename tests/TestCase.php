<?php

namespace Tests;

use Laravel\Feature\FeatureManager;
use Laravel\Feature\FeatureServiceProvider;
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
            FeatureServiceProvider::class,
        ];
    }

    /**
     * Create an instance of the manager.
     *
     * @return \Laravel\Feature\FeatureManager
     */
    protected function createManager()
    {
        return new FeatureManager($this->app);
    }
}
