<?php

namespace Laravel\Pennant\Drivers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Laravel\Pennant\Attributes\Store;
use Laravel\Pennant\Contracts\Driver;
use Laravel\Pennant\FeatureManager;
use ReflectionClass;
use RuntimeException;

use function Laravel\Pennant\enum_value;

/**
 * Routes each feature to the store it declares, falling back to the default store.
 *
 * @mixin \Laravel\Pennant\PendingScopedFeatureInteraction
 */
class RoutingDecorator extends Decorator
{
    /**
     * The feature manager instance.
     *
     * @var FeatureManager
     */
    protected $manager;

    /**
     * The resolved store name for each feature class.
     *
     * @var array<class-string, string|null>
     */
    protected $storeMap = [];

    /**
     * The names of the stores features have been routed to.
     *
     * @var array<string, bool>
     */
    protected $routedStores = [];

    /**
     * Create a new routing decorator instance.
     *
     * @param  Container  $container
     */
    public function __construct(FeatureManager $manager, $container)
    {
        $this->manager = $manager;

        parent::__construct(
            '__routing',
            $this->unreachableDriver(),
            fn () => $this->defaultStore()->defaultScope(),
            $container,
            new Collection
        );
    }

    /**
     * Define an initial feature flag state resolver.
     *
     * @param  \BackedEnum|\UnitEnum|class-string|string  $feature
     * @param  mixed  $resolver
     */
    public function define($feature, $resolver = null): void
    {
        $store = $this->storeInstanceFor($feature);

        if (func_num_args() === 1) {
            $store->define($feature);
        } else {
            $store->define($feature, $resolver);
        }
    }

    /**
     * Retrieve a feature flag's value.
     *
     * @internal
     *
     * @param  string  $feature
     * @param  mixed  $scope
     */
    public function get($feature, $scope): mixed
    {
        return $this->storeInstanceFor($feature)->get($feature, $scope);
    }

    /**
     * Set a feature flag's value.
     *
     * @internal
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @param  mixed  $value
     */
    public function set($feature, $scope, $value): void
    {
        $this->storeInstanceFor($feature)->set($feature, $scope, $value);
    }

    /**
     * Set a feature flag's value for all scopes.
     *
     * @internal
     *
     * @param  string  $feature
     * @param  mixed  $value
     */
    public function setForAllScopes($feature, $value): void
    {
        $this->storeInstanceFor($feature)->setForAllScopes($feature, $value);
    }

    /**
     * Delete a feature flag's value.
     *
     * @internal
     *
     * @param  string  $feature
     * @param  mixed  $scope
     */
    public function delete($feature, $scope): void
    {
        $this->storeInstanceFor($feature)->delete($feature, $scope);
    }

    /**
     * Retrieve the feature's name.
     *
     * @param  \BackedEnum|\UnitEnum|string  $feature
     * @return string
     */
    public function name($feature)
    {
        return $this->storeInstanceFor($feature)->name($feature);
    }

    /**
     * Retrieve the feature's class.
     *
     * @param  \BackedEnum|\UnitEnum|string  $name
     * @return mixed
     */
    public function instance($name)
    {
        return $this->storeInstanceFor($name)->instance($name);
    }

    /**
     * Get multiple feature flag values.
     *
     * @internal
     *
     * @param  string|array<int|string, mixed>  $features
     * @return array<string, array<int, mixed>>
     */
    public function getAll($features): array
    {
        return $this->routeMany($features, fn ($store, $group) => $store->getAll($group));
    }

    /**
     * Get multiple feature flag values that are missing.
     *
     * @internal
     *
     * @param  string|array<int|string, mixed>  $features
     * @return array<string, array<int, mixed>>
     */
    public function getAllMissing($features)
    {
        return $this->routeMany($features, fn ($store, $group) => $store->getAllMissing($group));
    }

    /**
     * Set multiple feature flag values.
     *
     * @internal
     *
     * @param  list<array{ feature: string, scope: mixed, value: mixed }>  $features
     */
    public function setAll(array $features): void
    {
        $groups = [];

        foreach ($features as $feature) {
            $groups[$this->storeKeyFor($feature['feature'])][] = $feature;
        }

        foreach ($groups as $store => $group) {
            $this->storeInstance($store)->setAll($group);
        }
    }

