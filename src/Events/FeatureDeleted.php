<?php

namespace Laravel\Pennant\Events;

use Illuminate\Queue\SerializesModels;

class FeatureDeleted
{
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string  $feature  The feature name.
     * @param  mixed  $scope  The scope of the feature deletion.
     */
    public function __construct(
        public $feature,
        public $scope,
    ) {}
}
