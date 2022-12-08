<?php

namespace Laravel\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Feature\Contracts\FeatureScopeable;
use RuntimeException;

class PendingScopedFeatureInteraction
{
    /**
     * The feature driver.
     *
     * @var \Laravel\Feature\DriverDecorator
     */
    protected $driver;

    /**
     * The feature interaction scope.
     *
     * @var array<mixed>
     */
    protected $scope;

    /**
     * Create a new Pending Scoped Feature Interaction instance.
     *
     * @param  \Laravel\Feature\DriverDecorator  $driver
     * @param  array<mixed>  $scope
     */
    public function __construct($driver, $scope = [])
    {
        $this->driver = $driver;

        $this->scope = $scope;
    }

    /**
     * Scope the feature interaction.
     *
     * @param  mixed|array<mixed>  $scope
     * @return $this
     */
    public function for($scope)
    {
        $this->scope = array_merge($this->scope, Arr::wrap($scope));

        return $this;
    }

    /**
     * Scope the feature interaction to the authenticated user.
     *
     * @return $this
     */
    public function forTheAuthenticatedUser()
    {
        if (! $this->driver->auth()->guard()->check()) {
            throw new RuntimeException('There is no user currently authenticated.');
        }

        return $this->for($this->driver->auth()->guard()->user());
    }

    /**
     * Determine if the feature is active.
     *
     * @param  string|array<string>  $feature
     * @return bool
     */
    public function isActive($feature)
    {
        return Collection::wrap($feature)
            ->crossJoin($this->scope())
            ->every(fn ($bits) => $this->driver->get(...$bits));
    }

    /**
     * Determine if the feature is inactive.
     *
     * @param  string|array<string>  $feature
     * @return bool
     */
    public function isInactive($feature)
    {
        return Collection::wrap($feature)
            ->crossJoin($this->scope())
            ->every(fn ($bits) => ! $this->driver->get(...$bits));
    }

    /**
     * Activate the feature.
     *
     * @param  string|array<string>  $feature
     * @return void
     */
    public function activate($feature)
    {
        Collection::wrap($feature)
            ->crossJoin($this->scope())
            ->each(fn ($bits) => $this->driver->set($bits[0], $bits[1], true));
    }

    /**
     * Deactivate the feature.
     *
     * @param  string|array<string>  $feature
     * @return void
     */
    public function deactivate($feature)
    {
        Collection::wrap($feature)
            ->crossJoin($this->scope())
            ->each(fn ($bits) => $this->driver->set($bits[0], $bits[1], false));
    }

    /**
     * Load the feature into memory.
     *
     * @param  string|array<string>  $feature
     * @return void
     */
    public function load($feature)
    {
        $features = Collection::wrap($feature)
            ->mapWithKeys(fn ($feature) => [$feature => $this->scope()])
            ->all();

        $this->driver->load($features);
    }

    /**
     * The scope to pass to the driver.
     *
     * @return array<mixed>
     */
    protected function scope()
    {
        return $this->scope ?: [null];
    }
}
