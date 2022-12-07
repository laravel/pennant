<?php

namespace Laravel\Feature;

use Illuminate\Support\Collection;


/**
 * @mixin \Laravel\Feature\PendingScopedFeatureInteraction
 */
class DriverDecorator
{
    /**
     * The driver name.
     *
     * @var string
     */
    public $name;

    /**
     * The driver being decorated.
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
     * Feature state cache.
     *
     * @var array{ feature: string, scope: mixed, value: mixed }
     */
    protected $cache;

    /**
     * Create a new Driver Decorator instance.
     *
     * @param  string  $name
     * @param  \Laravel\Feature\Drivers\ArrayDriver  $driver
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @param  array{ feature: string, scope: mixed, value: mixed }  $cache
     */
    public function __construct($name, $driver, $auth, $cache = [])
    {
        $this->name = $name;

        $this->driver = $driver;

        $this->auth = $auth;

        $this->cache = $cache;
    }

    /**
     * Register an initial feature state resolver.
     *
     * @param  string  $feature
     * @param  (callable(mixed $scope): mixed)  $resolver
     * @return void
     */
    public function register($feature, $resolver)
    {
        $this->driver()->register($feature, $resolver);
    }

    /**
     * Eagerly load the feature state into memory.
     *
     * @param  string|array<string|int, array<int, mixed>|string>  $features
     * @return void
     */
    public function load($features)
    {
        // TODO map to toFeatureScopeIdentifier()
        $features = Collection::wrap($features)
            ->mapWithKeys(fn ($value, $key) => is_int($key)
                ? [$value => [null]]
                : [$key => ($value ?: [null])])
            ->all();

        $result = $this->driver()->load($features);

        Collection::make($features)
            ->flatMap(fn ($value, $key) => collect($value)
                ->zip($result[$key])
                ->map(fn ($value) => collect(['scope', 'value', 'feature'])->combine($value->push($key))))
            ->each(function ($value) {
                $position = Collection::make($this->cache)
                    ->search(fn ($v) => $v['feature'] === $value['feature'] && $v['scope'] === $value['scope']);

                if ($position === false) {
                    $this->cache[] = $value;
                } else {
                    $this->cache[$position] = $value;
                }
            });
    }

    /**
     * Get the value of the feature flag.
     *
     * @internal
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @return mixed
     */
    public function get($feature, $scope)
    {
        $item = Collection::make($this->cache)
            ->whereStrict('scope', $scope)
            ->whereStrict('feature', $feature)
            ->first();

        if ($item !== null) {
            return $item['value'];
        }

        return tap($this->driver()->isActive($feature, $scope), function ($value) use ($feature, $scope) {
            $this->cache[] = [
                'value' => $value,
                'scope' => $scope,
                'feature' => $feature,
            ];
        });
    }

    /**
     * Set the value of the feature flag.
     *
     * @internal
     *
     * @param  string  $feature
     * @param  mixed  $scope
     * @param  mixed  $value
     * @return void
     */
    public function set($feature, $scope, $value)
    {
        if ($value) {
            $this->driver->activate($feature, $scope);
        } else {
            $this->driver->deactivate($feature, $scope);
        }

        $position = Collection::make($this->cache)->search(fn ($v) => $v['feature'] === $feature && $v['scope'] === $scope);

        $value = ['feature' => $feature, 'scope' => $scope, 'value' => $value];

        if ($position === false) {
            $this->cache[] = $value;
        } else {
            $this->cache[$position] = $value;
        }
    }

    /**
     * Eagerly load the missing feature state into memory.
     *
     * @param  string|array<string|int, array<int, mixed>|string>  $features
     * @return void
     */
    public function loadMissing($features)
    {
        $this->driver()->loadMissing($features);
    }

    /**
     * Get the decorated driver.
     *
     * @return \Laravel\Feature\Drivers\ArrayDriver
     */
    public function driver()
    {
        return $this->driver;
    }

    /**
     * Get the Authentication factory.
     *
     * @return \Illuminate\Contracts\Auth\Factory
     */
    public function auth()
    {
        return $this->auth;
    }

    /**
     * Dynamically create a pending feature interaction.
     *
     * @param  string  $name
     * @param  array<mixed>  $parameters
     * @return \Laravel\Feature\PendingScopedFeatureInteraction
     */
    public function __call($name, $parameters)
    {
        return (new PendingScopedFeatureInteraction($this))->{$name}(...$parameters);
    }
}
