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
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, mixed>>  $scope
     * @return bool
     */
    public function isActive($features, $scope = new Collection)
    {
        return $this->resolveFeatureCacheKeys($features, $scope)->every(function ($resolved) {
            ['scope' => $scope, 'cacheKey' => $cacheKey, 'feature' => $feature] = $resolved;

            if ($this->featureNotYetCached($cacheKey) && $this->missingResolver($feature)) {
                $this->events->dispatch(new CheckingUnknownFeature($feature, $scope));

                return false;
            }

            $this->events->dispatch(new CheckingKnownFeature($feature, $scope));

            return $this->cache[$cacheKey] ??= $this->resolveInitialFeatureState($feature, $scope);
        });
    }

    /**
     * Determine if the feature are inactive for the given scope.
     *
     * TODO: does this make sense to just invert?  I think there could be an
     * issue here. Need to test futher.
     *
     * @param  array<int, string>  $features
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, mixed>>  $scope
     * @return bool
     */
    public function isInactive($features, $scope = new Collection)
    {
        return ! $this->isActive($features, $scope);
    }

    /**
     * Activate the features for the given scope.
     *
     * @param  array<int, string>  $features
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, mixed>>  $scope
     * @return void
     */
    public function activate($features, $scope = new Collection)
    {
        $this->cache = $this->cache->merge(
            $this->resolveFeatureCacheKeys($features, $scope)
                ->mapWithKeys(fn ($resolved) => [
                    $resolved['cacheKey'] => true,
                ])
        );
    }

    /**
     * Deactivate the features for the given scope.
     *
     * @param  array<int, string>  $features
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, mixed>>  $scope
     * @return void
     */
    public function deactivate($features, $scope = new Collection)
    {
        $this->cache = $this->cache->merge(
            $this->resolveFeatureCacheKeys($features, $scope)
                ->mapWithKeys(fn ($resolved) => [
                    $resolved['cacheKey'] => false,
                ])
        );
    }

    /**
     * Register an initial feature state resolver.
     *
     * @param  string  $feature
     * @param  (callable(mixed $scope, mixed ...$additional): mixed)  $resolver
     * @return void
     */
    public function register($feature, $resolver)
    {
        $this->initialFeatureStateResolvers[$feature] = $resolver;
    }

    /**
     * Eagerly load the feature state into memory.
     *
     * @param  array<string|int, array<int, mixed>|string>  $feature
     * @return void
     */
    public function load($features)
    {
        Collection::make($features)
            ->mapWithKeys(fn ($value, $key) => is_int($key)
                ? [$value => Collection::make([])]
                : [$key => Collection::make($value)->map(fn ($v) => Collection::make($v))])
            ->map(function ($scope, $feature) {
                return $this->resolveFeatureCacheKeys([$feature], $scope);
            })
            ->flatten(1)
            ->each(function ($resolved) {
                ['scope' => $scope, 'cacheKey' => $cacheKey, 'feature' => $feature] = $resolved;

                if ($this->missingResolver($feature)) {
                    return;
                }

                $this->cache[$cacheKey] = $this->resolveInitialFeatureState($feature, $scope);
            });
    }

    /**
     * Eagerly load the missing feature state into memory.
     *
     * @param  array<string|int, array<int, mixed>|string>  $feature
     * @return void
     */
    public function loadMissing($features)
    {
        Collection::make($features)
            ->mapWithKeys(fn ($value, $key) => is_int($key)
                ? [$value => Collection::make([])]
                : [$key => Collection::make($value)->map(fn ($v) => Collection::make($v))])
            ->map(function ($scope, $feature) {
                return $this->resolveFeatureCacheKeys([$feature], $scope);
            })
            ->flatten(1)
            ->each(function ($resolved) {
                ['scope' => $scope, 'cacheKey' => $cacheKey, 'feature' => $feature] = $resolved;

                if ($this->missingResolver($feature)) {
                    return;
                }

                $this->cache[$cacheKey] ??= $this->resolveInitialFeatureState($feature, $scope);
            });
    }

    /**
     * Resolve a features initial state.
     *
     * @param  string  $feature
     * @param  \Illuminate\Support\Collection<int, mixed>  $scope
     * @return bool
     */
    protected function resolveInitialFeatureState($feature, $scope)
    {
        return (bool) $this->initialFeatureStateResolvers[$feature](...$scope);
    }

    /**
     * Determine if the feature has not yet been cached.
     *
     * @param  string  $cacheKey
     * @return bool
     */
    protected function featureNotYetCached($cacheKey)
    {
        return ! $this->cache->has($cacheKey);
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
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, mixed>>  $scope
     * @return \Illuminate\Support\Collection<int, array{ feature: string, scope: \Illuminate\Support\Collection<int, mixed>, cacheKey: string }>
     */
    protected function resolveFeatureCacheKeys($features, $scope)
    {
        return $scope->whenEmpty(fn ($c) => $c->push(Collection::make([null])))
            ->map(fn ($scope) => [$this->resolveCacheKey($scope), $scope])
            ->crossJoin($features)
            ->map(fn ($value) => [
                'feature' => $value[1],
                'scope' => $value[0][1],
                'cacheKey' => "{$value[1]}:{$value[0][0]}",
            ]);
    }

    /**
     * Resolve the cache key for the given scope.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $scope
     * @return string
     */
    protected function resolveCacheKey($scope)
    {
        return $scope->whenEmpty(fn ($c) => $c->push(null))
            ->map(function ($scope) {
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
            })->join(',');
    }
}
