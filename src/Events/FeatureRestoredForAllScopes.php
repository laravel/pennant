<?php

namespace Laravel\Pennant\Events;

use Illuminate\Queue\SerializesModels;

class FeatureRestoredForAllScopes
{
    use SerializesModels;

    /**
     * The feature name.
     *
     * @var string
     */
    public $feature;

    /**
     * The feature's fallback value.
     *
     * @var mixed
     */
    public $fallback;

    /**
     * Create a new event instance.
     *
     * @param  string  $feature
     * @param  mixed  $value
     */
    public function __construct($feature, $fallback)
    {
        $this->feature = $feature;
        $this->fallback = $fallback;
    }
}
