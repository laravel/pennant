<?php

namespace Laravel\Pennant\Drivers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Pennant\Contracts\Driver;
use Laravel\Pennant\Events\UnknownFeatureResolved;
use Laravel\Pennant\Feature;
use stdClass;

class DatabaseDriver implements Driver
{
    /**
     * The database connection.
     *
     * @var \Illuminate\Database\Connection
     */
    protected $db;

    /**
     * The user configuration.
     *
     * @var array{connection?: string|null, table?: string|null}
     */
    protected $config;

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
     * The sentinel value for unknown features.
     *
     * @var \stdClass
     */
    protected $unknownFeatureValue;

    /**
     * Create a new driver instance.
     *
     * @param  array{connection?: string|null, table?: string|null}  $config
     * @param  array<string, (callable(mixed $scope): mixed)>  $featureStateResolvers
     * @return void
     */
    public function __construct(Connection $db, Dispatcher $events, $config, $featureStateResolvers)
    {
        $this->db = $db;
        $this->events = $events;
        $this->config = $config;
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
     * Define the names of all defined features.
     *
     * @return array<string>
     */
    public function defined(): array
    {
        return array_keys($this->featureStateResolvers);
    }

    /**
     * Get multiple feature flag values.
     *
     * @param  array<string, array<int, mixed>>  $features
     * @return array<string, array<int, mixed>>
     */
    public function getAll($features): array
    {
        $query = $this->newQuery();

        $features = Collection::make($features)
            ->map(fn ($scopes, $feature) => Collection::make($scopes)
                ->each(fn ($scope) => $query->orWhere(
                    fn ($q) => $q->where('name', $feature)->where('scope', Feature::serializeScope($scope))
                )));

        $records = $query->get();

        $inserts = new Collection;

        $results = $features->map(fn ($scopes, $feature) => $scopes->map(function ($scope) use ($feature, $records, $inserts) {
            $filtered = $records->where('name', $feature)->where('scope', Feature::serializeScope($scope));

            if ($filtered->isNotEmpty()) {
                return json_decode($filtered->value('value'), flags: JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR);
            }

            return with($this->resolveValue($feature, $scope), function ($value) use ($feature, $scope, $inserts) {
                if ($value === $this->unknownFeatureValue) {
                    return false;
                }

                $inserts[] = [
                    'name' => $feature,
                    'scope' => Feature::serializeScope($scope),
                    'value' => json_encode($value, flags: JSON_THROW_ON_ERROR),
                ];

                return $value;
            });
        })->all())->all();

        if ($inserts->isNotEmpty()) {
            $now = Carbon::now();

            $this->newQuery()->insert($inserts->map(fn ($insert) => [
                ...$insert,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        }

        return $results;
    }

    /**
     * Retrieve a feature flag's value.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     */
    public function get($feature, $scope): mixed
    {
        if (($record = $this->retrieve($feature, $scope)) !== null) {
            return json_decode($record->value, flags: JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR);
        }

        return with($this->resolveValue($feature, $scope), function ($value) use ($feature, $scope) {
            if ($value === $this->unknownFeatureValue) {
                return false;
            }

            $this->insert($feature, $scope, $value);

            return $value;
        });
    }

    /**
     * Retrieve the value for the given feature and scope from storage.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @return object|null
     */
    protected function retrieve($feature, $scope)
    {
        return $this->newQuery()
            ->where('name', $feature)
            ->where('scope', Feature::serializeScope($scope))
            ->first();
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
        if (! array_key_exists($feature, $this->featureStateResolvers)) {
            $this->events->dispatch(new UnknownFeatureResolved($feature, $scope));

            return $this->unknownFeatureValue;
        }

        return $this->featureStateResolvers[$feature]($scope);
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
        if (! $this->update($feature, $scope, $value)) {
            $this->insert($feature, $scope, $value);
        }
    }

    /**
     * Set a feature flag's value for all scopes.
     *
     * @param  string  $feature
     * @param  mixed  $value
     */
    public function setForAllScopes($feature, $value): void
    {
        $this->newQuery()
            ->where('name', $feature)
            ->update([
                'value' => json_encode($value, flags: JSON_THROW_ON_ERROR),
                'updated_at' => Carbon::now(),
            ]);
    }

    /**
     * Update the value for the given feature and scope in storage.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @param  mixed  $value
     * @return bool
     */
    protected function update($feature, $scope, $value)
    {
        $exists = $this->newQuery()
            ->where('name', $feature)
            ->where('scope', $serialized = Feature::serializeScope($scope))
            ->exists();

        if (! $exists) {
            return false;
        }

        $this->newQuery()
            ->where('name', $feature)
            ->where('scope', $serialized)
            ->update([
                'value' => json_encode($value, flags: JSON_THROW_ON_ERROR),
                'updated_at' => Carbon::now(),
            ]);

        return true;
    }

    /**
     * Insert the value for the given feature and scope into storage.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @param  mixed  $value
     * @return bool
     */
    protected function insert($feature, $scope, $value)
    {
        return $this->newQuery()->insert([
            'name' => $feature,
            'scope' => Feature::serializeScope($scope),
            'value' => json_encode($value, flags: JSON_THROW_ON_ERROR),
            'created_at' => $now = Carbon::now(),
            'updated_at' => $now,
        ]);
    }

    /**
     * Delete a feature flag's value.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     */
    public function delete($feature, $scope): void
    {
        $this->newQuery()
            ->where('name', $feature)
            ->where('scope', Feature::serializeScope($scope))
            ->delete();
    }

    /**
     * Purge the given feature from storage.
     *
     * @param  array|null  $features
     */
    public function purge($features): void
    {
        if ($features === null) {
            $this->newQuery()->delete();
        } else {
            $this->newQuery()
                ->whereIn('name', $features)
                ->delete();
        }
    }

    /**
     * Create a new table query.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function newQuery()
    {
        return $this->db->table($this->config['table'] ?? 'features');
    }
}
