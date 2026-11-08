<?php

namespace Laravel\Pennant\Events;

use Illuminate\Queue\SerializesModels;

class FeatureDefaultStateDeleted
{
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string  $feature  The feature name.
     */
    public function __construct(
        public $feature,
    ) {}
}
