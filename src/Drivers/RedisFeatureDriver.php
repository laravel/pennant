<?php

namespace Laravel\Pennant\Drivers;

use Laravel\Pennant\Contracts\Driver;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Collection;
use Laravel\Pennant\Events\UnknownFeatureResolved;
use Laravel\Pennant\Feature;
use stdClass;

class RedisFeatureDriver implements Driver
{

    /**
     * The sentinel value for unknown features.
     *
     * @var stdClass
     */
    protected stdClass $unknownFeatureValue;

    /**
     * The prefix for the feature flags.
     * @var string
     */
    private string $prefix = 'feature';

    /**
     * Create a new driver instance.
     * @param RedisManager $redis
     * @param Dispatcher $events
     * @param array<string, (callable(mixed $scope): mixed)> $featureStateResolvers
     */
    public function __construct(private readonly RedisManager $redis, private readonly Dispatcher $events, protected array $featureStateResolvers)
    {
        $this->unknownFeatureValue = new stdClass;
    }


    /**
     * Define an initial feature flag state resolver.
     * @param string $feature
     * @param (callable(mixed $scope): mixed) $resolver
     * @return void
     */
    public function define(string $feature, callable $resolver): void
    {

        $this->featureStateResolvers[$feature] = $resolver;
    }


    /**
     * Define the names of all defined features.
     * @return array<string>
     */
    public function defined(): array
    {

        return array_keys($this->featureStateResolvers);
    }

    /**
     * Get multiple feature flag values.
     *
     * @param array<string, array<int, mixed>> $features
     * @return array<string, array<int, mixed>>
     */
    public function getAll(array $features): array
    {

        return Collection::make($features)
            ->map(fn($scopes, $feature) => Collection::make($scopes)
                ->map(fn($scope) => $this->get($feature, $scope))
                ->all())
            ->all();

    }


    /**
     * Retrieve a feature flag's value.
     * @param string $feature
     * @param mixed $scope
     * @return mixed
     */
    public function get(string $feature, mixed $scope): mixed
    {

        $scopeKey = Feature::serializeScope($scope);

        $result = $this->redis->command('HGET', ["$this->prefix:$feature", $scopeKey]);
        if ($result) {
            return $result;
        }


        return
            with($this->resolveValue($feature, $scope), function ($value) use ($feature, $scopeKey) {
                if ($value === $this->unknownFeatureValue) {
                    return false;
                }

                $this->set($feature, $scopeKey, $value);

                return $value;
            });
    }

    /**
     * Determine the initial value for a given feature and scope.
     * @param $feature
     * @param $scope
     * @return mixed
     */
    protected function resolveValue($feature, $scope)
    {

        if (!array_key_exists($feature, $this->featureStateResolvers)) {
            $this->events->dispatch(new UnknownFeatureResolved($feature, $scope));

            return $this->unknownFeatureValue;
        }

        return $this->featureStateResolvers[$feature]($scope);
    }


    /**
     * Set a feature flag's value.
     * @param string $feature
     * @param mixed $scope
     * @param mixed $value
     * @return void
     */
    public function set(string $feature, mixed $scope, mixed $value): void
    {
        $this->redis->command('HSET', ["$this->prefix:$feature", Feature::serializeScope($scope), $value]);
    }


    /**
     * Set a feature flag's value for all scopes.
     * @param string $feature
     * @param mixed $value
     * @return void
     */
    public function setForAllScopes(string $feature, mixed $value): void
    {
        $this->redis->command('HMSET', ["$this->prefix:$feature", $value]);
    }


    /**
     * Delete a feature flag's value.
     * @param string $feature
     * @param mixed $scope
     * @return void
     */
    public function delete(string $feature, mixed $scope): void
    {
        $this->redis->command('HDEL', ["$this->prefix:$feature", Feature::serializeScope($scope)]);
    }


    /**
     * Purge the given feature from storage.
     * @param array|null $features
     * @return void
     */
    public function purge(?array $features): void
    {

        if ($features === null) {
            $ds = $this->redis->command('KEYS', ["$this->prefix:*"]);
            $ds = array_map(fn($d) => explode($this->prefix, $d)[1], $ds);

            $ds = array_map(fn($d) => $this->prefix . $d, $ds);

            foreach ($ds as $d) {
                $this->redis->command('DEL', [$d]);
            }

        } else {
            foreach ($features as $feature) {
                $this->redis->command('DEL', ["$this->prefix:$feature"]);
            }
        }
    }
}
