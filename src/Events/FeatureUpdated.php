<?php

namespace Laravel\Pennant\Events;

use Illuminate\Queue\SerializesModels;

class FeatureUpdated
{
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string  $feature  The feature name.
     * @param  mixed  $scope  The scope of the feature update.
     * @param  mixed  $value  The new feature value.
     */
    public function __construct(
        public $feature,
        public $scope,
        public $value,
    ) {}
}
