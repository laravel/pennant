<?php

namespace Tests;

use Laravel\Feature\FeatureManager;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
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
