<?php

namespace Laravel\Pennant\Drivers;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Lottery;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use Laravel\Pennant\Contracts\CanListStoredFeatures;
use Laravel\Pennant\Contracts\Driver;
use Laravel\Pennant\Contracts\FeatureScopeable;
use Laravel\Pennant\Events\AllFeaturesPurged;
use Laravel\Pennant\Events\DynamicallyRegisteringFeatureClass;
use Laravel\Pennant\Events\FeatureDeleted;
use Laravel\Pennant\Events\FeatureResolved;
use Laravel\Pennant\Events\FeatureRetrieved;
use Laravel\Pennant\Events\FeaturesPurged;
use Laravel\Pennant\Events\FeatureUpdated;
use Laravel\Pennant\Events\FeatureUpdatedForAllScopes;
use Laravel\Pennant\Events\UnexpectedNullScopeEncountered;
use Laravel\Pennant\Feature;
use Laravel\Pennant\LazilyResolvedFeature;
use Laravel\Pennant\PendingScopedFeatureInteraction;
use ReflectionFunction;
use RuntimeException;
use Symfony\Component\Finder\Finder;

/**
 * @mixin \Laravel\Pennant\PendingScopedFeatureInteraction
 */
class Decorator implements CanListStoredFeatures, Driver
{
    use Macroable {
        __call as macroCall;
    }

    /**
     * The driver name.
     *
     * @var string
     */
    protected $name;

    /**
     * The driver being decorated.
     *
     * @var \Laravel\Pennant\Contracts\Driver
     */
    protected $driver;

    /**
     * The default scope resolver.
     *
     * @var callable(): mixed
     */
    protected $defaultScopeResolver;

    /**
     * The container instance.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $container;

    /**
     * The in-memory feature state cache.
     *
     * @var \Illuminate\Support\Collection<int, array{ feature: string, scope: mixed, value: mixed }>
     */
    protected $cache;

    /**
     * Create a new driver decorator instance.
     *
     * @param  string  $name
     * @param  \Laravel\Pennant\Contracts\Driver  $driver
     * @param  (callable(): mixed)  $defaultScopeResolver
     * @param  \Illuminate\Contracts\Container\Container  $container
     * @param  \Illuminate\Support\Collection<int, array{ feature: string, scope: mixed, value: mixed }>  $cache
     */
    public function __construct($name, $driver, $defaultScopeResolver, $container, $cache)
    {
        $this->name = $name;
        $this->driver = $driver;
        $this->defaultScopeResolver = $defaultScopeResolver;
        $this->container = $container;
        $this->cache = $cache;
    }

    /**
     * Discover and register the application's feature classes.
     *
     * @param  string  $namespace
     * @param  string|null  $path
     * @return void
     */
    public function discover($namespace = 'App\\Features', $path = null)
    {
        $namespace = Str::finish($namespace, '\\');

        Collection::make((new Finder)
            ->files()
            ->name('*.php')
            ->depth(0)
            ->in($path ?? base_path('app/Features')))
            ->each(fn ($file) => $this->define("{$namespace}{$file->getBasename('.php')}"));
    }

    /**
     * Define an initial feature flag state resolver.
     *
     * @param  string|class-string  $feature
     * @param  mixed  $resolver
     */
    public function define($feature, $resolver = null): void
    {
        if (func_num_args() === 1) {
            [$feature, $resolver] = [
                $this->container->make($feature)->name ?? $feature,
                new LazilyResolvedFeature($feature),
            ];
        }

        $this->driver->define($feature, function ($scope) use ($feature, $resolver) {
            if ($resolver instanceof LazilyResolvedFeature) {
                $resolver = with($this->container[$resolver->feature], fn ($instance) => method_exists($instance, 'resolve')
                    ? $instance->resolve(...)
                    : $instance(...));
            }

            if (! $resolver instanceof Closure) {
                return $this->resolve($feature, fn () => $resolver, $scope);
            }

            if ($scope !== null) {
                return $this->resolve($feature, $resolver, $scope);
            }

            if ($this->canHandleNullScope($resolver)) {
                return $this->resolve($feature, $resolver, $scope);
            }

            Event::dispatch(new UnexpectedNullScopeEncountered($feature));

            return $this->resolve($feature, fn () => false, $scope);
        });
    }

    /**
     * Resolve the feature value.
     *
     * @param  string  $feature
     * @param  callable  $resolver
     * @param  mixed  $scope
     * @return mixed
     */
    protected function resolve($feature, $resolver, $scope)
    {
        $value = $resolver($scope);

        $value = $value instanceof Lottery ? $value() : $value;

        Event::dispatch(new FeatureResolved($feature, $scope, $value));

        return $value;
    }

    /**
     * Determine if the resolver accepts null scope.
     *
     * @param  callable  $resolver
     * @return bool
     */
    protected function canHandleNullScope($resolver)
    {
        $function = new ReflectionFunction(Closure::fromCallable($resolver));

        return $function->getNumberOfParameters() === 0 ||
            ! $function->getParameters()[0]->hasType() ||
            $function->getParameters()[0]->getType()->allowsNull();
    }

