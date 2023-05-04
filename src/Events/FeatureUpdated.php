<?php

namespace Laravel\Pennant\Events;

use Illuminate\Queue\SerializesModels;

class FeatureUpdated
{
    use SerializesModels;

    /**
     * The feature name.
     *
     * @var string
     */
    public $feature;

    /**
     * The scope of the feature update.
     *
     * @var mixed
     */
    public $scope;

    /**
     * The new feature value.
     *
     * @var mixed
     */
    public $value;

    /**
     * Create a new event instance.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @param  mixed  $value
     */
    public function __construct($feature, $scope, $value)
    {
        $this->feature = $feature;
        $this->scope = $scope;
        $this->value = $value;
    }
}
