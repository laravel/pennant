<?php

namespace Laravel\Pennant\Contracts;

interface CanSetDefaultState
{
    /**
     * Set a feature flag's default state.
     *
     * The default state is used as the initial value for any scope that does not
     * yet have a stored value, taking precedence over the feature's resolver.
     * Values that are already stored are left untouched.
     */
    public function setDefaultState(string $feature, mixed $value): void;

    /**
     * Set a feature flag's default state and discard every stored scope value.
     *
     * Every scope will adopt the default state the next time it is resolved.
     */
    public function resetToDefaultState(string $feature, mixed $value): void;

    /**
     * Remove a feature flag's default state.
     *
     * Scopes without a stored value will be resolved by the feature's resolver again.
     * Values that are already stored are left untouched.
     */
    public function forgetDefaultState(string $feature): void;
}
