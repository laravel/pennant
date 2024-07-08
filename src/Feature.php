<?php

namespace Laravel\Pennant;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Laravel\Pennant\Drivers\Decorator store(string|null $store = null)
 * @method static \Laravel\Pennant\Drivers\Decorator driver(string|null $name = null)
 * @method static \Laravel\Pennant\Drivers\ArrayDriver createArrayDriver()
 * @method static \Laravel\Pennant\Drivers\DatabaseDriver createDatabaseDriver(array $config, string $name)
 * @method static string|null serializeScope(mixed $scope)
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
 * @method static void define(string $feature, mixed $resolver = null)
 * @method static array defined()
 * @method static array stored()
 * @method static void activateForEveryone(string|array $feature, mixed $value = true)
 * @method static void deactivateForEveryone(string|array $feature)
 * @method static void purge(string|array|null $features = null)
 * @method static string name(string $feature)
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
}
