<?php

namespace Laravel\Package;

use Closure;

class LaravelPackage
{
    /**
     * The callback that should be used to authenticate LaravelPackage users.
     *
     * @var \Closure
     */
    public static $authUsing;

    /**
     * Indicates if LaravelPackage migrations will be run.
     *
     * @var bool
     */
    public static $runsMigrations = true;

    /**
     * Determine if the given request can access the LaravelPackage dashboard.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public static function check($request)
    {
        return (static::$authUsing ?: function () {
            return app()->environment('local');
        })($request);
    }

    /**
     * Set the callback that should be used to authenticate LaravelPackage users.
     *
     * @param  \Closure  $callback
     * @return static
     */
    public static function auth(Closure $callback)
    {
        static::$authUsing = $callback;

        return new static;
    }

    /**
     * Configure LaravelPackage to not register its migrations.
     *
     * @return static
     */
    public static function ignoreMigrations()
    {
        static::$runsMigrations = false;

        return new static;
    }
}
