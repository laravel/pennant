<?php

namespace Laravel\Pennant\Events;

use Illuminate\Queue\SerializesModels;

class UnknownFeatureResolved
{
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string  $feature  The feature name.
     * @param  mixed  $scope  The scope of the feature check.
     */
    public function __construct(
        public $feature,
        public $scope,
    ) {}
}
