<?php

namespace Laravel\Pennant\Contracts;

interface Driver
{
    /**
     * Define an initial feature flag state resolver.
     *
     * @param  (callable(mixed $scope): mixed)  $resolver
     */
    public function define(string $feature, callable $resolver): void;

    /**
     * Retrieve the names of all defined features.
     *
     * @return array<string>
     */
    public function defined(): array;

    /**
     * Retrieve a feature flag's value.
     */
    public function get(string $feature, mixed $scope): mixed;

    /**
     * Set a feature flag's value.
     */
    public function set(string $feature, mixed $scope, mixed $value): void;

    /**
     * Set a feature flag's value for all scopes.
     */
    public function setForAllScopes(string $feature, mixed $value): void;

    /**
     * Delete a feature flag's value.
     */
    public function delete(string $feature, mixed $scope): void;

    /**
     * Purge the given feature from storage.
     */
    public function purge(?string $feature): void;

    /**
     * Eagerly preload multiple feature flag values.
     *
     * @param  array<string, array<int, mixed>>  $features
     * @return array<string, array<int, mixed>>
     */
    public function load(array $features): array;
}
