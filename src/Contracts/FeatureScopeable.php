<?php

namespace Laravel\Feature\Contracts;

interface FeatureScopeable
{
    /**
     * The value to use when checking features against the instance.
     *
     * @param  string  $driver
     * @return mixed
     */
    public function toFeatureScopeIdentifier($driver);
}
