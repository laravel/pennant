<?php

namespace Laravel\Pennant\Drivers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Pennant\Contracts\Driver;
use Laravel\Pennant\Events\RetrievingKnownFeature;
use Laravel\Pennant\Events\RetrievingUnknownFeature;
use RuntimeException;
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
     * @param  \Illuminate\Database\Connection  $db
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @param  array<{connection?: string|null, table?: string|null}  $config
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
     * Retrieve a feature flag's value.
     *
     * @param  string  $feature
     * @param  mixed  $scope
     */
    public function get($feature, $scope): mixed
    {
        if (($record = $this->retrieve($feature, $scope)) !== null) {
            return json_decode($record->value, flags:  JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR);
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
            ->where('scope', $this->serializeScope($scope))
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
            $this->events->dispatch(new RetrievingUnknownFeature($feature, $scope));

            return $this->unknownFeatureValue;
        }

        return tap($this->featureStateResolvers[$feature]($scope), function ($value) use ($feature, $scope) {
            $this->events->dispatch(new RetrievingKnownFeature($feature, $scope, $value));
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
            ->where('scope', $serialized = $this->serializeScope($scope))
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
            'scope' => $this->serializeScope($scope),
            'value' => json_encode($value, flags: JSON_THROW_ON_ERROR),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
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
            ->where('scope', $this->serializeScope($scope))
            ->delete();
    }

    /**
     * Purge the given feature from storage.
     *
     * @param  string|null  $feature
     */
    public function purge($feature): void
    {
        if ($feature === null) {
            $this->newQuery()->delete();
        } else {
            $this->newQuery()
                ->where('name', $feature)
                ->delete();
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
        $query = $this->newQuery();

        $features = Collection::make($features)
            ->map(fn ($scopes, $feature) => Collection::make($scopes)
                ->each(fn ($scope) => $query->orWhere(
                    fn ($q) => $q->where('name', $feature)->where('scope', $this->serializeScope($scope))
                )));

        $records = $query->get();

        $inserts = new Collection;

        $results = $features->map(fn ($scopes, $feature) => $scopes->map(function ($scope) use ($feature, $records, $inserts) {
            $filtered = $records->where('name', $feature)->where('scope', $this->serializeScope($scope));

            if ($filtered->isNotEmpty()) {
                return json_decode($filtered->value('value'), flags:  JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR);
            }

            return tap($this->resolveValue($feature, $scope), function ($value) use ($feature, $scope, $inserts) {
                $inserts[] = [
                    'name' => $feature,
                    'scope' => $this->serializeScope($scope),
                    'value' => json_encode($value, flags: JSON_THROW_ON_ERROR),
                ];
            });
        })->all())->all();

        if ($inserts->isNotEmpty()) {
            $this->newQuery()->insert($inserts->all());
        }

        return $results;
    }

    /**
     * Serialize the given scope for storage.
     *
     * @param  mixed  $scope
     * @return string|null
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
