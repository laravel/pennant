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
     * Create a new Driver Decorator instance.
     *
     * @param \Laravel\Feature\Drivers\ArrayDriver $driver
     */
    public function __construct($driver)
    {
        $this->driver = $driver;
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
     * @param  string  $method
     * @param  array  $parameters
     * @return \Laravel\Feature\PendingScopedFeatureInteraction
     */
    public function __call($name, $arguments)
    {
        return (new PendingScopedFeatureInteraction($this->driver))->{$name}(...$arguments);
    }
}
