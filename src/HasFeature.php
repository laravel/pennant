<?php

namespace Laravel\Feature;

trait HasFeature
{
    /**
     * Determine if the feature is active.
     *
     * @param  string  $feature
     * @param  mixed  ...$additionalScope
     * @return bool
     */
    public function featureIsActive($feature, ...$additionalScope)
    {
        return Feature::for($this, ...$additionalScope)->isActive($feature);
    }

    /**
     * Determine if the feature is inactive.
     *
     * @param  string  $feature
     * @param  mixed  ...$additionalScope
     * @return bool
     */
    public function featureIsInactive($feature, ...$additionalScope)
    {
        return Feature::for($this, ...$additionalScope)->isInactive($feature);
    }
}
