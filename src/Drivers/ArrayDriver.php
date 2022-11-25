<?php

namespace Laravel\Feature\Drivers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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
     * @var array<string, bool>
     */
    protected $cache = [];

    /**
     * The initial feature state resolvers.
     *
     * @var array<string, (callable(mixed): bool)>
     */
    protected $initialFeatureStateResolvers = [];

    /**
     * Create a new driver instance.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     */
    public function __construct(Dispatcher $events)
    {
        $this->events = $events;
    }

    /**
     * Determine if the feature(s) is active for the given scope.
     *
     * @param  string|array<int, string>  $feature
     * @param  mixed  $scope
     * @return bool
     */
    public function isActive($feature, $scope = null)
    {
        return $this->resolveFeatureCacheKeys($feature, $scope)
            ->every(function ($feature, $cacheKey) use ($scope) {
                if ($this->featureNotYetCached($cacheKey) && $this->missingResolver($feature)) {
                    $this->events->dispatch(new CheckingUnknownFeature($feature, $scope));

                    return false;
                }

                $this->events->dispatch(new CheckingKnownFeature($feature, $scope));

                return $this->cache[$cacheKey] ??= $this->resolveInitialFeatureState($feature, $scope);
            });
    }

    /**
     * Determine if the feature(s) is inactive for the given scope.
     *
     * TODO: does this make sense to just invert?  I think there could be an
     * issue here. Need to test futher.
     *
     * @param  string|array<int, string>  $feature
     * @param  mixed  $scope
     * @return bool
     */
    public function isInactive($feature, $scope = null)
    {
        return ! $this->isActive($feature, $scope);
    }

    /**
     * Activate the feature(s) for the given scope.
     *
     * @param  string|array<int, string>  $feature
     * @param  mixed  $scope
     * @return void
     */
    public function activate($feature, $scope = null)
    {
        $this->cache = array_merge(
            $this->cache,
            $this->resolveFeatureCacheKeys($feature, $scope)
                ->map(fn () => true)
                ->all()
        );
    }

    /**
     * Deactivate the feature(s) for the given scope.
     *
     * @param  string|array<int, string>  $feature
     * @param  mixed  $scope
     * @return void
     */
    public function deactivate($feature, $scope = null)
    {
        $this->cache = array_merge(
            $this->cache,
            $this->resolveFeatureCacheKeys($feature, $scope)
                ->map(fn () => false)
                ->all()
        );
    }

    /**
     * Resolve an initial features state.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @return bool
     */
    protected function resolveInitialFeatureState($feature, $scope)
    {
        return (bool) $this->initialFeatureStateResolvers[$feature]($scope);
    }

    /**
     * Register an initial feature state resolver.
     *
     * TODO: Should this return a pending registration object so we can do
     * interesting modifications while registering?
     *
     * @param  string  $feature
     * @param  (callable(mixed $scope): bool)  $resolver
     * @return void
     */
    public function register($feature, $resolver)
    {
        $this->initialFeatureStateResolvers[$feature] = $resolver;
    }

    /**
     * Determine if the feature has not yet been cached.
     *
     * @param  string  $cacheKey
     * @return bool
     */
    protected function featureNotYetCached($cacheKey)
    {
        return ! array_key_exists($cacheKey, $this->cache);
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
     * Resolve all permutations of the feature(s) and scope cache keys.
     *
     * @param  string|array<string>  $feature
     * @param  mixed  $scope
     * @return \Illuminate\Support\Collection<string, string>
     */
    protected function resolveFeatureCacheKeys($feature, $scope)
    {
        return Collection::wrap($scope)
            ->whenEmpty(fn ($collection) => $collection->merge([null]))
            ->map(fn ($item) => $this->resolveCacheKey($feature, $item))
            ->crossJoin(Arr::wrap($feature))
            ->mapWithKeys(fn ($value) => [
                ($value[0] === null ? "{$value[1]}" : "{$value[1]}:{$value[0]}") => $value[1],
            ]);
    }

    /**
     * Resolve the cache key for the given feature and scope.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @return string|null
     */
    protected function resolveCacheKey($feature, $scope)
    {
        if ($scope === null) {
            return null;
        }

        if ($scope instanceof FeatureScopeable) {
            return $scope->toFeatureScopeIdentifier();
        }

        if ($scope instanceof Model) {
            return 'eloquent_model:'.(new $scope)->getMorphClass().':'.$scope->getKey();
        }

        return (string) $scope;
    }
}
