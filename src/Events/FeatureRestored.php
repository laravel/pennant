<?php

namespace Laravel\Pennant\Events;

use Illuminate\Queue\SerializesModels;

class FeatureRestored
{
    use SerializesModels;

    /**
     * The feature name.
     *
     * @var string
     */
    public $feature;

    /**
     * The scope of the feature restore.
     *
     * @var mixed
     */
    public $scope;

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
     * @param  mixed  $scope
     * @param  mixed  $fallback
     */
    public function __construct($feature, $scope, $fallback)
    {
        $this->feature = $feature;
        $this->scope = $scope;
        $this->fallback = $fallback;
    }
}
