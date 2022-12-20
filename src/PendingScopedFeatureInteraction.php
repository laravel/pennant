<?php

namespace Laravel\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use RuntimeException;

class PendingScopedFeatureInteraction
{
    /**
     * The feature driver.
     *
     * @var \Laravel\Feature\Drivers\Decorator
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
     * @param  \Laravel\Feature\Drivers\Decorator  $driver
     * @param  array<mixed>  $scope
     */
    public function __construct($driver, $scope)
    {
        $this->driver = $driver;

        $this->scope = $scope;
    }

    /**
     * Add scope to the feature interaction.
     *
     * @param  mixed|array<mixed>|\Illuminate\Support\Collection<int, mixed>  $scope
     * @return $this
     */
    public function for($scope)
    {
        $this->scope = array_merge($this->scope, Collection::wrap($scope)->all());

        return $this;
    }

    /**
     * Add the authenticated user as scope to the feature interaction.
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
     * Get the value of the flag.
     *
     * @param  string  $feature
     * @return mixed
     */
    public function value($feature)
    {
        if (count($this->scope()) > 1) {
            throw new RuntimeException('To retrieve the value for mutliple scopes, use the `values` method instead.');
        }

        return $this->driver->get($feature, $this->scope()[0]);
    }

    /**
     * Get the values of the flag.
     *
     * @param  string|array<string>  $feature
     * @return array<string, array<mixed>>
     */
    public function values($feature)
    {
        return Collection::wrap($feature)
            ->mapWithKeys(fn ($feature) => [
                $feature => Collection::make($this->scope())
                    ->map(fn ($scope) => $this->driver->get($feature, $scope))
                    ->all(),
            ])
            ->all();
    }

    /**
     * Determine if the feature is active.
     *
     * @param  string  $feature
     * @return bool
     */
    public function isActive($feature)
    {
        return $this->allAreActive([$feature]);
    }

    /**
     * Determine if all the features are active.
     *
     * @param  array<string>  $features
     * @return bool
     */
    public function allAreActive($features)
    {
        return Collection::make($features)
            ->crossJoin($this->scope())
            ->every(fn ($bits) => $this->driver->get(...$bits) !== false);
    }

    /**
     * Determine if any of the features are active.
     *
     * @param  array<string>  $features
     * @return bool
     */
    public function anyAreActive($features)
    {
        return Collection::make($features)
            ->crossJoin($this->scope())
            ->some(fn ($bits) => $this->driver->get(...$bits) !== false);
    }

    /**
     * Determine if the feature is inactive.
     *
     * @param  string  $feature
     * @return bool
     */
    public function isInactive($feature)
    {
        return $this->allAreInactive([$feature]);
    }

    /**
     * Determine if all the features are inactive.
     *
     * @param  array<string>  $features
     * @return bool
     */
    public function allAreInactive($features)
    {
        return Collection::make($features)
            ->crossJoin($this->scope())
            ->every(fn ($bits) => $this->driver->get(...$bits) === false);
    }

    /**
     * Determine if any of the features are inactive.
     *
     * @param  array<string>  $features
     * @return bool
     */
    public function anyAreInactive($features)
    {
        return Collection::make($features)
            ->crossJoin($this->scope())
            ->some(fn ($bits) => $this->driver->get(...$bits) === false);
    }

    /**
     * Activate the feature.
     *
     * @param  string|array<string>  $feature
     * @param  mixed  $value
     * @return void
     */
    public function activate($feature, $value = true)
    {
        Collection::wrap($feature)
            ->crossJoin($this->scope())
            ->each(fn ($bits) => $this->driver->set($bits[0], $bits[1], $value));
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
     * @param  string|array<int, string>  $feature
     * @return void
     */
    public function load($feature)
    {
        Collection::wrap($feature)
            ->mapWithKeys(fn ($feature) => [$feature => $this->scope()])
            ->pipe(fn ($features) => $this->driver->load($features->all()));
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
