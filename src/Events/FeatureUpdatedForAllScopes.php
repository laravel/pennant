<?php

namespace Laravel\Pennant\Events;

use Illuminate\Queue\SerializesModels;

class FeatureUpdatedForAllScopes
{
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string  $feature  The feature name.
     * @param  mixed  $value  The new feature value.
     */
    public function __construct(
        public $feature,
        public $value,
    ) {}
}
