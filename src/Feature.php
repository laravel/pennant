<?php

namespace Laravel\Feature;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Laravel\Feature\Drivers\ArrayDriver register(string $feature, (callable(mixed $scope, mixed ...$additional): bool) $resolver)
 * @method static \Laravel\Feature\Drivers\ArrayDriver toBaseDriver()
 * @method static \Laravel\Feature\PendingScopedFeatureInteraction for(mixed $scope)
 * @method static \Laravel\Feature\PendingScopedFeatureInteraction forTheAuthenticatedUser()
 * @method static \Laravel\Feature\PendingScopedFeatureInteraction globally()
 * @method static bool activate(string|array<int, string> $feature)
 * @method static bool deactivate(string|array<int, string> $feature)
 * @method static bool isActive(string|array<int, string> $feature)
 * @method static bool isInactive(string|array<int, string> $feature)
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
