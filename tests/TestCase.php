<?php

namespace Tests;

use Laravel\Pennant\FeatureManager;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use WithWorkbench;

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
