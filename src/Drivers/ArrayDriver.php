<?php

namespace Laravel\Feature\Drivers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Laravel\Feature\Contracts\Driver;
use Laravel\Feature\Events\CheckingKnownFeature;
use Laravel\Feature\Events\CheckingUnknownFeature;

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
     * Create a new driver instance.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @param  array<string, (callable(mixed $scope): mixed)>  $featureStateResolvers
     */
    public function __construct(Dispatcher $events, $featureStateResolvers = [])
    {
        $this->events = $events;

        $this->featureStateResolvers = $featureStateResolvers;
    }

    /**
     * Retrieve the flags value.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @return bool
     */
    public function get($feature, $scope)
    {
        if ($this->missingResolver($feature)) {
            $this->events->dispatch(new CheckingUnknownFeature($feature, $scope)); // rename event to "retrieving unknown feature"

            return false;
        }

        return tap($this->featureStateResolvers[$feature]($scope), function ($value) use ($feature, $scope) {
            $this->events->dispatch(new CheckingKnownFeature($feature, $scope, $value)); // rename event to "retrieving known feature"
        });
    }

    /**
     * Activate the feature.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @param  mixed  $value
     * @return void
     */
    public function set($feature, $scope, $value)
    {
        $existing = $this->featureStateResolvers[$feature] ?? fn () => false;

        $this->register($feature, fn ($s) => $scope === $s ? $value : $existing($s)); // TODO: this comparison needs to be handled elsewhere and allow for configuration.
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
        $this->featureStateResolvers[$feature] = $resolver;
    }

    /**
     * Eagerly load the feature state into memory.
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
     * Determine if the feature has no resolver available.
     *
     * @param  string  $feature
     * @return bool
     */
    protected function missingResolver($feature)
    {
        return ! array_key_exists($feature, $this->featureStateResolvers);
    }
}
