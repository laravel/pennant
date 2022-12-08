<?php

namespace Laravel\Feature\Concerns;

use Laravel\Feature\Feature;

trait HasFeature
{
    /**
     * Determine if the feature is active.
     *
     * @param  string  $feature
     * @return bool
     */
    public function featureIsActive($feature)
    {
        return Feature::for($this)->isActive($feature);
    }

    /**
     * Determine if the feature is inactive.
     *
     * @param  string  $feature
     * @return bool
     */
    public function featureIsInactive($feature)
    {
        return Feature::for($this)->isInactive($feature);
    }
}
