<?php

namespace Laravel\Pennant\Middleware;

use BackedEnum;
use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Drivers\Decorator;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PendingScopedFeatureInteraction;
use UnitEnum;

use function Laravel\Pennant\enum_value;

class EnsureFeaturesAreActive
{
    protected static ?Closure $respondUsing = null;

    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next, BackedEnum|UnitEnum|string ...$features): mixed
    {
        $features = array_map(enum_value(...), $features);

        $driver = $this->getScopedDriver($request);

        $driver->loadMissing($features);

        if ($driver->someAreInactive($features)) {
            $error = config('app.debug')
                ? 'Required features ['.implode(', ', $features).'] not enabled.'
                : '';

            return static::$respondUsing
                ? call_user_func(static::$respondUsing, $request, $features)
                : abort(400, $error);
        }

        return $next($request);
    }

    /**
     * Get the driver to use, with optional scope.
     */
    protected function getScopedDriver(Request $request): Decorator|PendingScopedFeatureInteraction
    {
        return Feature::driver();
    }

    /**
     * Specify the features for the middleware.
     */
    public static function using(BackedEnum|UnitEnum|string ...$features): string
    {
        return static::class.':'.implode(',', array_map(enum_value(...), $features));
    }

    /**
     * Specify a callback that should be used to generate responses for failed feature checks.
     */
    public static function whenInactive(?Closure $callback): void
    {
        static::$respondUsing = $callback;
    }
}
