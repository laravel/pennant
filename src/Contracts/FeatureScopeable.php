<?php

namespace Laravel\Feature\Contracts;

interface FeatureScopeable
{
    /**
     * Cast the object to an identifier for the given driver.
     *
     * @param  string  $driver
     * @return mixed
     */
    public function toFeatureScopeIdentifier($driver);
}
