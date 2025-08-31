<?php

namespace Laravel\Pennant\Events;

class DynamicallyRegisteringFeatureClass
{
    /**
     * Create a new event instance.
     *
     * @param  class-string  $feature  The feature class.
     */
    public function __construct(
        public $feature,
    ) {}
}
