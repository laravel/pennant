<?php

namespace Laravel\Feature;

trait HasFeature
{
    public function hasFeature($feature, ...$context)
    {
        return Feature::for($this, ...$context)->isActive($feature);
    }
}
