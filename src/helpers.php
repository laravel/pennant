<?php

use Illuminate\Container\Container;
use Laravel\Pennant\FeatureManager;

if (! function_exists('feature')) {
    /**
     * Get the feature manager instance.
     *
     * @param  string|null  $name
     * @param  \Closure|null  $whenActive
     * @param  \Closure|null  $whenInactive
     * @return \Laravel\Pennant\FeatureManager|mixed
     */
    function feature($name = null, $whenActive = null, $whenInactive = null)
    {
        $manager = Container::getInstance()->make(FeatureManager::class);

        return match (func_num_args()) {
            0 => $manager,
            1 => $manager->value(...func_get_args()),
            default => $manager->when(...func_get_args()),
        };
    }
}
