<?php

namespace Laravel\Pennant\Events;

use Illuminate\Queue\SerializesModels;

class FeaturesPurged
{
    use SerializesModels;

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
