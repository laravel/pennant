<?php

namespace Laravel\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Feature\Contracts\FeatureScopeable;
use RuntimeException;

class PendingScopedFeatureInteraction
{
    /**
     * The feature driver wrapper.
     *
     * @var \Laravel\Feature\DriverDecorator
     */
    protected $wrapper;

    /**
     * The feature interaction scope.
     *
     * @var array<mixed>
     */
    protected $scope = [];

    /**
     * Create a new Pending Scoped Feature Interaction instance.
     *
     * @param  \Laravel\Feature\DriverDecorator  $wrapper
     */
    public function __construct($wrapper)
    {
        $this->wrapper = $wrapper;
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
        if (! $this->wrapper->auth()->guard()->check()) {
            throw new RuntimeException('There is no user currently authenticated.');
        }

        return $this->for($this->wrapper->auth()->guard()->user());
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
            ->every(function ($bits) {
                return $this->driver()->isActive(...$bits);
            });
    }

    /**
     * Determine if the feature is inactive.
     *
     * @param  string  $feature
     * @return bool
     */
    public function isInactive($feature)
    {
        return Collection::wrap($feature)
            ->crossJoin($this->scope())
            ->every(function ($bits) {
                return ! $this->driver()->isActive(...$bits);
            });
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
            ->each(function ($bits) {
                $this->driver()->activate(...$bits);
            });
    }

    /**
     * Deactivate the feature.
     *
     * @param  string|array<string>  $feature
     * @return void
     */
    public function deactivate($feature)
    {
        return Collection::wrap($feature)
            ->crossJoin($this->scope())
            ->each(function ($bits) {
                $this->driver()->deactivate(...$bits);
            });
    }

    /**
     * The scope for the feature check.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function scope()
    {
        return Collection::make($this->scope ?: [null])
            ->map(fn ($scope) => $scope instanceof FeatureScopeable
                ? $scope->toFeatureScopeIdentifier($this->wrapper->name)
                : $scope);
    }

    /**
     * The underlying driver.
     *
     * @return \Laravel\Feature\Drivers\ArrayDriver
     */
    protected function driver()
    {
        return $this->wrapper->driver();
    }
}
