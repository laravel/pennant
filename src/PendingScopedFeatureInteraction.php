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
    protected $scope = [];

    /**
     * Create a new Pending Scoped Feature Interaction instance.
     *
     * @param  \Laravel\Feature\Drivers\Decorator  $driver
     */
    public function __construct($driver)
    {
        $this->driver = $driver;
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
     * Get the value of the flag.
     *
     * @param  string  $feature
     * @return mixed
     */
    public function value($feature)
    {
        return $this->values([$feature])[$feature];
    }

    /**
     * Get the values of the flag.
     *
     * @param  array<string>  $features
     * @return array<string, mixed>
     */
    public function values($features)
    {
        if (count($this->scope()) > 1) {
            throw new RuntimeException('It is not possible to retrieve the values for mutliple scopes.');
        }

        return Collection::make($features)
            ->mapWithKeys(fn ($feature) => [
                $feature => $this->driver->get($feature, $this->scope()[0])
            ])
            ->all();
    }

    /**
     * Retrieve all the features and their values.
     *
     * @return array<string, mixed>
     */
    public function all()
    {
        return $this->values($this->driver->registered());
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
     * @param  string|array<int, string>  $features
     * @return void
     */
    public function load($features)
    {
        Collection::wrap($features)
            ->mapWithKeys(fn ($feature) => [$feature => $this->scope()])
            ->pipe(fn ($features) => $this->driver->load($features->all()));
    }

    /**
     * Forget the flags value.
     *
     * @param  string|array<string>  $feature
     * @param  mixed  $scope
     * @return void
     */
    public function forget($features)
    {
        return Collection::wrap($features)
            ->crossJoin($this->scope())
            ->each(fn ($bits) => $this->driver->delete(...$bits));
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
