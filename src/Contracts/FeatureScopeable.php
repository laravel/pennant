<?php

namespace Laravel\Feature\Contracts;

interface FeatureScopeable
{
    /**
     * The value to use when checking features against the instance.
     *
     * @return string
     */
    public function toFeatureScopeIdentifier();
}
