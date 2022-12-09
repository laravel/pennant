<?php

namespace Laravel\Feature\Drivers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Queue\SerializesAndRestoresModelIdentifiers;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Feature\Contracts\Driver;
use Laravel\Feature\Events\RetrievingKnownFeature;
use Laravel\Feature\Events\RetrievingUnknownFeature;

class DatabaseDriver implements Driver
{
    use SerializesAndRestoresModelIdentifiers;

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
     * The feature state resolvers.
     *
     * @var array<string, (callable(mixed): mixed)>
     */
    protected $featureStateResolvers;

    /**
     * The scope comparator.
     *
     * @var (callable(mixed, mixed, string): bool)
     */
    protected $scopeComparator;

    /**
     * Create a new driver instance.
     *
     * @param  \Illuminate\Database\Connection  $db
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @param  (callable(mixed, mixed, string): bool)  $scopeComparator
     * @param  array<string, (callable(mixed $scope): mixed)>  $featureStateResolvers
     */
    public function __construct(Connection $db, Dispatcher $events, $scopeComparator, $featureStateResolvers)
    {
        $this->db = $db;

        $this->events = $events;

        $this->scopeComparator = $scopeComparator;

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
        $record = $this->retrieve($feature, $scope);

        if ($record !== null) {
            return unserialize($record->value);
        }

        return tap($this->resolveValue($feature, $scope), function ($value) use ($feature, $scope) {
            $this->insert($feature, $scope, $value);
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
        if (! $this->update($feature, $scope, $value)) {
            $this->insert($feature, $scope, $value);
        }
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
        $query = $this->db->table('features');

        $features = Collection::make($features)
            ->map(fn ($scopes, $feature) => Collection::make($scopes)
                ->each(fn ($scope) => $query->orWhere(fn ($q) => $q->where('name', $feature)->where('scope', $scope))));

        $records = $query->get();
        $inserts = new Collection;

        $results = $features->map(fn ($scopes, $feature) => $scopes->map(function ($scope) use ($feature, $records, $inserts) {
            if ($records->where('name', $feature)->where('scope', $scope)->isNotEmpty()) {
                return unserialize($records->where('name', $feature)->where('scope', $scope)->value('value'));
            }

            return tap($this->resolveValue($feature, $scope), function ($value) use ($feature, $scope, $inserts) {
                $inserts[] = [
                    'name' => $feature,
                    'scope' => $scope,
                    'value' => serialize($value),
                ];
            });
        })->all())->all();

        if ($inserts->isNotEmpty()) {
            $this->db->table('features')->insert($inserts->all());
        }

        return $results;
    }

    /**
     * Retrieve the value in storage.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @return object|null
     */
    protected function retrieve($feature, $scope)
    {
        return $this->db->table('features')
            ->where('name', '=', $feature)
            ->where('scope', '=', $scope)
            ->first();
    }

    /**
     * Insert the value in storage.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @param  mixed  $value
     * @return bool
     */
    protected function insert($feature, $scope, $value)
    {
        return $this->db->table('features')->insert([
            'name' => $feature,
            'scope' => $scope,
            'value' => serialize($value),
        ]);
    }

    /**
     * Update the value in storage.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @param  mixed  $value
     * @return bool
     */
    protected function update($feature, $scope, $value)
    {
        return $this->db->table('features')
            ->where('name', $feature)
            ->where('scope', $scope)
            ->update([
                'value' => serialize($value),
            ]) > 0;
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
        if (! array_key_exists($feature, $this->featureStateResolvers)) {
            $this->events->dispatch(new RetrievingUnknownFeature($feature, $scope));

            return false;
        }

        return tap($this->featureStateResolvers[$feature]($scope), function ($value) use ($feature, $scope) {
            $this->events->dispatch(new RetrievingKnownFeature($feature, $scope, $value));

            return $value;
        });
    }
}
