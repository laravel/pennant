<?php

namespace Laravel\Feature;

use Illuminate\Support\Traits\ForwardsCalls;

class PendingScopedFeatureInteraction
{
    /**
     * The feature driver.
     *
     * @var TODO
     */
    protected $driver;

    /**
     * The feature interaction scope.
     *
     * @var array
     */
    protected $scope = [];

    /**
     * Create a new Pending Scoped Feature Interaction instance.
     *
     * @param  TODO  $driver
     */
    public function __construct($driver)
    {
        $this->driver = $driver;
    }

    /**
     * Add scope to the interaction.
     *
     * @param  mixed  $scope
     * @return $this
     */
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
