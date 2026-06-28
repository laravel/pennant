<?php

namespace Laravel\Pennant;

use Illuminate\Support\Collection;
use Laravel\Pennant\Drivers\Decorator;
use RuntimeException;

class PendingScopedFeatureInteraction
{
    /**
     * The feature driver.
     *
     * @var Decorator
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
     * @param  Decorator  $driver
     */
    public function __construct($driver)
    {
        $this->driver = $driver;
    }

    /**
     * Add scope to the feature interaction.
     *
     * @param  mixed  $scope
     * @return $this
     */
    public function for($scope)
    {
        $this->scope = array_merge($this->scope, Collection::wrap($scope)->all());

        return $this;
    }

    /**
     * Load the feature into memory.
     *
     * @param  \BackedEnum|\UnitEnum|string|array<int, \BackedEnum|\UnitEnum|string>  $features
     * @return array<string, array<int, mixed>>
     */
    public function load($features)
    {
        $features = $this->normalize($features);

        return Collection::wrap($features)
            ->map(fn ($feature) => ['feature' => $feature, 'scope' => $this->scope()])
            ->pipe(fn ($features) => $this->driver->getAll($features->all()));
    }

    /**
     * Load the missing features into memory.
     *
     * @param  \BackedEnum|\UnitEnum|string|array<int, \BackedEnum|\UnitEnum|string>  $features
     * @return array<string, array<int, mixed>>
     */
    public function loadMissing($features)
    {
        $features = $this->normalize($features);

        return Collection::wrap($features)
            ->map(fn ($feature) => ['feature' => $feature, 'scope' => $this->scope()])
            ->pipe(fn ($features) => $this->driver->getAllMissing($features->all()));
    }

    /**
     * Load all defined features into memory.
     *
     * @return array<string, array<int, mixed>>
     */
    public function loadAll()
    {
        return $this->load(
            $this->driver->definedFeaturesForScope($this->scope()[0])->all()
        );
    }

    /**
     * Get the value of the flag.
     *
     * @param  \BackedEnum|\UnitEnum|string  $feature
     * @return mixed
     */
    public function value($feature)
    {
        return head($this->values([$feature]));
    }

