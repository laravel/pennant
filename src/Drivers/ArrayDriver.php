<?php

namespace Laravel\Feature\Drivers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Laravel\Feature\Contracts\Driver;
use Laravel\Feature\Events\RetrievingUnknownFeature;
use Laravel\Feature\Events\RetrievingKnownFeature;
use Laravel\Feature\FeatureManager;

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
     * The scope comparator.
     *
     * @var callable(mixed, mixed): bool
     */
    protected $scopeComparator;

    /**
     * Create a new driver instance.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @param  (callable(mixed, mixed): bool)  $scopeComparator
     * @param  array<string, (callable(mixed $scope): mixed)>  $featureStateResolvers
     */
    public function __construct(Dispatcher $events, $scopeComparator, $featureStateResolvers)
    {
        $this->events = $events;

        $this->scopeComparator = $scopeComparator;

        $this->featureStateResolvers = $featureStateResolvers;
    }

    /**
     * Retrieve the flags value.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @return mixed
     */
    public function get($feature, $scope)
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
     * Set the flags value.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @param  mixed  $value
     * @return void
     */
    public function set($feature, $scope, $value)
    {
        // TODO: are we worried about memory leaks here?
        $existing = $this->featureStateResolvers[$feature] ?? fn () => false;

        $this->register($feature, fn ($s) => ($this->scopeComparator)($scope, $s) ? $value : $existing($s));
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
        //
    }

    /**
     * Delete any feature flags that are no longer registered.
     *
     * @return void
     */
    public function prune()
    {
        //
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
}
