<?php

namespace Laravel\Pennant\Contracts;

interface FeatureScopeable
{
    /**
     * Cast the object to a feature scope identifier for the given driver.
     *
     * @param  string  $driver
     * @return mixed
     */
    public function toFeatureIdentifier($driver);
}
