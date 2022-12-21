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
     * @var (callable(mixed, mixed): bool)
     */
    protected $scopeComparator;

    /**
     * Create a new driver instance.
     *
     * @param  \Illuminate\Database\Connection  $db
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @param  (callable(mixed, mixed): bool)  $scopeComparator
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
     * @return mixed
     */
    public function get($feature, $scope)
    {
        if (($record = $this->retrieve($feature, $scope)) !== null) {
            return json_decode($record->value, flags:  JSON_OBJECT_AS_ARRAY|JSON_THROW_ON_ERROR);
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
     * Clear the flags value.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @return void
     */
    public function clear($feature, $scope)
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
        $query = $this->db->table('features');

        $features = Collection::make($features)
            ->map(fn ($scopes, $feature) => Collection::make($scopes)
                ->each(fn ($scope) => $query->orWhere(fn ($q) => $q->where('name', $feature)->where('scope', $scope))));

        $records = $query->get();
        $inserts = new Collection;

        $results = $features->map(fn ($scopes, $feature) => $scopes->map(function ($scope) use ($feature, $records, $inserts) {
            if ($records->where('name', $feature)->where('scope', $scope)->isNotEmpty()) {
                $value = $records->where('name', $feature)->where('scope', $scope)->value('value');

                return json_decode($value, flags:  JSON_OBJECT_AS_ARRAY|JSON_THROW_ON_ERROR);
            }

            return tap($this->resolveValue($feature, $scope), function ($value) use ($feature, $scope, $inserts) {
                $inserts[] = [
                    'name' => $feature,
                    'scope' => $scope,
                    'value' => json_encode($value, flags: JSON_THROW_ON_ERROR),
                ];
            });
        })->all())->all();

        if ($inserts->isNotEmpty()) {
            $this->db->table('features')->insert($inserts->all());
        }

        return $results;
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
     * Retrieve the value from storage.
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
     * Insert the value into storage.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @param  mixed  $value
     * @return bool
     */
    protected function insert($feature, $scope, $value)
    {
        // TODO will need to serialize the scope in a good way.
        return $this->db->table('features')->insert([
            'name' => $feature,
            'scope' => $scope,
            'value' => json_encode($value, flags: JSON_THROW_ON_ERROR),
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
                'value' => json_encode($value, flags: JSON_THROW_ON_ERROR),
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
        });
    }
}