    /**
     * Get the values of the flag.
     *
     * @param  array<int, \BackedEnum|\UnitEnum|string>  $features
     * @return array<string, mixed>
     */
    public function values($features)
    {
        if (count($this->scope()) > 1) {
            throw new RuntimeException('It is not possible to retrieve the values for multiple scopes.');
        }

        $features = $this->normalize($features);

        $this->loadMissing($features);

        return Collection::make($features)
            ->mapWithKeys(fn ($feature) => [
                $this->driver->name($feature) => $this->driver->get($feature, $this->scope()[0]),
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
        return $this->values(
            $this->driver->definedFeaturesForScope($this->scope()[0])->all()
        );
    }

    /**
     * Determine if the feature is active.
     *
     * @param  \BackedEnum|\UnitEnum|string  $feature
     * @return bool
     */
    public function active($feature)
    {
        return $this->allAreActive([$feature]);
    }

    /**
     * Determine if all the features are active.
     *
     * @param  array<int, \BackedEnum|\UnitEnum|string>  $features
     * @return bool
     */
    public function allAreActive($features)
    {
        $features = $this->normalize($features);

        $this->loadMissing($features);

        return Collection::make($features)
            ->crossJoin($this->scope())
            ->every(fn ($bits) => $this->driver->get(...$bits) !== false);
    }

    /**
     * Determine if any of the features are active.
     *
     * @param  array<int, \BackedEnum|\UnitEnum|string>  $features
     * @return bool
     */
    public function someAreActive($features)
    {
        $features = $this->normalize($features);

        $this->loadMissing($features);

        return Collection::make($this->scope())
            ->every(fn ($scope) => Collection::make($features)
                ->some(fn ($feature) => $this->driver->get($feature, $scope) !== false));
    }

    /**
     * Determine if the feature is inactive.
     *
     * @param  \BackedEnum|\UnitEnum|string  $feature
     * @return bool
     */
    public function inactive($feature)
    {
        return $this->allAreInactive([$feature]);
    }

    /**
     * Determine if all the features are inactive.
     *
     * @param  array<int, \BackedEnum|\UnitEnum|string>  $features
     * @return bool
     */
    public function allAreInactive($features)
    {
        $features = $this->normalize($features);

        $this->loadMissing($features);

        return Collection::make($features)
            ->crossJoin($this->scope())
            ->every(fn ($bits) => $this->driver->get(...$bits) === false);
    }

    /**
     * Determine if any of the features are inactive.
     *
     * @param  array<int, \BackedEnum|\UnitEnum|string>  $features
     * @return bool
     */
    public function someAreInactive($features)
    {
        $features = $this->normalize($features);

        $this->loadMissing($features);

        return Collection::make($this->scope())
            ->every(fn ($scope) => Collection::make($features)
                ->some(fn ($feature) => $this->driver->get($feature, $scope) === false));
    }

    /**
     * Apply the callback if the feature is active.
     *
     * @param  \BackedEnum|\UnitEnum|string  $feature
     * @param  \Closure  $whenActive
     * @param  \Closure|null  $whenInactive
     * @return mixed
     */
    public function when($feature, $whenActive, $whenInactive = null)
    {
        if ($this->active($feature)) {
            return $whenActive($this->value($feature), $this);
        }

        if ($whenInactive !== null) {
            return $whenInactive($this);
        }
    }

    /**
     * Apply the callback if the feature is inactive.
     *
     * @param  \BackedEnum|\UnitEnum|string  $feature
     * @param  \Closure  $whenInactive
     * @param  \Closure|null  $whenActive
     * @return mixed
     */
    public function unless($feature, $whenInactive, $whenActive = null)
    {
        return $this->when($feature, $whenActive ?? fn () => null, $whenInactive);
    }

    /**
     * Activate the feature.
     *
     * @param  \BackedEnum|\UnitEnum|string|array<int, \BackedEnum|\UnitEnum|string>  $feature
     * @param  mixed  $value
     * @return void
     */
    public function activate($feature, $value = true)
    {
        $feature = $this->normalize($feature);

        $features = Collection::wrap($feature)
            ->crossJoin($this->scope())
            ->map(fn ($bits) => ['feature' => $bits[0], 'scope' => $bits[1], 'value' => $value])
            ->all();

        $this->driver->setAll($features);
    }

    /**
     * Deactivate the feature.
     *
     * @param  \BackedEnum|\UnitEnum|string|array<int, \BackedEnum|\UnitEnum|string>  $feature
     * @return void
     */
    public function deactivate($feature)
    {
        $feature = $this->normalize($feature);

        $features = Collection::wrap($feature)
            ->crossJoin($this->scope())
            ->map(fn ($bits) => ['feature' => $bits[0], 'scope' => $bits[1], 'value' => false])
            ->all();

        $this->driver->setAll($features);
    }

    /**
     * Forget the flags value.
     *
     * @param  \BackedEnum|\UnitEnum|string|array<int, \BackedEnum|\UnitEnum|string>  $features
     * @return void
     */
    public function forget($features)
    {
        $features = $this->normalize($features);

        Collection::wrap($features)
            ->crossJoin($this->scope())
            ->each(fn ($bits) => $this->driver->delete(...$bits));
    }

    /**
     * Normalize the given features to their scalar names.
     *
     * @param  \BackedEnum|\UnitEnum|string|array<int, \BackedEnum|\UnitEnum|string>  $features
     * @return array<int, mixed>
     */
    protected function normalize($features)
    {
        return Collection::wrap($features)
            ->map(static fn ($feature) => enum_value($feature))
            ->all();
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
