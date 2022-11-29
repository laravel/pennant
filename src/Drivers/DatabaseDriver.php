<?php

namespace Laravel\Feature\Drivers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Laravel\Feature\Events\CheckingUnknownFeature;

class DatabaseDriver
{
    /**
     * The database connection.
     *
     * @var \Illuminate\Database\Connection
     */
    protected $db;

    /**
     * The event dispatcher.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $events;

    /**
     * The initial feature state resolvers.
     *
     * @var array<string, (callable(mixed, mixed ...): mixed)>
     */
    protected $initialFeatureStateResolvers = [];

    /**
     * Create a new Database Driver instance.
     *
     * @param  \Illuminate\Database\Connection  $db
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     */
    public function __construct(Connection $db, Dispatcher $events)
    {
        $this->db = $db;

        $this->events = $events;
    }

    /**
     * Determine if the features are active for the given scope.
     *
     * @param  array<int, string>  $features
     * @param  array<int, mixed>  $scope
     * @return bool
     */
    public function isActive($features, $scope = [])
    {
        $query = $this->db->table('features');

        $resolved = $this->resolveFeatureStorageKeys($features, $scope)->each(function ($resolved) use ($query) {
            $query->where('name', $resolved['feature'])->where('scope', $resolved['storageKey']);
        });

        if ($query->get()->isEmpty()) {
            $this->db->table('features')->upsert($resolved->map(fn ($resolved) => [
                'name' => $resolved['feature'],
                'scope' => $resolved['storageKey'],
                'is_active' => $this->resolveInitialFeatureState($resolved['feature'], $resolved['storageKey']),
            ])->all(), [
                'name',
                'scope',
            ], [
                'is_active',
            ]);

            return $resolved->every(fn ($resolved) => $this->resolveInitialFeatureState($resolved['feature'], $resolved['storageKey']));
        }

        $this->events->dispatch(new CheckingUnknownFeature($features[0], $scope[0] ?? null));

        return false;
        // $results = $query->get();

        // dd($query->get());

        // if ($this->featureNotYetCached($cacheKey) && $this->missingResolver($feature)) {

                //     return false;
        // }

        // $this->events->dispatch(new CheckingKnownFeature($feature, $scope));

        // return $this->cache[$cacheKey] ??= $this->resolveInitialFeatureState($feature, $scope);
        // });
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
     * Resolve a features initial state.
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
     * Resolve all permutations of the features and scope storage keys.
     *
     * @param  array<int, string>  $features
     * @param  array<int, mixed>  $scope
     * @return \Illuminate\Support\Collection<int, array{ feature: string, scope: mixed, storageKey: string|null }>
     */
    protected function resolveFeatureStorageKeys($features, $scope)
    {
        return Collection::make($scope)->whenEmpty(fn ($c) => $c->push(null))
            ->map(fn ($scope) => [$this->resolveStorageKey($scope), $scope])
            ->crossJoin($features)
            ->map(fn ($value) => [
                'feature' => $value[1],
                'scope' => $value[0][1],
                'storageKey' => $value[0][0],
            ]);
    }

    /**
     * Resolve the storage key for the given scope.
     *
     * @param  mixed  $scope
     * @return string|null
     */
    protected function resolveStorageKey($scope)
    {
        if ($scope === null) {
            return null;
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