    /**
     * Purge the given feature from storage.
     *
     * Purging every feature is intentionally limited to the default store: the set
     * of routed stores depends on which features have been resolved so far, and a
     * non-deterministic destructive operation is worse than a predictable one. Use
     * `Feature::store('...')->purge()` to purge another store in full.
     *
     * @param  \BackedEnum|\UnitEnum|string|array<\BackedEnum|\UnitEnum|string>|null  $features
     */
    public function purge($features = null): void
    {
        if ($features === null) {
            $this->defaultStore()->purge(null);

            return;
        }

        $groups = [];

        foreach (Collection::wrap($features) as $feature) {
            $groups[$this->storeKeyFor($feature)][] = $feature;
        }

        foreach ($groups as $store => $group) {
            $this->storeInstance($store)->purge($group);
        }
    }

    /**
     * Retrieve the names of all defined features.
     *
     * @return array<string>
     */
    public function defined(): array
    {
        return $this->collectFromStores(fn ($store) => $store->defined());
    }

    /**
     * Retrieve the names of all stored features.
     *
     * @return array<string>
     */
    public function stored(): array
    {
        return $this->collectFromStores(fn ($store) => $store->stored());
    }

    /**
     * Retrieve the map of feature names to their implementations.
     */
    public function nameMap(): array
    {
        $map = [];

        foreach ($this->stores() as $store) {
            $map = array_replace($map, $store->nameMap());
        }

        return $map;
    }

    /**
     * Retrieve the defined features for the given scope.
     *
     * @internal
     *
     * @param  mixed  $scope
     * @return Collection<int, string>
     */
    public function definedFeaturesForScope($scope)
    {
        return Collection::make($this->stores())
            ->flatMap(fn ($store) => $store->definedFeaturesForScope($scope)->all())
            ->unique()
            ->values();
    }

    /**
     * Get the underlying feature driver.
     *
     * @return Driver
     */
    public function getDriver()
    {
        return $this->defaultStore()->getDriver();
    }

    /**
     * Flush the in-memory cache of feature values.
     *
     * @return void
     */
    public function flushCache()
    {
        foreach ($this->stores() as $store) {
            $store->flushCache();
        }
    }

    /**
     * Set the container instance used by the decorator.
     *
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        foreach ($this->stores() as $store) {
            $store->setContainer($container);
        }

        return $this;
    }

    /**
     * Route a batch of features to their stores and merge the results.
     *
     * Partitioning happens on the raw feature identifiers - resolving them first
     * would trigger dynamic definition against the wrong store. Each target still
     * normalizes its own group, so scopes are resolved with the target's name.
     *
     * @param  string|array<int|string, mixed>  $features
     * @param  callable(Decorator, array<int|string, mixed>): array<string, array<int, mixed>>  $callback
     * @return array<string, array<int, mixed>>
     */
    protected function routeMany($features, $callback)
    {
        $groups = [];
        $order = [];

        foreach (Collection::wrap($features) as $key => $value) {
            $feature = is_array($value) && array_key_exists('feature', $value) && array_key_exists('scope', $value)
                ? $value['feature']
                : (is_int($key) ? $value : $key);

            $order[] = $feature;

            $store = $this->storeKeyFor($feature);

            if (is_int($key)) {
                $groups[$store][] = $value;
            } else {
                $groups[$store][$key] = $value;
            }
        }

        $results = [];

        foreach ($groups as $store => $group) {
            $results = array_replace($results, $callback($this->storeInstance($store), $group));
        }

        return $this->sortToInputOrder($results, $order);
    }

    /**
     * Restore the caller's feature ordering after partitioning by store.
     *
     * @param  array<string, array<int, mixed>>  $results
     * @param  array<int, mixed>  $order
     * @return array<string, array<int, mixed>>
     */
    protected function sortToInputOrder($results, $order)
    {
        $sorted = [];

        foreach ($order as $feature) {
            $name = $this->name($feature);

            if (array_key_exists($name, $results)) {
                $sorted[$name] = $results[$name];
            }
        }

        return array_replace($sorted, $results);
    }

