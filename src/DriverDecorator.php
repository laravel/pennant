<?php

namespace Laravel\Feature;

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
     * @var \Illuminate\Contracts\Auth\Factory  $auth
     */
    protected $auth;

    /**
     * Create a new Driver Decorator instance.
     *
     * @param \Laravel\Feature\Drivers\ArrayDriver $driver
     */
    public function __construct($driver, $auth)
    {
        $this->driver = $driver;

        $this->auth = $auth;
    }

    /**
     * Get the driver being decorated.
     *
     * @return \Laravel\Feature\Drivers\ArrayDriver
     */
    public function toBase()
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