    /**
     * Retrieve the names of all defined features.
     *
     * @return array<string>
     */
    public function defined(): array
    {
        return $this->driver->defined();
    }

    /**
     * Retrieve the names of all stored features.
     *
     * @return array<string>
     */
    public function stored(): array
    {
        if (! $this->driver instanceof CanListStoredFeatures) {
            throw new RuntimeException("The [{$this->name}] driver does not support listing stored features.");
        }

        return $this->driver->stored();
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
        $features = $this->normalizeFeaturesToLoad($features);

        if ($features->isEmpty()) {
            return [];
        }

        $hasUnresolvedFeatures = false;

        $resolvedBefore = $features->reduce(function ($resolved, $scopes, $feature) use (&$hasUnresolvedFeatures) {
            $resolved[$feature] = [];

            if (! method_exists($feature, 'before')) {
                $hasUnresolvedFeatures = true;

                return $resolved;
            }

            $before = $this->container->make($feature)->before(...);

            foreach ($scopes as $index => $scope) {
                $value = $this->resolveBeforeHook($feature, $scope, $before);

                if ($value !== null) {
                    $resolved[$feature][$index] = $value;
                } else {
                    $hasUnresolvedFeatures = true;
                }
            }

            return $resolved;
        }, []);

        $results = array_replace_recursive(
            $features->all(),
            $resolvedBefore,
            $hasUnresolvedFeatures ? $this->driver->getAll($features->map(function ($scopes, $feature) use ($resolvedBefore) {
                return array_diff_key($scopes, $resolvedBefore[$feature]);
            })->all()) : [],
        );

        $features->flatMap(fn ($scopes, $key) => Collection::make($scopes)
            ->zip($results[$key])
            ->map(fn ($scopes) => $scopes->push($key)))
            ->each(fn ($value) => $this->putInCache($value[2], $value[0], $value[1]));

        return $results;
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
        return $this->normalizeFeaturesToLoad($features)
            ->map(fn ($scopes, $feature) => Collection::make($scopes)
                ->reject(fn ($scope) => $this->isCached($feature, $scope))
                ->all())
            ->reject(fn ($scopes) => $scopes === [])
            ->pipe(fn ($features) => $this->getAll($features->all()));
    }

    /**
     * Normalize the features to load.
     *
     * @param  string|array<int|string, mixed>  $features
     * @return \Illuminate\Support\Collection<string, array<int, mixed>>
     */
    protected function normalizeFeaturesToLoad($features)
    {
        return Collection::wrap($features)
            ->mapWithKeys(fn ($value, $key) => is_int($key)
                ? [$value => Collection::make([$this->defaultScope()])]
                : [$key => Collection::wrap($value)])
            ->mapWithKeys(fn ($scopes, $feature) => [
                $this->resolveFeature($feature) => $scopes,
            ])
            ->map(
                fn ($scopes) => $scopes->map(fn ($scope) => $this->resolveScope($scope))->all()
            );
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
        $feature = $this->resolveFeature($feature);

        $scope = $this->resolveScope($scope);

        $item = $this->cache
            ->whereStrict('scope', Feature::serializeScope($scope))
            ->whereStrict('feature', $feature)
            ->first();

        if ($item !== null) {
            Event::dispatch(new FeatureRetrieved($feature, $scope, $item['value']));

            return $item['value'];
        }

        $before = method_exists($feature, 'before')
            ? $this->container->make($feature)->before(...)
            : fn () => null;

        $value = $this->resolveBeforeHook($feature, $scope, $before) ?? $this->driver->get($feature, $scope);

        $this->putInCache($feature, $scope, $value);

        Event::dispatch(new FeatureRetrieved($feature, $scope, $value));

        return $value;
    }

    /**
     * Resolve the before hook value.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @param  callable  $hook
     * @return mixed
     */
    protected function resolveBeforeHook($feature, $scope, $hook)
    {
        if ($scope === null && ! $this->canHandleNullScope($hook)) {
            Event::dispatch(new UnexpectedNullScopeEncountered($feature));

            return null;
        }

        return $hook($scope);
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
        $feature = $this->resolveFeature($feature);

        $scope = $this->resolveScope($scope);

        $this->driver->set($feature, $scope, $value);

        $this->putInCache($feature, $scope, $value);

        Event::dispatch(new FeatureUpdated($feature, $scope, $value));
    }

    /**
     * Activate the feature for everyone.
     *
     * @param  string|array<string>  $feature
     * @param  mixed  $value
     * @return void
     */
    public function activateForEveryone($feature, $value = true)
    {
        Collection::wrap($feature)
            ->each(fn ($name) => $this->setForAllScopes($name, $value));
    }

