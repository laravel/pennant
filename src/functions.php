<?php

namespace Laravel\Pennant;

use UnitEnum;

if (! function_exists('Laravel\Pennant\enum_value')) {
    /**
     * Return a scalar value for the given value that might be an enum.
     *
     * @internal
     *
     * @param  mixed  $value
     * @return mixed
     */
    function enum_value($value)
    {
        return $value instanceof UnitEnum
            ? (string) \Illuminate\Support\enum_value($value)
            : $value;
    }
}
