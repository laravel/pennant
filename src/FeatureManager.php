<?php

namespace Laravel\Pennant;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Manager;
use InvalidArgumentException;
use Laravel\Pennant\Drivers\ArrayDriver;
use Laravel\Pennant\Drivers\DatabaseDriver;
use Laravel\Pennant\Drivers\Decorator;

/**
 * @mixin \Laravel\Pennant\Drivers\Decorator
 */
class FeatureManager extends Manager
{
    /**
     * The default scope resolver.
     *
     * @var (callable(string): mixed)|null
     */
    protected $defaultScopeResolver;

    /**
     * Get a Pennant store instance.
     *
     * @param  string|null  $store
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function store($store = null)
    {
        return $this->driver($store);
    }

    /**
     * Create a new driver instance.
     *
     * @param  string  $driver
     * @return \Laravel\Pennant\Drivers\Decorator
     *
     * @throws \InvalidArgumentException
     */
    protected function createDriver($driver)
    {
        $storeDriver = $this->container['config']->get('pennant.stores.'.$driver.'.driver');

        if (is_null($storeDriver)) {
            throw new InvalidArgumentException("Pennant store [{$driver}] is not defined or does not have a driver.");
        }

        return new Decorator(
            $driver,
            parent::createDriver($storeDriver),
            $this->defaultScopeResolver($driver),
            $this->container,
            new Collection
        );
    }

    /**
     * Create an instance of the array driver.
     *
     * @return \Laravel\Pennant\Drivers\ArrayDriver
     */
    public function createArrayDriver()
    {
        return new ArrayDriver($this->container['events'], []);
    }

    /**
     * Create an instance of the database driver.
     *
     * @return \Laravel\Pennant\Drivers\DatabaseDriver
     */
    public function createDatabaseDriver()
    {
        return with($this->container['config']->get('pennant.drivers.database'), function ($config) {
            return new DatabaseDriver(
                $this->container['db']->connection($config['connection'] ?? null),
                $this->container['events'],
                $config,
                []
            );
        });
    }

    /**
     * Flush the driver caches.
     *
     * @return void
     */
    public function flushCache()
    {
        foreach ($this->drivers as $driver) {
            $driver->flushCache();
        }

        if (isset($this->drivers['array'])) {
            $this->drivers['array']->getDriver()->flushCache();
        }
    }

    /**
     * The default scope resolver.
     *
     * @param  string  $driver
     * @return callable(): mixed
     */
    protected function defaultScopeResolver($driver)
    {
        return function () use ($driver) {
            if ($this->defaultScopeResolver !== null) {
                return ($this->defaultScopeResolver)($driver);
            }

            return $this->container['auth']->guard()->user();
        };
    }

    /**
     * Set the default scope resolver.
     *
     * @param  (callable(string): mixed)  $resolver
     * @return void
     */
    public function resolveScopeUsing($resolver)
    {
        $this->defaultScopeResolver = $resolver;
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->container['config']->get('pennant.default') ?? 'database';
    }

    /**
     * Set the container instance used by the manager.
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     * @return $this
     */
    public function setContainer(Container $container)
    {
        foreach ($this->drivers as $driver) {
            $driver->setContainer($container);
        }

        return parent::setContainer($container);
    }
}
