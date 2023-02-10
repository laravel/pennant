<?php

namespace Laravel\Pennant\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Pennant\Feature;

class FeatureMiddleware
{
    public function handle(Request $request, Closure $next, string ...$features): mixed
    {
        foreach ($features as $feature) {
            abort_if(
                ! Feature::active($feature),
                Response::HTTP_BAD_REQUEST
            );

            continue;
        }

        return $next($request);
    }
}
