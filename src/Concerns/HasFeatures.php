<?php

namespace Laravel\Pennant\Concerns;

use Laravel\Pennant\Feature;

trait HasFeatures
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

    /**
     * Get the value of the feature.
     *
     * @param  string  $feature
     * @return mixed
     */
    public function featureValue($feature)
    {
        return Feature::for($this)->value($feature);
    }
}
