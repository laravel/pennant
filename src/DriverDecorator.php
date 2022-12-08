<?php

namespace Laravel\Feature;

use Illuminate\Support\Collection;
use Laravel\Feature\Contracts\FeatureScopeable;


/**
 * @mixin \Laravel\Feature\PendingScopedFeatureInteraction
 */
class DriverDecorator
{
    /**
     * The driver name.
     *
     * @var string
     */
    protected $name;

    /**
     * The driver being decorated.
     *
     * @var \Laravel\Feature\Drivers\ArrayDriver
     */
    protected $driver;

    /**
     * The authenticate factory.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Feature state cache.
     *
     * @var \Illuminate\Support\Collection{ feature: string, scope: mixed, value: mixed }
     */
    protected $cache;

    /**
     * Create a new Driver Decorator instance.
     *
     * @param  string  $name
     * @param  \Laravel\Feature\Drivers\ArrayDriver  $driver
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @param  \Illuminate\Support\Collection<int, array{ feature: string, scope: mixed, value: mixed }>  $cache
     */
    public function __construct($name, $driver, $auth, $cache = new Collection)
    {
        $this->name = $name;

        $this->driver = $driver;

        $this->auth = $auth;

        $this->cache = $cache;
    }

    /**
     * Register an initial feature state resolver.
     *
     * @param  string  $feature
     * @param  (callable(mixed $scope): mixed)  $resolver
     * @return void
     */
    public function register($feature, $resolver)
    {
        $this->driver()->register($feature, $resolver);
    }

    /**
     * Eagerly load the feature state into memory.
     *
     * @param  string|array<string|int, array<int, mixed>|string>  $features
     * @return void
     */
    public function load($features)
    {
        $features = $this->normalizeFeaturesToLoad($features);

        $results = $this->driver()->load($features->all());

        $features->flatMap(fn ($scopes, $key) => Collection::make($scopes)
                ->zip($results[$key])
                ->map(fn ($scopes) => $scopes->push($key)))
            ->each(fn ($value) => $this->remember($value[2], $value[0], $value[1]));
    }

    /**
     * Eagerly load the missing feature state into memory.
     *
     * @param  string|array<string|int, array<int, mixed>|string>  $features
     * @return void
     */
    public function loadMissing($features)
    {
        $features = $this->normalizeFeaturesToLoad($features)
            ->map(fn ($scopes, $feature) => Collection::make($scopes)
                ->reject(fn ($scope) => $this->isCached($feature, $scope))
                ->all())
            ->reject(fn ($scopes) => $scopes === [])
            ->all();

        $this->load($features);
    }

    /**
     * Get the value of the feature flag.
     *
     * @internal
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @return mixed
     */
    public function get($feature, $scope)
    {
        $scope = $scope instanceof FeatureScopeable
            ? $scope->toFeatureScopeIdentifier($this->name)
            : $scope;

        $item = $this->cache
            ->whereStrict('scope', $scope)
            ->whereStrict('feature', $feature)
            ->first();

        if ($item !== null) {
            return $item['value'];
        }

        return tap($this->driver()->isActive($feature, $scope), function ($value) use ($feature, $scope) {
            $this->remember($feature, $scope, $value);
        });
    }

    /**
     * Set the value of the feature flag.
     *
     * @internal
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @param  mixed  $value
     * @return void
     */
    public function set($feature, $scope, $value)
    {
        $scope = $scope instanceof FeatureScopeable
            ? $scope->toFeatureScopeIdentifier($this->name)
            : $scope;

        if ($value !== false) {
            $this->driver->activate($feature, $scope);
        } else {
            $this->driver->deactivate($feature, $scope);
        }

        $this->remember($feature, $scope, $value);
    }

    /**
     * Get the decorated driver.
     *
     * @return \Laravel\Feature\Drivers\ArrayDriver
     */
    public function driver()
    {
        return $this->driver;
    }

    /**
     * Get the Authentication factory.
     *
     * @return \Illuminate\Contracts\Auth\Factory
     */
    public function auth()
    {
        return $this->auth;
    }

    /**
     * Put the feature value into the cache.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @param  mixed  $value
     * @return void
     */
    protected function remember($feature, $scope, $value)
    {
        $position = $this->cache->search(
            fn ($item) => $item['feature'] === $feature && $item['scope'] === $scope
        );

        if ($position === false) {
            $this->cache[] = ['feature' => $feature, 'scope' => $scope, 'value' => $value];
        } else {
            $this->cache[$position] = ['feature' => $feature, 'scope' => $scope, 'value' => $value];
        }
    }

    /**
     * Determine if a feature is in the cache.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @return bool
     */
    protected function isCached($feature, $scope)
    {
        return $this->cache->search(
            fn ($item) => $item['feature'] === $feature && $item['scope'] === $scope
        ) !== false;
    }

    /**
     * Normalize features to load.
     *
     * @param  string|array<int|string, mixed>  $features
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, mixed>>
     */
    protected function normalizeFeaturesToLoad($features)
    {
        return Collection::wrap($features)
            ->mapWithKeys(fn ($value, $key) => is_int($key)
                ? [$value => Collection::make([null])]
                : [$key => Collection::wrap($value ?: [null])])
            ->map(fn ($scopes) => $scopes
                ->map(fn ($scope) => $scope instanceof FeatureScopeable
                    ? $scope->toFeatureScopeIdentifier($this->name)
                    : $scope)
                ->all());
    }

    /**
     * Dynamically create a pending feature interaction.
     *
     * @param  string  $name
     * @param  array<mixed>  $parameters
     * @return \Laravel\Feature\PendingScopedFeatureInteraction
     */
    public function __call($name, $parameters)
    {
        return (new PendingScopedFeatureInteraction($this))->{$name}(...$parameters);
    }
}
