<?php

namespace Laravel\Pennant\Events;

use Illuminate\Queue\SerializesModels;

class UnknownFeatureResolved
{
    use SerializesModels;

    /**
     * The feature name.
     *
     * @var string
     */
    public $feature;

    /**
     * The scope of the feature check.
     *
     * @var mixed
     */
    public $scope;

    /**
     * Create a new event instance.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     */
    public function __construct($feature, $scope)
    {
        $this->feature = $feature;
        $this->scope = $scope;
    }
}
