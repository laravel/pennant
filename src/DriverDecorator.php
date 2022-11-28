<?php

namespace Laravel\Feature;

use Illuminate\Support\Arr;


/**
 * @mixin \Laravel\Feature\PendingScopedFeatureInteraction
 */
class DriverDecorator
{
    /**
     * The driver being decorated.
     *
     * @var \Laravel\Feature\Drivers\ArrayDriver
     */
    protected $driver;

    /**
     * The authenticate factory.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Create a new Driver Decorator instance.
     *
     * @param  \Laravel\Feature\Drivers\ArrayDriver  $driver
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     */
    public function __construct($driver, $auth)
    {
        $this->driver = $driver;

        $this->auth = $auth;
    }

    /**
     * Register an initial feature state resolver.
     *
     * TODO: Should this return a pending registration object so we can do
     * interesting modifications while registering?
     *
     * @param  string  $feature
     * @param  (callable(mixed $scope, mixed ...$additional): mixed)  $resolver
     * @return void
     */
    public function register($feature, $resolver)
    {
        $this->toBaseDriver()->register($feature, $resolver);
    }

    /**
     * Eagerly load the feature state into memory.
     *
     * @param  array<string|int, array<int, mixed>|string>  $feature
     * @return void
     */
    public function load($features)
    {
        $this->toBaseDriver()->load($features);
    }

    /**
     * Eagerly load the missing feature state into memory.
     *
     * @param  array<string|int, array<int, mixed>|string>  $feature
     * @return void
     */
    public function loadMissing($features)
    {
        $this->toBaseDriver()->loadMissing($features);
    }

    /**
     * Get the decorated driver.
     *
     * @return \Laravel\Feature\Drivers\ArrayDriver
     */
    public function toBaseDriver()
    {
        return $this->driver;
    }

    /**
     * Dynamically create a pending feature interaction.
     *
     * @param  string  $name
     * @param  array<mixed>  $parameters
     * @return \Laravel\Feature\PendingScopedFeatureInteraction
     */
    public function __call($name, $parameters)
    {
        return (new PendingScopedFeatureInteraction($this->driver, $this->auth))->{$name}(...$parameters);
    }
}
