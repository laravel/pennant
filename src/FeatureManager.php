<?php

namespace Laravel\Feature;

use Illuminate\Support\Manager;
use Laravel\Feature\Drivers\ArrayDriver;

/**
 * @mixin \Laravel\Feature\PendingScopedFeatureInteraction
 * @method \Laravel\Feature\DriverDecorator driver(string $driver)
 */
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

    /**
     * Create a new driver instance.
     *
     * @param  string  $driver
     * @return \Laravel\Feature\DriverDecorator
     */
    protected function createDriver($driver)
    {
        return new DriverDecorator(parent::createDriver($driver));
    }
}
