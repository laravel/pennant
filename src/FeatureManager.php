<?php

namespace Laravel\Feature;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Manager;

class FeatureManager extends Manager
{
    /**
     * Create an instance of the Array driver.
     *
     * @return \Laravel\Feature\ArrayDriver
     */
    public function createArrayDriver()
    {
        return $this->container[ArrayDriver::class];
    }

    /**
     * Create an instance of the Database driver.
     *
     * @return \Laravel\Feature\DatabaseDriver
     */
    public function createDatabaseDriver()
    {
        return $this->container[DatabaseDriver::class];
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return 'array';
    }
}
