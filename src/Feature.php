<?php

namespace Laravel\Feature;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Laravel\Feature\DriverDecorator driver(?string $driver = null)
 * @method static \Laravel\Feature\Drivers\ArrayDriver register(string $feature, (callable(mixed $scope): mixed) $resolver)
 * @method static \Laravel\Feature\Drivers\ArrayDriver toBaseDriver()
 * @method static \Laravel\Feature\PendingScopedFeatureInteraction for(mixed $scope)
 * @method static \Laravel\Feature\PendingScopedFeatureInteraction forTheAuthenticatedUser()
 * @method static bool activate(string|array<int, string> $feature)
 * @method static bool deactivate(string|array<int, string> $feature)
 * @method static bool isActive(string|array<int, string> $feature)
 * @method static bool isInactive(string|array<int, string> $feature)
 * @method static void load(string|array<int|string, string|array<int, mixed>> $features)
 * @method static void loadMissing(string|array<int|string, string|array<int, mixed>> $features)
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
