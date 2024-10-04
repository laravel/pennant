<?php

namespace Laravel\Pennant\Exceptions;

use RuntimeException;

class FeatureInactiveException extends RuntimeException
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $feature)
    {
        parent::__construct(sprintf('The feature [%s] is not active.', $feature));
    }
}
