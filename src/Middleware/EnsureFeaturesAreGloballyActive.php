<?php

namespace Laravel\Pennant\Middleware;

use Illuminate\Http\Request;
use Laravel\Pennant\Drivers\Decorator;
use Laravel\Pennant\Feature;
use Laravel\Pennant\PendingScopedFeatureInteraction;
use Override;

class EnsureFeaturesAreGloballyActive extends EnsureFeaturesAreActive
{
    #[Override]
    protected function getScopedDriver(Request $request): Decorator|PendingScopedFeatureInteraction
    {
        return Feature::globally();
    }
}
