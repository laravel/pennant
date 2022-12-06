<?php

namespace Laravel\Feature;

/**
 * @mixin \Laravel\Feature\PendingScopedFeatureInteraction
 */
class DriverDecorator
{
    /**
     * The driver name.
     *
     * @var string
     */
    public $name;

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
     * @param  string  $name
     * @param  \Laravel\Feature\Drivers\ArrayDriver  $driver
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     */
    public function __construct($name, $driver, $auth)
    {
        $this->name = $name;

        $this->driver = $driver;

        $this->auth = $auth;
    }

    /**
     * Register an initial feature state resolver.
     *
     * @param  string  $feature
     * @param  (callable(mixed $scope): mixed)  $resolver
     * @return void
     */
    public function register($feature, $resolver)
    {
        $this->driver()->register($feature, $resolver);
    }

    /**
     * Eagerly load the feature state into memory.
     *
     * @param  string|array<string|int, array<int, mixed>|string>  $features
     * @return void
     */
    public function load($features)
    {
        $this->driver()->load($features);
    }

    /**
     * Eagerly load the missing feature state into memory.
     *
     * @param  string|array<string|int, array<int, mixed>|string>  $features
     * @return void
     */
    public function loadMissing($features)
    {
        $this->driver()->loadMissing($features);
    }

    /**
     * Get the decorated driver.
     *
     * @return \Laravel\Feature\Drivers\ArrayDriver
     */
    public function driver()
    {
        return $this->driver;
    }

    /**
     * Get the Authentication factory.
     *
     * @return \Illuminate\Contracts\Auth\Factory
     */
    public function auth()
    {
        return $this->auth;
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
        return (new PendingScopedFeatureInteraction($this))->{$name}(...$parameters);
    }
}
