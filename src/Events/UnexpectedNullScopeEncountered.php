<?php

namespace Laravel\Pennant\Events;

class UnexpectedNullScopeEncountered
{
    /**
     * Create a new event instance.
     *
     * @param  string  $feature  The feature name.
     */
    public function __construct(
        public $feature,
    ) {}
}
