<?php

namespace Laravel\Pennant\Events;

use Illuminate\Queue\SerializesModels;

class FeatureResetToDefaultState
{
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string  $feature  The feature name.
     * @param  mixed  $value  The new default state.
     */
    public function __construct(
        public $feature,
        public $value,
    ) {}
}
