<?php

namespace Laravel\Feature;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Manager;

class FeatureManager extends Manager
{
    /**
     * @return \Laravel\Feature\ArrayDriver
     */
    public function createArrayDriver()
    {
        return new ArrayDriver(
            $this->container[Dispatcher::class]
        );
    }

    /**
     * @return \Laravel\Feature\DatabaseDriver
     */
    public function createDatabaseDriver()
    {
        return $this->container[DatabaseDriver::class];
    }

    /**
     * @return string
     */
    public function getDefaultDriver()
    {
        return 'array';
    }
}
