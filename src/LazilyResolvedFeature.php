<?php

namespace Laravel\Pennant;

class LazilyResolvedFeature
{
    /**
     * The feature class.
     *
     * @var class-string
     */
    public $feature;

    /**
     * Create a new lazy feature instance.
     *
     * @param  class-string  $feature
     */
    public function __construct($feature)
    {
        $this->feature = $feature;
    }
}
