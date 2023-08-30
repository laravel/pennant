<?php

namespace Laravel\Pennant\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;

class EnsureFeaturesAreActive
{
    protected static ?Closure $respondUsing = null;

    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$features): mixed
    {
        Feature::loadMissing($features);

        if (Feature::someAreInactive($features)) {
            return static::$respondUsing
                ? call_user_func(static::$respondUsing, $request, $features)
                : abort(400);
        }

        return $next($request);
    }

    /**
     * Specify the features for the middleware.
     */
    public static function using(string ...$features): string
    {
        return static::class.':'.implode(',', $features);
    }

    /**
     * Specify a callback that should be used to generate responses for failed feature checks.
     */
    public static function whenInactive(?Closure $callback): void
    {
        static::$respondUsing = $callback;
    }
}
