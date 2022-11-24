<?php

namespace Laravel\Feature;

use Illuminate\Support\Traits\ForwardsCalls;

class PendingScopedFeatureEvaluation
{
    use ForwardsCalls;

    protected $driver;

    protected $scope;

    public function __construct($driver)
    {
        $this->driver = $driver;
    }

    public function for($scope)
    {
        $this->scope = $scope;

        return $this;
    }

    public function activate($feature)
    {
        return $this->driver->activate("{$feature}:{$this->driver->resolveScope($this->scope)}");
    }

    public function deactivate($feature)
    {
        return $this->driver->deactivate("{$feature}:{$this->driver->resolveScope($this->scope)}");
    }

    public function isActive($feature)
    {
        return $this->driver->isActive("{$feature}:{$this->driver->resolveScope($this->scope)}");
    }
}
