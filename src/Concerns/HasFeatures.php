<?php

namespace Laravel\Pennant\Concerns;

use Laravel\Pennant\Feature;
use Laravel\Pennant\PendingScopedFeatureInteraction;

trait HasFeatures
{
    /**
     * Get a scoped feature interaction for the class.
     *
     * @param  string|null  $store
     * @return PendingScopedFeatureInteraction
     */
    public function features($store = null)
    {
        return Feature::store($store)->for($this);
    }
}
