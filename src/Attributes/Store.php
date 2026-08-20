<?php

namespace Laravel\Pennant\Attributes;

use Attribute;
use BackedEnum;
use UnitEnum;

use function Laravel\Pennant\enum_value;

#[Attribute(Attribute::TARGET_CLASS)]
class Store
{
    /**
     * The name of the store the feature should be resolved from.
     */
    public string $store;

    /**
     * Create a new attribute instance.
     */
    public function __construct(BackedEnum|UnitEnum|string $store)
    {
        $this->store = (string) enum_value($store);
    }
}
