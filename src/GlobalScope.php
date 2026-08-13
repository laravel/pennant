<?php

namespace Laravel\Pennant;

use Laravel\Pennant\Contracts\FeatureScopeSerializeable;

class GlobalScope implements FeatureScopeSerializeable
{
    /**
     * Serialize the feature scope for storage.
     */
    public function featureScopeSerialize(): string
    {
        return '__laravel_global';
    }
}
