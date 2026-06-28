<?php

namespace Laravel\Pennant;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Laravel\Pennant\Drivers\Decorator store(string|null $store = null)
 * @method static \Laravel\Pennant\Drivers\Decorator driver(string|null $name = null)
 * @method static \Laravel\Pennant\Drivers\ArrayDriver createArrayDriver()
 * @method static \Laravel\Pennant\Drivers\DatabaseDriver createDatabaseDriver(array $config, string $name)
 * @method static string serializeScope(mixed $scope)
 * @method static \Laravel\Pennant\FeatureManager useMorphMap(bool $value = true)
 * @method static void flushCache()
 * @method static void resolveScopeUsing(callable $resolver)
 * @method static string getDefaultDriver()
 * @method static void setDefaultDriver(string $name)
 * @method static \Laravel\Pennant\FeatureManager forgetDriver(array|string|null $name = null)
 * @method static \Laravel\Pennant\FeatureManager forgetDrivers()
 * @method static \Laravel\Pennant\FeatureManager extend(string $driver, \Closure $callback)
 * @method static \Laravel\Pennant\FeatureManager setContainer(\Illuminate\Container\Container $container)
 * @method static void discover(string $namespace = 'App\\Features', string|null $path = null)
 * @method static void define(\BackedEnum|\UnitEnum|string $feature, mixed $resolver = null)
 * @method static bool isResolverValidForScope(callable|string $resolver, mixed $scope)
 * @method static array defined()
 * @method static array stored()
 * @method static void activateForEveryone(\BackedEnum|\UnitEnum|string|array $feature, mixed $value = true)
 * @method static void deactivateForEveryone(\BackedEnum|\UnitEnum|string|array $feature)
 * @method static void purge(\BackedEnum|\UnitEnum|string|array|null $features = null)
 * @method static string name(\BackedEnum|\UnitEnum|string $feature)
 * @method static array nameMap()
 * @method static mixed instance(\BackedEnum|\UnitEnum|string $name)
 * @method static \Laravel\Pennant\Contracts\Driver getDriver()
 * @method static void macro(string $name, object|callable $macro)
 * @method static void mixin(object $mixin, bool $replace = true)
 * @method static bool hasMacro(string $name)
 * @method static void flushMacros()
 * @method static mixed macroCall(string $method, array $parameters)
 * @method static \Laravel\Pennant\PendingScopedFeatureInteraction for(mixed $scope)
 * @method static array load(\BackedEnum|\UnitEnum|string|array $features)
 * @method static array loadMissing(\BackedEnum|\UnitEnum|string|array $features)
 * @method static array loadAll()
 * @method static mixed value(\BackedEnum|\UnitEnum|string $feature)
 * @method static array values(array $features)
 * @method static array all()
 * @method static bool active(\BackedEnum|\UnitEnum|string $feature)
 * @method static bool allAreActive(array $features)
 * @method static bool someAreActive(array $features)
 * @method static bool inactive(\BackedEnum|\UnitEnum|string $feature)
 * @method static bool allAreInactive(array $features)
 * @method static bool someAreInactive(array $features)
 * @method static mixed when(\BackedEnum|\UnitEnum|string $feature, \Closure $whenActive, \Closure|null $whenInactive = null)
 * @method static mixed unless(\BackedEnum|\UnitEnum|string $feature, \Closure $whenInactive, \Closure|null $whenActive = null)
 * @method static void activate(\BackedEnum|\UnitEnum|string|array $feature, mixed $value = true)
 * @method static void deactivate(\BackedEnum|\UnitEnum|string|array $feature)
 * @method static void forget(\BackedEnum|\UnitEnum|string|array $features)
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
