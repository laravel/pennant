<?php

namespace Laravel\Pennant\Concerns;

use Laravel\Pennant\Feature;

trait HasFeatures
{
    /**
     * Get a scoped feature interaction for the class.
     *
     * @param  string|null  $store
     * @return \Laravel\Pennant\PendingScopedFeatureInteraction
     */
    public function features($store = null)
    {
        return Feature::store($store)->for($this);
    }
}
