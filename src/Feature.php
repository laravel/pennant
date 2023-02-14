<?php

namespace Laravel\Pennant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use RuntimeException;

/**
 * @method static mixed store(string|null $store = null)
 * @method static \Laravel\Pennant\Drivers\ArrayDriver createArrayDriver()
 * @method static \Laravel\Pennant\Drivers\DatabaseDriver createDatabaseDriver()
 * @method static void flushCache()
 * @method static void resolveScopeUsing(callable $resolver)
 * @method static string getDefaultDriver()
 * @method static \Laravel\Pennant\FeatureManager setContainer(\Illuminate\Contracts\Container\Container $container)
 * @method static mixed driver(string|null $driver = null)
 * @method static \Laravel\Pennant\FeatureManager extend(string $driver, \Closure $callback)
 * @method static array getDrivers()
 * @method static \Illuminate\Contracts\Container\Container getContainer()
 * @method static \Laravel\Pennant\FeatureManager forgetDrivers()
 * @method static void discover(string $namespace = '\\App\\Features', string|null $path = null)
 * @method static void define(string $feature, mixed $resolver = null)
 * @method static array defined()
 * @method static void activateForEveryone(string|array $feature, mixed $value = true)
 * @method static void deactivateForEveryone(string|array $feature)
 * @method static void purge(string|array|null $features = null)
 * @method static \Laravel\Pennant\Contracts\Driver getDriver()
 * @method static void macro(string $name, object|callable $macro)
 * @method static void mixin(object $mixin, bool $replace = true)
 * @method static bool hasMacro(string $name)
 * @method static void flushMacros()
 * @method static mixed macroCall(string $method, array $parameters)
 * @method static \Laravel\Pennant\PendingScopedFeatureInteraction for(mixed $scope)
 * @method static array load(string|array $features)
 * @method static array loadMissing(string|array $features)
 * @method static mixed value(string $feature)
 * @method static array values(array $features)
 * @method static array all()
 * @method static bool active(string $feature)
 * @method static bool allAreActive(array $features)
 * @method static bool someAreActive(array $features)
 * @method static bool inactive(string $feature)
 * @method static bool allAreInactive(array $features)
 * @method static bool someAreInactive(array $features)
 * @method static mixed when(string $feature, \Closure $whenActive, \Closure|null $whenInactive = null)
 * @method static mixed unless(string $feature, \Closure $whenInactive, \Closure|null $whenActive = null)
 * @method static void activate(string|array $feature, mixed $value = true)
 * @method static void deactivate(string|array $feature)
 * @method static void forget(string|array $features)
 *
 * @see \Laravel\Pennant\FeatureManager
 */
class Feature extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return FeatureManager::class;
    }

    /**
     * Serialize the given scope for storage.
     *
     * @param  mixed  $scope
     * @return string|null
     */
    public static function serializeScope($scope)
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
