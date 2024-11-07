<?php

namespace Laravel\Pennant\Contracts;

interface FeatureScopeSerializeable
{
    /**
     * Serialize the feature scope for storage.
     */
    public function featureScopeSerialize(): string;
}
