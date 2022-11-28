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
     * @var \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, mixed>>
     */
    protected $scope;

    /**
     * Create a new Pending Scoped Feature Interaction instance.
     *
     * @param  \Laravel\Feature\Drivers\ArrayDriver  $driver
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, mixed>>  $scope
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
     * @param  mixed  ...$additional
     * @return $this
     */
    public function for($scope, ...$additional)
    {
        $this->scope->push(Collection::make([$scope, ...$additional]));

        return $this;
    }

    /**
     * Add additional to the feature interaction. Aliases `$this->for()`.
     *
     * @param  mixed  $scope
     * @param  mixed  ...$additional
     * @return $this
     */
    public function andFor($scope, ...$additional)
    {
        return $this->for($scope, ...$additional);
    }

    /**
     * Scope the feature to check the global state.
     *
     * TODO: `null` doesn't feel like a good identifier here.
     *
     * @param  mixed  ...$additionalScope
     * @return $this
     */
    public function globally(...$additionalScope)
    {
        return $this->for(null, ...$additionalScope);
    }

    /**
     * Scope the feature interaction to the authenticated user.
     *
     * @param  mixed  ...$additionalScope
     * @return $this
     */
    public function forTheAuthenticatedUser(...$additionalScope)
    {
        if (! $this->auth->guard()->check()) {
            throw new RuntimeException('There is no user currently authenticated.');
        }

        return $this->for($this->auth->guard()->user(), ...$additionalScope);
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
