<?php

namespace Laravel\Pennant\Contracts;

interface Driver
{
    /**
     * Define an initial feature flag state resolver.
     *
     * @param  string  $feature
     * @param  (callable(mixed $scope): mixed)  $resolver
     * @return void
     */
    public function define($feature, $resolver);

    /**
     * Retrieve the names of all defined features.
     *
     * @return array<string>
     */
    public function defined();

    /**
     * Retrieve a feature flag's value.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @return mixed
     */
    public function get($feature, $scope);

    /**
     * Set a feature flag's value.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @param  mixed  $value
     * @return void
     */
    public function set($feature, $scope, $value);

    /**
     * Delete a feature flag's value.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @return void
     */
    public function delete($feature, $scope);

    /**
     * Purge the given feature from storage.
     *
     * @param  string|null  $feature
     * @return void
     */
    public function purge($feature);

    /**
     * Eagerly preload multiple feature flag values.
     *
     * @param  array<string, array<int, mixed>>  $features
     * @return array<string, array<int, mixed>>
     */
    public function load($features);
}
