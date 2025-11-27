<?php

namespace Laravel\Pennant\Contracts;

interface CanSetManyFeaturesForScopes
{
    /**
     * Set multiple feature flag values.
     *
     * @param  list<array{ feature: string, scope: mixed, value: mixed }>  $features
     */
    public function setAll(array $features): void;
}
