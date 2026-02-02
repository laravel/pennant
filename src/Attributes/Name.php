<?php

namespace Laravel\Pennant\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Name
{
    /**
     * Create a new attribute instance.
     */
    public function __construct(public string $name)
    {
        //
    }
}
