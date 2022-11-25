<?php

namespace Laravel\Feature;

use Illuminate\Support\Collection;
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
     * @var \Illuminate\Support\Collection<int, mixed>
     */
    protected $scope;

    /**
     * Create a new Pending Scoped Feature Interaction instance.
     *
     * @param  \Laravel\Feature\Drivers\ArrayDriver  $driver
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @param  \Illuminate\Support\Collection<int, mixed>  $scope
     */
    public function __construct($driver, $auth, $scope = new Collection)
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
        $this->scope = $this->scope->merge(Collection::wrap($scope));

        return $this;
    }

    /**
     * Scope the feature to check the global state.
     *
     * @return $this
     */
    public function globally()
    {
        $this->scope = $this->scope->merge([null]);

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

        $this->scope = $this->scope->merge([$this->auth->guard()->user()]);

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
        $this->driver->activate($feature, $this->scope);
    }

    /**
     * Deactivate the feature(s).
     *
     * @param  string|array<int, string>  $feature
     * @return void
     */
    public function deactivate($feature)
    {
        $this->driver->deactivate($feature, $this->scope);
    }
}
