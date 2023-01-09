<?php

namespace Laravel\Feature\Events;

class DynamicallyRegisteringFeature
{
    /**
     * The feature class.
     *
     * @var class-string
     */
    public $feature;


    /**
     * Create a new event instance.
     *
     * @param  class-string  $feature
     */
    public function __construct($feature)
    {
        $this->feature = $feature;
    }
}
