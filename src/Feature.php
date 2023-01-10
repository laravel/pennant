<?php

namespace Laravel\Pennant;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Laravel\Pennant\Drivers\ArrayDriver createArrayDriver()
 * @method static \Laravel\Pennant\Drivers\DatabaseDriver createDatabaseDriver()
 * @method static void flushCache()
 * @method static void resolveScopeUsing(callable $resolver)
 * @method static string getDefaultDriver()
 * @method static mixed driver(string|null $driver = null)
 * @method static \Laravel\Pennant\FeatureManager extend(string $driver, \Closure $callback)
 * @method static array getDrivers()
 * @method static \Illuminate\Contracts\Container\Container getContainer()
 * @method static \Laravel\Pennant\FeatureManager setContainer(\Illuminate\Contracts\Container\Container $container)
 * @method static \Laravel\Pennant\FeatureManager forgetDrivers()
 * @method static void register(string $feature, mixed $resolver = null)
 * @method static array registered()
 * @method static void purge(string|null $feature = null)
 * @method static array load(string|array $features)
 * @method static array loadMissing(string|array $features)
 * @method static \Laravel\Pennant\Contracts\Driver getDriver()
 * @method static \Laravel\Pennant\PendingScopedFeatureInteraction for(mixed $scope)
 * @method static mixed value(string $feature)
 * @method static array values(array $features)
 * @method static array all()
 * @method static bool isActive(string $feature)
 * @method static bool allAreActive(array $features)
 * @method static bool someAreActive(array $features)
 * @method static bool isInactive(string $feature)
 * @method static bool allAreInactive(array $features)
 * @method static bool anyAreInactive(array $features)
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
}
