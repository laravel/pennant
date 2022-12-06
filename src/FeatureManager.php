<?php

namespace Laravel\Feature;

use Illuminate\Contracts\Auth\Factory;
use Illuminate\Support\Manager;
use Laravel\Feature\Drivers\ArrayDriver;
use Laravel\Feature\Drivers\DatabaseDriver;

/**
 * @method \Laravel\Feature\DriverDecorator driver(?string $driver)
 * @mixin \Laravel\Feature\DriverDecorator
 */
class FeatureManager extends Manager
{
    /**
     * Create an instance of the Array driver.
     *
     * @return \Laravel\Feature\Drivers\ArrayDriver
     */
    public function createArrayDriver()
    {
        return $this->container[ArrayDriver::class];
    }

    /**
     * Create an instance of the Datbase driver.
     *
     * @return \Laravel\Feature\Drivers\ArrayDriver
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
        return $this->container['config']->get('features.default', 'array');
    }

    /**
     * Create a new driver instance.
     *
     * @param  string  $driver
     * @return \Laravel\Feature\DriverDecorator
     */
    protected function createDriver($driver)
    {
        return new DriverDecorator(
            $driver,
            parent::createDriver($driver),
            $this->container[Factory::class]
        );
    }
}
