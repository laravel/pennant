<?php

namespace Laravel\Feature\Contracts;

interface Driver
{
    /**
     * Retrieve the flags value.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @return bool
     */
    public function get($feature, $scope);

    /**
     * Set the flags value.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @param  mixed  $value
     * @return void
     */
    public function set($feature, $scope, $value);

    /**
     * Register an initial flag state resolver.
     *
     * @param  string  $feature
     * @param  (callable(mixed $scope): mixed)  $resolver
     * @return void
     */
    public function register($feature, $resolver);

    /**
     * Retrieve mutliple flags values.
     *
     * @param  array<string, array<int, mixed>>  $features
     * @return array<string, array<int, mixed>>
     */
    public function load($features);
}
