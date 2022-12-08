<?php

namespace Laravel\Feature;

use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Manager;
use Laravel\Feature\Drivers\ArrayDriver;
use Laravel\Feature\Drivers\DatabaseDriver;
use Laravel\Feature\Drivers\Decorator;

/**
 * @method \Laravel\Feature\Drivers\Decorator driver(?string $driver)
 * @mixin \Laravel\Feature\Drivers\Decorator
 */
class FeatureManager extends Manager
{
    /**
     * The scope comparator.
     *
     * @var (callable(mixed, mixed, string): bool)|null
     */
    protected $scopeComparator;

    /**
     * Create a new manager instance.
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    /**
     * Create an instance of the Array driver.
     *
     * @return \Laravel\Feature\Drivers\ArrayDriver
     */
    public function createArrayDriver()
    {
        return new ArrayDriver(
            $this->container['events'],
            $this->scopeComparator('array'),
            []
        );
    }

    /**
     * Create an instance of the Datbase driver.
     *
     * @return \Laravel\Feature\Drivers\DatabaseDriver
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
     * @return \Laravel\Feature\Drivers\Decorator
     */
    protected function createDriver($driver)
    {
        return new Decorator(
            $driver,
            parent::createDriver($driver),
            $this->container[Factory::class]
        );
    }

    /**
     * Get the feature scope comparator.
     *
     * @param  string  $driver
     * @return (callable(mixed, mixed, string): bool)|null
     */
    protected function scopeComparator($driver)
    {
        return function ($a, $b) use ($driver) {
            if ($this->scopeComparator !== null) {
                return ($this->scopeComparator)($a, $b, $driver);
            }

            return $a instanceof Model && $b instanceof Model ? $a->is($b) : $a === $b;
        };
    }

    /**
     * Set the closure used to compare scopes.
     *
     * @param  callable(mixed, mixed, string): bool  $callback
     * @return $this
     */
    public function compareScopeUsing($callback)
    {
        $this->scopeComparator = $callback;

        return $this;
    }
}
