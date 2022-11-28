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
     * @var array<int, mixed>
     */
    protected $scope;

    /**
     * Create a new Pending Scoped Feature Interaction instance.
     *
     * @param  \Laravel\Feature\Drivers\ArrayDriver  $driver
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @param  array<int, mixed>  $scope
     */
    public function __construct($driver, $auth, $scope)
    {
        $this->driver = $driver;

        $this->auth = $auth;

        $this->scope = $scope;
    }

    /**
     * Add scope to the feature interaction.
     *
     * @param  mixed  $scope
     * @return $this
     */
    public function for($scope)
    {
        $this->scope = array_merge($this->scope, Arr::wrap($scope));

        return $this;
    }

    /**
     * Add scope to the feature interaction.
     *
     * @param  mixed  $scope
     * @return $this
     */
    public function andFor($scope)
    {
        return $this->for($scope);
    }

    /**
     * Scope the feature to check the global state.
     *
     * TODO: `null` doesn't feel like a good identifier here.
     *
     * @return $this
     */
    public function globally()
    {
        return $this->for(null);
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
     * Determine if the feature(s) is active.
     *
     * @param  string|array<int, string>  $feature
     * @return bool
     */
    public function isActive($feature)
    {
        return $this->driver->isActive(Arr::wrap($feature), $this->scope);
    }

    /**
     * Determine if the feature(s) is inactive.
     *
     * @param  string|array<int, string>  $feature
     * @return bool
     */
    public function isInactive($feature)
    {
        return $this->driver->isInactive(Arr::wrap($feature), $this->scope);
    }

    /**
     * Activate the feature(s).
     *
     * @param  string|array<int, string>  $feature
     * @return void
     */
    public function activate($feature)
    {
        $this->driver->activate(Arr::wrap($feature), $this->scope);
    }

    /**
     * Deactivate the feature(s).
     *
     * @param  string|array<int, string>  $feature
     * @return void
     */
    public function deactivate($feature)
    {
        $this->driver->deactivate(Arr::wrap($feature), $this->scope);
    }
}
