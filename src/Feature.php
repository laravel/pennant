<?php

namespace Laravel\Feature;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Laravel\Feature\FeatureManager
 */
class Feature extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return FeatureManager::class;
    }
}
