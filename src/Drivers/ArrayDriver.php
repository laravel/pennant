<?php

namespace Laravel\Pennant\Drivers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Pennant\Contracts\Driver;
use Laravel\Pennant\Events\FeatureResolved;
use Laravel\Pennant\Events\UnknownFeatureResolved;
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
     * @return void
     */
    public function __construct(Dispatcher $events, $featureStateResolvers)
    {
        $this->events = $events;
        $this->featureStateResolvers = $featureStateResolvers;

        $this->unknownFeatureValue = new stdClass;
    }

    /**
     * Define an initial feature flag state resolver.
     *
     * @param  string  $feature
     * @param  (callable(mixed $scope): mixed)  $resolver
     */
    public function define($feature, $resolver): void
    {
        $this->featureStateResolvers[$feature] = $resolver;
    }

    /**
     * Retrieve the names of all defined features.
     *
     * @return array<string>
     */
    public function defined(): array
    {
        return array_keys($this->featureStateResolvers);
    }

    /**
     * Retrieve a feature flag's value.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     */
    public function get($feature, $scope): mixed
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
     * Determine the initial value for a given feature and scope.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @return mixed
     */
    protected function resolveValue($feature, $scope)
    {
        if ($this->missingResolver($feature)) {
            $this->events->dispatch(new UnknownFeatureResolved($feature, $scope));

            return $this->unknownFeatureValue;
        }

        return tap($this->featureStateResolvers[$feature]($scope), function ($value) use ($feature, $scope) {
            $this->events->dispatch(new FeatureResolved($feature, $scope, $value));
        });
    }

    /**
     * Set a feature flag's value.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @param  mixed  $value
     */
    public function set($feature, $scope, $value): void
    {
        $this->resolvedFeatureStates[$feature] ??= [];

        $this->resolvedFeatureStates[$feature][$this->serializeScope($scope)] = $value;
    }

    /**
     * Set a feature flag's value for all scopes.
     *
     * @param  string  $feature
     * @param  mixed  $value
     */
    public function setForAllScopes($feature, $value): void
    {
        $this->resolvedFeatureStates[$feature] ??= [];

        foreach ($this->resolvedFeatureStates[$feature] as $scope => $currentValue) {
            $this->resolvedFeatureStates[$feature][$scope] = $value;
        }
    }

    /**
     * Delete a feature flag's value.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     */
    public function delete($feature, $scope): void
    {
        unset($this->resolvedFeatureStates[$feature][$this->serializeScope($scope)]);
    }

    /**
     * Purge the given feature from storage.
     *
     * @param  array|null  $features
     */
    public function purge($features): void
    {
        if ($features === null) {
            $this->resolvedFeatureStates = [];
        } else {
            foreach ($features as $feature) {
                unset($this->resolvedFeatureStates[$feature]);
            }
        }
    }

    /**
     * Eagerly preload multiple feature flag values.
     *
     * @param  array<string, array<int, mixed>>  $features
     * @return array<string, array<int, mixed>>
     */
    public function load($features): array
    {
        return Collection::make($features)
            ->map(fn ($scopes, $feature) => Collection::make($scopes)
                ->map(fn ($scope) => $this->get($feature, $scope))
                ->all())
            ->all();
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
     * Serialize the given scope for storage.
     *
     * @param  mixed  $scope
     * @return string
     */
    protected function serializeScope($scope)
    {
        return match (true) {
            $scope === null => '__laravel_null',
            is_string($scope) => $scope,
            is_numeric($scope) => (string) $scope,
            $scope instanceof Model => $scope::class.'|'.$scope->getKey(),
            default => throw new RuntimeException('Unable to serialize the feature scope to a string. You should implement the FeatureScopeable contract.')
        };
    }
}