    /**
     * Deactivate the feature for everyone.
     *
     * @param  string|array<string>  $feature
     * @return void
     */
    public function deactivateForEveryone($feature)
    {
        Collection::wrap($feature)
            ->each(fn ($name) => $this->setForAllScopes($name, false));
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
        $feature = $this->resolveFeature($feature);

        $this->driver->setForAllScopes($feature, $value);

        $this->cache = $this->cache->reject(
            fn ($item) => $item['feature'] === $feature
        );

        Event::dispatch(new FeatureUpdatedForAllScopes($feature, $value));
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
        $feature = $this->resolveFeature($feature);

        $scope = $this->resolveScope($scope);

        $this->driver->delete($feature, $scope);

        $this->removeFromCache($feature, $scope);

        Event::dispatch(new FeatureDeleted($feature, $scope));
    }

    /**
     * Purge the given feature from storage.
     *
     * @param  string|array|null  $features
     */
    public function purge($features = null): void
    {
        if ($features === null) {
            $this->driver->purge(null);

            $this->cache = new Collection;

            Event::dispatch(new AllFeaturesPurged);
        } else {
            Collection::wrap($features)
                ->map($this->resolveFeature(...))
                ->pipe(function ($features) {
                    $this->driver->purge($features->all());

                    $this->cache->forget(
                        $this->cache->whereInStrict('feature', $features)->keys()->all()
                    );

                    Event::dispatch(new FeaturesPurged($features->all()));
                });
        }
    }

    /**
     * Retrieve the feature's name.
     *
     * @param  string  $feature
     * @return string
     */
    public function name($feature)
    {
        return $this->resolveFeature($feature);
    }

    /**
     * Resolve the feature name and ensure it is defined.
     *
     * @param  string  $feature
     * @return string
     */
    protected function resolveFeature($feature)
    {
        return $this->shouldDynamicallyDefine($feature)
            ? $this->ensureDynamicFeatureIsDefined($feature)
            : $feature;
    }

    /**
     * Determine if the feature should be dynamically defined.
     *
     * @param  string  $feature
     * @return bool
     */
    protected function shouldDynamicallyDefine($feature)
    {
        return ! in_array($feature, $this->defined())
            && class_exists($feature)
            && (method_exists($feature, 'resolve') || method_exists($feature, '__invoke'));
    }

    /**
     * Dynamically define the feature.
     *
     * @param  string  $feature
     * @return string
     */
    protected function ensureDynamicFeatureIsDefined($feature)
    {
        return tap($this->container->make($feature)->name ?? $feature, function ($name) use ($feature) {
            if (! in_array($name, $this->defined())) {
                Event::dispatch(new DynamicallyRegisteringFeatureClass($feature));

                $this->define($feature);
            }
        });
    }

    /**
     * Resolve the scope.
     *
     * @param  mixed  $scope
     * @return mixed
     */
    protected function resolveScope($scope)
    {
        return $scope instanceof FeatureScopeable
            ? $scope->toFeatureIdentifier($this->name)
            : $scope;
    }

    /**
     * Determine if a feature's value is in the cache for the given scope.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @return bool
     */
    protected function isCached($feature, $scope)
    {
        $scope = Feature::serializeScope($scope);

        return $this->cache->search(
            fn ($item) => $item['feature'] === $feature && $item['scope'] === $scope
        ) !== false;
    }

    /**
     * Put the given feature's value into the cache.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @param  mixed  $value
     * @return void
     */
    protected function putInCache($feature, $scope, $value)
    {
        $scope = Feature::serializeScope($scope);

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
     * Remove the given feature's value from the cache.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @return void
     */
    protected function removeFromCache($feature, $scope)
    {
        $scope = Feature::serializeScope($scope);

        $position = $this->cache->search(
            fn ($item) => $item['feature'] === $feature && $item['scope'] === $scope
        );

        if ($position !== false) {
            unset($this->cache[$position]);
        }
    }

    /**
     * Retrieve the default scope.
     *
     * @return mixed
     */
    protected function defaultScope()
    {
        return ($this->defaultScopeResolver)();
    }

    /**
     * Flush the in-memory cache of feature values.
     *
     * @return void
     */
    public function flushCache()
    {
        $this->cache = new Collection;
    }

    /**
     * Get the underlying feature driver.
     *
     * @return \Laravel\Pennant\Contracts\Driver
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * Set the container instance used by the decorator.
     *
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Dynamically create a pending feature interaction.
     *
     * @param  string  $name
     * @param  array<mixed>  $parameters
     * @return mixed
     */
    public function __call($name, $parameters)
    {
        if (static::hasMacro($name)) {
            return $this->macroCall($name, $parameters);
        }

        return tap(new PendingScopedFeatureInteraction($this), function ($interaction) use ($name) {
            if ($name !== 'for' && ($this->defaultScopeResolver)() !== null) {
                $interaction->for(($this->defaultScopeResolver)());
            }
        })->{$name}(...$parameters);
    }
}
