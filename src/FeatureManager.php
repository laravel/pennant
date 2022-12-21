<?php

namespace Laravel\Feature;

use Illuminate\Support\Collection;
use Illuminate\Support\Manager;
use Laravel\Feature\Drivers\ArrayDriver;
use Laravel\Feature\Drivers\DatabaseDriver;
use Laravel\Feature\Drivers\Decorator;

/**
 * @method \Laravel\Feature\Drivers\Decorator driver(string|null $driver = null)
 * @mixin \Laravel\Feature\Drivers\Decorator
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
     * Create an instance of the Array driver.
     *
     * @return \Laravel\Feature\Drivers\ArrayDriver
     */
    public function createArrayDriver()
    {
        return new ArrayDriver($this->container['events'], []);
    }

    /**
     * Create an instance of the Datbase driver.
     *
     * @return \Laravel\Feature\Drivers\DatabaseDriver
     */
    public function createDatabaseDriver()
    {
        return new DatabaseDriver($this->container['db.connection'], $this->container['events'], []);
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->container['config']->get('features.default') ?? 'database';
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
            $this->defaultScopeResolver($driver),
            $this->container,
            new Collection
        );
    }

    /**
     * Set the default scope resolver.
     *
     * @param  (callable(string): mixed)  $resolver
     * @return void
     */
    public function setDefaultScopeResolver($resolver)
    {
        $this->defaultScopeResolver = $resolver;
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
}
