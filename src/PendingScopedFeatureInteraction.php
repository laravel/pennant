<?php

namespace Laravel\Feature;

use Illuminate\Support\Collection;

class PendingScopedFeatureInteraction
{
    /**
     * The feature driver.
     *
     * @var \Laravel\Feature\Drivers\ArrayDriver
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
     * @param \Laravel\Feature\Drivers\ArrayDriver  $driver
     */
    public function __construct($driver)
    {
        $this->driver = $driver;
    }

    /**
     * Add scope to the feature interaction.
     *
     * @param  mixed  $scope
     * @return $this
     */
    public function for($scope)
    {
        $this->scope = array_merge($this->scope, Collection::wrap($scope)->all());

        return $this;
    }

    /**
     * Determine if the feature(s) is active.
     *
     * @param  string|array<int, string>  $feature
     * @return bool
     */
    public function isActive($feature)
    {
        return $this->driver->isActive($feature, $this->scope);
    }

    /**
     * Determine if the feature(s) is inactive.
     *
     * @param  string|array<int, string>  $feature
     * @return bool
     */
    public function isInactive($feature)
    {
        return $this->driver->isInactive($feature, $this->scope);
    }

    /**
     * Activate the feature(s).
     *
     * @param  string|array<int, string>  $feature
     * @return void
     */
    public function activate($feature)
    {
        return $this->driver->activate($feature, $this->scope);
    }

    /**
     * Deactivate the feature(s).
     *
     * @param  string|array<int, string>  $feature
     * @return void
     */
    public function deactivate($feature)
    {
        return $this->driver->deactivate($feature, $this->scope);
    }
}
