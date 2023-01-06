<?php

namespace Laravel\Feature\Drivers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Feature\Contracts\Driver;
use Laravel\Feature\Events\RetrievingKnownFeature;
use Laravel\Feature\Events\RetrievingUnknownFeature;
use RuntimeException;
use stdClass;

class ArrayDriver implements Driver
{
    /**
     * The event dispatcher.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $events;

    /**
     * The feature state resolvers.
     *
     * @var array<string, (callable(mixed): mixed)>
     */
    protected $featureStateResolvers;

    /**
     * The resolved feature states.
     *
     * @var array<string, array<string, mixed>>
     */
    protected $resolvedFeatureStates = [];

    /**
     * The sentinel value for unknown features.
     *
     * @var \stdClass
     */
    protected $unknownFeatureValue;

    /**
     * Create a new driver instance.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @param  array<string, (callable(mixed $scope): mixed)>  $featureStateResolvers
     */
    public function __construct(Dispatcher $events, $featureStateResolvers)
    {
        $this->events = $events;
        $this->featureStateResolvers = $featureStateResolvers;

        $this->unknownFeatureValue = new stdClass;
    }

    /**
     * Retrieve a feature flag's value.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @return mixed
     */
    public function get($feature, $scope)
    {
        $scopeKey = $this->serializeScope($scope);

        if (isset($this->resolvedFeatureStates[$feature][$scopeKey])) {
            return $this->resolvedFeatureStates[$feature][$scopeKey];
        }

        return with($this->resolveValue($feature, $scope), function ($value) use ($feature, $scopeKey) {
            if ($value === $this->unknownFeatureValue) {
                return false;
            }

            $this->set($feature, $scopeKey, $value);

            return $value;
        });
    }

    /**
     * Set the flags value.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @param  mixed  $value
     * @return void
     */
    public function set($feature, $scope, $value)
    {
        $this->resolvedFeatureStates[$feature] ??= [];

        $this->resolvedFeatureStates[$feature][$this->serializeScope($scope)] = $value;
    }

    /**
     * Clear the flags value.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @return void
     */
    public function delete($feature, $scope)
    {
        unset($this->resolvedFeatureStates[$feature][$this->serializeScope($scope)]);
    }

    /**
     * Purge the given feature.
     *
     * @param  string|null  $feature
     * @return void
     */
    public function purge($feature = null)
    {
        throw new RuntimeException('The array driver does not support purging.');
    }

    /**
     * Register an initial flag state resolver.
     *
     * @param  string  $feature
     * @param  (callable(mixed $scope): mixed)  $resolver
     * @return void
     */
    public function register($feature, $resolver)
    {
        $this->featureStateResolvers[$feature] = $resolver;
    }

    /**
     * Retrieve mutliple flags values.
     *
     * @param  array<string, array<int, mixed>>  $features
     * @return array<string, array<int, mixed>>
     */
    public function load($features)
    {
        return Collection::make($features)
            ->map(fn ($scopes, $feature) => Collection::make($scopes)
                ->map(fn ($scope) => $this->get($feature, $scope))
                ->all())
            ->all();
    }

    /**
     * Retrieve the registered features.
     *
     * @return array<string>
     */
    public function registered()
    {
        return array_keys($this->featureStateResolvers);
    }

    /**
     * Determine if the feature does not have a resolver available.
     *
     * @param  string  $feature
     * @return bool
     */
    protected function missingResolver($feature)
    {
        return ! array_key_exists($feature, $this->featureStateResolvers);
    }

    /**
     * Flush the resolved feature states.
     *
     * @return void
     */
    public function flushCache()
    {
        $this->resolvedFeatureStates = [];
    }

    /**
     * Determine the initial feature value.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @return mixed
     */
    protected function resolveValue($feature, $scope)
    {
        if ($this->missingResolver($feature)) {
            $this->events->dispatch(new RetrievingUnknownFeature($feature, $scope));

            return false;
        }

        return tap($this->featureStateResolvers[$feature]($scope), function ($value) use ($feature, $scope) {
            $this->events->dispatch(new RetrievingKnownFeature($feature, $scope, $value));
        });
    }

    /**
     * Serialize the scope for storage.
     *
     * @param  mixed  $scope
     * @return string
     */
    protected function serializeScope($scope)
    {
        if ($scope === null) {
            return '__laravel_null';
        }

        if (is_string($scope)) {
            return $scope;
        }

        if (is_numeric($scope)) {
            return (string) $scope;
        }

        if ($scope instanceof Model) {
            return $scope::class.'|'.$scope->getKey();
        }

        throw new RuntimeException('Unable to serialize the feature scope to a string. You should implement the FeatureScopeable contract.');
    }
}
