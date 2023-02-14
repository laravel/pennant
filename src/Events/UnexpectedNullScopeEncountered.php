<?php

namespace Laravel\Pennant\Events;

class UnexpectedNullScopeEncountered
{
    /**
     * The feature name.
     *
     * @var string
     */
    public $feature;

    /**
     * Create a new event instance.
     *
     * @param  string  $feature
     */
    public function __construct($feature)
    {
        $this->feature = $feature;
    }
}
