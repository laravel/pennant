<?php

namespace Laravel\Package\Http\Middleware;

use Laravel\Package\LaravelPackage;

class Authenticate
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response|null
     */
    public function handle($request, $next)
    {
        return LaravelPackage::check($request) ? $next($request) : abort(403);
    }
}
