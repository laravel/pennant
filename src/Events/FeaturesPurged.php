<?php

namespace Laravel\Pennant\Events;

class FeaturesPurged
{
    /**
     * The feature names.
     *
     * @var array
     */
    public $features;

    /**
     * Create a new event instance.
     *
     * @param  array  $features
     */
    public function __construct($features)
    {
        $this->features = $features;
    }
}
