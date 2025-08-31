<?php

namespace Laravel\Pennant\Events;

class FeaturesPurged
{
    /**
     * Create a new event instance.
     *
     * @param  array  $features  The feature names.
     */
    public function __construct(
        public $features,
    ) {}
}
