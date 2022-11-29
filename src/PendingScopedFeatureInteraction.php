<?php

namespace Laravel\Feature;

use Illuminate\Support\Arr;
use RuntimeException;

class PendingScopedFeatureInteraction
{
    /**
     * The feature driver.
     *
     * @var \Laravel\Feature\Drivers\ArrayDriver
     */
    protected $driver;

    /**
     * The authenticate factory.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * The feature interaction scope.
     *
     * @var array<mixed>
     */
    protected $scope = [];

    /**
     * Create a new Pending Scoped Feature Interaction instance.
     *
     * @param  \Laravel\Feature\Drivers\ArrayDriver  $driver
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     */
    public function __construct($driver, $auth)
    {
        $this->driver = $driver;

        $this->auth = $auth;
    }

    /**
     * Scope the feature interaction.
     *
     * @param  mixed|array<mixed>  $scope
     * @return $this
     */
    public function for($scope)
    {
        $this->scope = array_merge($this->scope, Arr::wrap($scope));

        return $this;
    }

    /**
     * Scope the feature interaction to the authenticated user.
     *
     * @return $this
     */
    public function forTheAuthenticatedUser()
    {
        if (! $this->auth->guard()->check()) {
            throw new RuntimeException('There is no user currently authenticated.');
        }

        return $this->for($this->auth->guard()->user());
    }

    /**
     * Determine if the feature is active.
     *
     * @param  string|array<string>  $feature
     * @return bool
     */
    public function isActive($feature)
    {
        return $this->driver->isActive(Arr::wrap($feature), $this->scope);
    }

    /**
     * Determine if the feature is inactive.
     *
     * @param  string  $feature
     * @return bool
     */
    public function isInactive($feature)
    {
        return $this->driver->isInactive(Arr::wrap($feature), $this->scope);
    }

    /**
     * Activate the feature.
     *
     * @param  string|array<string>  $feature
     * @return void
     */
    public function activate($feature)
    {
        $this->driver->activate(Arr::wrap($feature), $this->scope);
    }

    /**
     * Deactivate the feature.
     *
     * @param  string|array<string>  $feature
     * @return void
     */
    public function deactivate($feature)
    {
        $this->driver->deactivate(Arr::wrap($feature), $this->scope);
    }
}
