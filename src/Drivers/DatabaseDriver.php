<?php

namespace Laravel\Pennant\Drivers;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Pennant\Contracts\CanListStoredFeatures;
use Laravel\Pennant\Contracts\Driver;
use Laravel\Pennant\Events\UnknownFeatureResolved;
use Laravel\Pennant\Feature;
use RuntimeException;
use stdClass;

class DatabaseDriver implements CanListStoredFeatures, Driver
{
    /**
     * The database connection.
     *
     * @var \Illuminate\Database\DatabaseManager
     */
    protected $db;

    /**
     * The user configuration.
     *
     * @var \Illuminate\Config\Repository
     */
    protected $config;

    /**
     * The store's name.
     *
     * @var string
     */
    protected $name;

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
     * The current retry depth for retrieving values from the database.
     *
     * @var int
     */
    protected $retryDepth = 0;

    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'updated_at';

    /**
     * Create a new driver instance.
     *
     * @param  array<string, (callable(mixed $scope): mixed)>  $featureStateResolvers
     * @return void
     */
    public function __construct(DatabaseManager $db, Dispatcher $events, Repository $config, string $name, $featureStateResolvers)
    {
        $this->db = $db;
        $this->events = $events;
        $this->config = $config;
        $this->name = $name;
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
     * Retrieve the names of all stored features.
     *
     * @return array<string>
     */
    public function stored(): array
    {
        return $this->newQuery()
            ->select('name')
            ->distinct()
            ->get()
            ->pluck('name')
            ->all();
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

        $resolved = Collection::make($features)
            ->map(fn ($scopes, $feature) => Collection::make($scopes)
                ->each(fn ($scope) => $query->orWhere(
                    fn ($q) => $q->where('name', $feature)->where('scope', Feature::serializeScope($scope))
                )));

        $records = $query->get();

        $inserts = new Collection;

        $results = $resolved->map(fn ($scopes, $feature) => $scopes->map(function ($scope) use ($feature, $records, $inserts) {
            $filtered = $records->where('name', $feature)->where('scope', Feature::serializeScope($scope));

            if ($filtered->isNotEmpty()) {
                return $this->value($filtered->first());
            }

            return with($this->resolveValue($feature, $scope), function ($value) use ($feature, $scope, $inserts) {
                if ($value === $this->unknownFeatureValue) {
                    return false;
                }

                $inserts[] = [ // @phpstan-ignore offsetAssign.valueType
                    'name' => $feature,
                    'scope' => $scope,
                    'value' => $value,
                ];

                return $value;
            });
        })->all())->all();

        if ($inserts->isNotEmpty()) { // @phpstan-ignore method.impossibleType
            try {
                $this->insertMany($inserts->all());
            } catch (UniqueConstraintViolationException $e) {
                if ($this->retryDepth === 2) {
                    throw new RuntimeException('Unable to insert feature values into the database.', previous: $e);
                }

                $this->retryDepth++;

                return $this->getAll($features);
            } finally {
                $this->retryDepth = 0;
            }
        }

        return $results;
    }

    /**
     * Get the feature record's value
     * 
     * @param object $record
     */
    protected function value(object $record): mixed
    {
        $value = json_decode($record->value, flags: JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR);

        if (isset($record->active)) {
            return (bool) $record->active ? $value : false;
        }

        return $value;
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
            return $this->value($record);
        }

        return with($this->resolveValue($feature, $scope), function ($value) use ($feature, $scope) {
            if ($value === $this->unknownFeatureValue) {
                return false;
            }

            try {
                $this->insert($feature, $scope, $value);
            } catch (UniqueConstraintViolationException $e) {
                if ($this->retryDepth === 1) {
                    throw new RuntimeException('Unable to insert feature value into the database.', previous: $e);
                }

                $this->retryDepth++;

                return $this->get($feature, $scope);
            } finally {
                $this->retryDepth = 0;
            }

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
    public function set($feature, $scope, $value = null): void
    {
        if ($value) {
            $this->newQuery()->upsert([
                'name' => $feature,
                'scope' => Feature::serializeScope($scope),
                'active' => true,
                'value' => json_encode($value, flags: JSON_THROW_ON_ERROR),
                static::CREATED_AT => $now = Carbon::now(),
                static::UPDATED_AT => $now,
            ], uniqueBy: ['name', 'scope'], update: ['active', 'value', static::UPDATED_AT]);

            return;
        }

        if (is_null($value)) {
            $this->newQuery()->upsert([
                'name' => $feature,
                'scope' => Feature::serializeScope($scope),
                'active' => true,
                static::CREATED_AT => $now = Carbon::now(),
                static::UPDATED_AT => $now,
            ], uniqueBy: ['name', 'scope'], update: ['active', static::UPDATED_AT]);

            return;
        }

        $this->newQuery()->upsert([
            'name' => $feature,
            'scope' => Feature::serializeScope($scope),
            'active' => false,
            'value' => json_encode($value, flags: JSON_THROW_ON_ERROR),
            static::CREATED_AT => $now = Carbon::now(),
            static::UPDATED_AT => $now,
        ], uniqueBy: ['name', 'scope'], update: ['active', static::UPDATED_AT]);
    }

    /**
     * Set a feature flag's value for all scopes.
     *
     * @param  string  $feature
     * @param  mixed  $value
     */
    public function setForAllScopes($feature, $value): void
    {
        if ($value) {
            $this->newQuery()
                ->where('name', $feature)
                ->update([
                    'active' => true,
                    'value' => json_encode($value, flags: JSON_THROW_ON_ERROR),
                    static::UPDATED_AT => Carbon::now(),
                ]);

            return;
        }

        if (is_null($value)) {
            $this->newQuery()
                ->where('name', $feature)
                ->update([
                    'active' => true,
                    static::UPDATED_AT => Carbon::now(),
                ]);

            return;
        }

        $this->newQuery()
            ->where('name', $feature)
            ->update([
                'active' => false,
                static::UPDATED_AT => Carbon::now(),
            ]);
    }

    /**
     * Restore the value for the given feature and scope.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @param  mixed  $fallback
     * @return void
     */
    public function restore($feature, $scope, $fallback = true): void
    {
        if (($record = $this->retrieve($feature, $scope)) === null) {
            return;
        }

        $values = [
            'active' => true,
            static::UPDATED_AT => Carbon::now(),
        ];

        if (! json_decode($record->value, flags: JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR)) {
            $values['value'] = json_encode($fallback, flags: JSON_THROW_ON_ERROR);
        }

        $this->newQuery()
            ->where('name', $feature)
            ->where('scope', Feature::serializeScope($scope))
            ->update($values);
    }

    /**
     * Restore a feature flag's values for all scopes.
     *
     * @param  string  $feature
     * @param  mixed  $fallback
     * @return void
     */
    public function restoreForAllScopes($feature, $fallback = true): void
    {
        $this->connection()->transaction(function () use ($feature, $fallback) {
            $this->newQuery()
                ->where('name', $feature)
                ->update([
                    'active' => true,
                    static::UPDATED_AT => Carbon::now(),
                ]);

            $this->newQuery()
                ->where('name', $feature)
                ->where(function ($query) {
                    $query->where('value', json_encode(false, flags: JSON_THROW_ON_ERROR))
                        ->orWhereNull('value');
                })
                ->update([
                    'value' => json_encode($fallback, flags: JSON_THROW_ON_ERROR),
                    static::UPDATED_AT => Carbon::now(),
                ]);
        });
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
        return (bool) $this->newQuery()
            ->where('name', $feature)
            ->where('scope', Feature::serializeScope($scope))
            ->update($value ? [
                'active' => true,
                'value' => json_encode($value, flags: JSON_THROW_ON_ERROR),
                static::UPDATED_AT => Carbon::now(),
            ]:[
                'active' => false,
                static::UPDATED_AT => Carbon::now(),
            ]);
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
        return $this->insertMany([[
            'name' => $feature,
            'scope' => $scope,
            'value' => $value,
        ]]);
    }

    /**
     * Insert the given feature values into storage.
     *
     * @param  array<int, array{name: string, scope: mixed, value: mixed}>  $inserts
     * @return bool
     */
    protected function insertMany($inserts)
    {
        $now = Carbon::now();

        return $this->newQuery()->insert(array_map(fn ($insert) => [
            'name' => $insert['name'],
            'scope' => Feature::serializeScope($insert['scope']),
            'value' => json_encode($insert['value'], flags: JSON_THROW_ON_ERROR),
            'active' => $insert['value'] ? true : false,
            static::CREATED_AT => $now,
            static::UPDATED_AT => $now,
        ], $inserts));
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
        return $this->connection()->table(
            $this->config->get("pennant.stores.{$this->name}.table") ?? 'features'
        );
    }

    /**
     * The database connection.
     *
     * @return \Illuminate\Database\Connection
     */
    protected function connection()
    {
        return $this->db->connection(
            $this->config->get("pennant.stores.{$this->name}.connection") ?? null
        );
    }
}
