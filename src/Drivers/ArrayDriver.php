<?php

namespace Laravel\Feature\Drivers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Feature\Contracts\FeatureScopeable;
use Laravel\Feature\Events\CheckingKnownFeature;
use Laravel\Feature\Events\CheckingUnknownFeature;

class ArrayDriver
{
    /**
     * The event dispatcher.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $events;

    /**
     * The current state of the features.
     *
     * @var \Illuminate\Support\Collection<string, bool>
     */
    protected $cache;

    /**
     * The key to use when encountering a null value to differentiate from an empty string.
     *
     * @var string
     */
    protected $nullKey;

    /**
     * The initial feature state resolvers.
     *
     * @var array<string, (callable(mixed, mixed ...): mixed)>
     */
    protected $initialFeatureStateResolvers = [];

    /**
     * Create a new driver instance.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @param  \Illuminate\Support\Collection<string, bool>  $cache
     * @param  ?string  $nullKey
     */
    public function __construct(Dispatcher $events, $cache = new Collection(), $nullKey = null)
    {
        $this->events = $events;

        $this->cache = $cache;

        $this->nullKey = $nullKey ?? Str::random();
    }

    /**
     * Determine if the features are active for the given scope.
     *
     * @param  array<int, string>  $features
     * @param  mixed  $scope
     * @return bool
     */
    public function isActive($features, $scope)
    {
        return $this->resolve($features, $scope)->every(function ($resolved) {
            ['scope' => $scope, 'key' => $key, 'name' => $name] = $resolved;

            if ($this->resultNotYetKnown($key) && $this->missingResolver($name)) {
                $this->events->dispatch(new CheckingUnknownFeature($name, $scope));

                return false;
            }

            $this->events->dispatch(new CheckingKnownFeature($name, $scope));

            return $this->cache[$key] ??= $this->resolveInitialFeatureState($name, $scope);
        });
    }

    /**
     * Determine if the feature are inactive for the given scope.
     *
     * TODO: does this make sense to just invert?  I think there could be an
     * issue here. Need to test futher.
     *
     * @param  array<int, string>  $features
     * @param  array<int, mixed>  $scope
     * @return bool
     */
    public function isInactive($features, $scope = [])
    {
        return ! $this->isActive($features, $scope);
    }

    /**
     * Activate the features for the given scope.
     *
     * @param  array<int, string>  $features
     * @param  array<int, mixed>  $scope
     * @return void
     */
    public function activate($features, $scope = [])
    {
        $this->cache = $this->cache->merge(
            $this->resolve($features, $scope)
                ->mapWithKeys(fn ($resolved) => [
                    $resolved['key'] => true,
                ])
        );
    }

    /**
     * Deactivate the features for the given scope.
     *
     * @param  array<int, string>  $features
     * @param  array<int, mixed>  $scope
     * @return void
     */
    public function deactivate($features, $scope = [])
    {
        $this->cache = $this->cache->merge(
            $this->resolve($features, $scope)
                ->mapWithKeys(fn ($resolved) => [
                    $resolved['key'] => false,
                ])
        );
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
        $this->initialFeatureStateResolvers[$feature] = $resolver;
    }

    /**
     * Eagerly load the feature state into memory.
     *
     * @param  string|array<string|int, array<int, mixed>|string>  $features
     * @return void
     */
    public function load($features)
    {
        Collection::wrap($features)
            ->mapWithKeys(fn ($value, $key) => is_int($key) ? [$value => []] : [$key => $value])
            ->flatMap(fn ($scope, $feature) => $this->resolve([$feature], $scope))
            ->each(function ($resolved) {
                ['scope' => $scope, 'key' => $key, 'name' => $name] = $resolved;

                if ($this->missingResolver($name)) {
                    return;
                }

                $this->cache[$key] = $this->resolveInitialFeatureState($name, $scope);
            });
    }

    /**
     * Eagerly load the missing feature state into memory.
     *
     * @param  string|array<string|int, array<int, mixed>|string>  $features
     * @return void
     */
    public function loadMissing($features)
    {
        Collection::make($features)
            ->mapWithKeys(fn ($value, $key) => is_int($key) ? [$value => []] : [$key => $value])
            ->flatMap(fn ($scope, $feature) => $this->resolve([$feature], $scope))
            ->each(function ($resolved) {
                ['scope' => $scope, 'key' => $key, 'name' => $name] = $resolved;

                if ($this->missingResolver($name)) {
                    return;
                }

                $this->cache[$key] ??= $this->resolveInitialFeatureState($name, $scope);
            });
    }

    /**
     * Resolve a features initial state.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @return bool
     */
    protected function resolveInitialFeatureState($feature, $scope)
    {
        return $this->initialFeatureStateResolvers[$feature]($scope) !== false;
    }

    /**
     * Determine if a result already exists.
     *
     * @param  string  $key
     * @return bool
     */
    protected function resultNotYetKnown($key)
    {
        return ! $this->cache->has($key);
    }

    /**
     * Determine if the feature has no resolver available.
     *
     * @param  string  $feature
     * @return bool
     */
    protected function missingResolver($feature)
    {
        return ! array_key_exists($feature, $this->initialFeatureStateResolvers);
    }

    /**
     * Resolve all permutations of the features and scope cache keys.
     *
     * @param  array<int, string>  $features
     * @param  array<int, mixed>  $scope
     * @return \Illuminate\Support\Collection<int, array{ feature: string, scope: mixed, key: string }>
     */
    protected function resolve($features, $scope)
    {
        if ($scope === []) {
            return Collection::make($features)->map(fn ($feature) => [
                'name' => $feature,
                'scope' => null,
                'key' => $feature,
            ]);
        }

        return Collection::make($scope)
            ->crossJoin($features)
            ->mapSpread(fn ($scope, $feature) => [
                'name' => $feature,
                'scope' => $scope,
                'key' => "{$feature}:{$this->resolveKey($scope)}",
            ]);
    }

    /**
     * Resolve the key for the given scope.
     *
     * @param  mixed  $scope
     * @return string
     */
    protected function resolveKey($scope)
    {
        if ($scope === null) {
            return $this->nullKey;
        }

        if ($scope instanceof FeatureScopeable) {
            return (string) $scope->toFeatureScopeIdentifier();
        }

        if ($scope instanceof Model) {
            return 'eloquent_model:'.(new $scope)->getMorphClass().':'.$scope->getKey();
        }

        return (string) $scope;
    }
}
