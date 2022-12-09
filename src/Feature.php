<?php

namespace Laravel\Feature;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Laravel\Feature\Drivers\ArrayDriver createArrayDriver()
 * @method static \Laravel\Feature\Drivers\DatabaseDriver createDatabaseDriver()
 * @method static string getDefaultDriver()
 * @method static \Laravel\Feature\FeatureManager compareScopeUsing(callable(mixed, mixed, string): bool $callback)
 * @method static mixed driver(string|null $driver = null)
 * @method static \Laravel\Feature\FeatureManager extend(string $driver, \Closure $callback)
 * @method static array getDrivers()
 * @method static \Illuminate\Contracts\Container\Container getContainer()
 * @method static \Laravel\Feature\FeatureManager setContainer(\Illuminate\Contracts\Container\Container $container)
 * @method static \Laravel\Feature\FeatureManager forgetDrivers()
 * @method static void register(string $feature, mixed $resolver)
 * @method static array<string, array<int, mixed>> load(array<string, array<int, mixed>> $features)
 * @method static array<string, array<int, mixed>> loadMissing(array<string, array<int, mixed>> $features)
 * @method static \Laravel\Feature\PendingScopedFeatureInteraction for(mixed|array<mixed> $scope)
 * @method static \Laravel\Feature\PendingScopedFeatureInteraction forTheAuthenticatedUser()
 * @method static mixed value(string $feature)
 * @method static array<string, array<mixed>> values(string|array<string> $feature)
 * @method static bool isActive(string|array<string> $feature)
 * @method static bool isInactive(string|array<string> $feature)
 * @method static void activate(string|array<string> $feature, mixed $value = true)
 * @method static void deactivate(string|array<string> $feature)
 *
 * @see \Laravel\Feature\FeatureManager
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