    /**
     * Collect and merge a list of feature names from every known store.
     *
     * @param  callable(Decorator): array<string>  $callback
     * @return array<string>
     */
    protected function collectFromStores($callback)
    {
        return Collection::make($this->stores())
            ->flatMap($callback)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Retrieve the store instance the given feature should be resolved from.
     *
     * @param  \BackedEnum|\UnitEnum|string  $feature
     * @return Decorator
     */
    protected function storeInstanceFor($feature)
    {
        return $this->storeInstance($this->storeKeyFor($feature));
    }

    /**
     * Retrieve the store instance for the given partition key.
     *
     * @param  string  $store
     * @return Decorator
     */
    protected function storeInstance($store)
    {
        return $store === ''
            ? $this->defaultStore()
            : $this->manager->driver($store);
    }

    /**
     * Retrieve the partition key for the given feature.
     *
     * An empty string means "the default store" and keeps the key usable as an
     * array index.
     *
     * @param  \BackedEnum|\UnitEnum|string  $feature
     * @return string
     */
    protected function storeKeyFor($feature)
    {
        return $this->storeFor($feature) ?? '';
    }

    /**
     * Retrieve the store the given feature declares, if any.
     *
     * @param  \BackedEnum|\UnitEnum|string  $feature
     * @return string|null
     */
    protected function storeFor($feature)
    {
        $class = $this->featureClass($feature);

        if ($class === null) {
            return null;
        }

        if (! array_key_exists($class, $this->storeMap)) {
            $this->storeMap[$class] = $this->resolveFeatureStore($class);
        }

        if ($this->storeMap[$class] !== null) {
            $this->routedStores[$this->storeMap[$class]] = true;
        }

        return $this->storeMap[$class];
    }

    /**
     * Resolve the store declared by a feature class.
     *
     * Mirrors the precedence used for feature names: attribute first, then a
     * public property, then nothing.
     *
     * @param  class-string  $class
     * @return string|null
     */
    protected function resolveFeatureStore($class)
    {
        $attribute = (new ReflectionClass($class))->getAttributes(Store::class)[0] ?? null;

        if ($attribute !== null) {
            return $attribute->newInstance()->store;
        }

        if (property_exists($class, 'store')) {
            return $this->container->make($class)->store ?? null;
        }

        return null;
    }

    /**
     * Retrieve the implementation class for the given feature, if there is one.
     *
     * @param  \BackedEnum|\UnitEnum|string  $feature
     * @return class-string|null
     */
    protected function featureClass($feature)
    {
        $feature = (string) enum_value($feature);

        if (class_exists($feature)) {
            return $feature;
        }

        foreach ($this->stores() as $store) {
            $class = $store->nameMap()[$feature] ?? null;

            if (is_string($class) && class_exists($class)) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Retrieve the default store instance.
     *
     * @return Decorator
     */
    protected function defaultStore()
    {
        return $this->manager->driver($this->manager->getDefaultDriver());
    }

    /**
     * Retrieve every store features are currently known to live in.
     *
     * @return array<int, Decorator>
     */
    protected function stores()
    {
        $names = array_values(array_unique(array_merge(
            [$this->manager->getDefaultDriver()],
            array_keys($this->routedStores)
        )));

        return array_map(fn ($name) => $this->manager->driver($name), $names);
    }

    /**
     * Build the placeholder driver for the decorator this class inherits from.
     *
     * @return Driver
     */
    protected function unreachableDriver()
    {
        return new class implements Driver
        {
            public function define(string $feature, callable $resolver): void
            {
                $this->fail();
            }

            public function defined(): array
            {
                $this->fail();
            }

            public function getAll(array $features): array
            {
                $this->fail();
            }

            public function get(string $feature, mixed $scope): mixed
            {
                $this->fail();
            }

            public function set(string $feature, mixed $scope, mixed $value): void
            {
                $this->fail();
            }

            public function setForAllScopes(string $feature, mixed $value): void
            {
                $this->fail();
            }

            public function delete(string $feature, mixed $scope): void
            {
                $this->fail();
            }

            public function purge(?array $features): void
            {
                $this->fail();
            }

            /**
             * @return never
             */
            protected function fail()
            {
                throw new RuntimeException('The routing decorator has no driver of its own. This feature interaction should have been delegated to a store.');
            }
        };
    }
}
