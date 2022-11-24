<?php

namespace Laravel\Feature;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Feature\Events\CheckingUnknownFeature;

class ArrayDriver
{
    /**
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $events;

    /**
     * @var array<string, bool>
     */
    protected $features = [];


    /**
     * @var array<string, callable>
     */
    protected $resolvers = [];

    /**
     * @param \Illuminate\Contracts\Events\Dispatcher $events
     */
    public function __construct($events)
    {
        $this->events = $events;
    }

    /**
     * @param  string  $feature
     * @param  mixed  $scope
     * @return bool
     */
    public function isActive($feature, $scope = null)
    {
        return $this->resolveKeys($feature, $scope)->every(function ($key) use ($feature, $scope) {
            $key = "{$feature}:{$key}";

            if (! array_key_exists($key, $this->features) && ! array_key_exists($feature, $this->resolvers)) {
                $this->events->dispatch(new CheckingUnknownFeature($feature, $scope));

                return false;
            }

            return $this->features[$key] ??= (bool) $this->resolvers[$feature]($scope);
        });
    }

    /**
     * @param  string  $feature
     * @param  mixed  $scope
     * @return bool
     */
    public function isInactive($feature, $scope = null)
    {
        return ! $this->isActive($feature, $scope);
    }

    /**
     * @param  string|array<string>  $feature
     * @param  mixed  $scope
     * @return void
     */
    public function activate($feature, $scope = null)
    {
        $this->features = array_merge(
            $this->features,
            Collection::wrap($feature)
                ->crossJoin($this->resolveKeys($feature, $scope))
                ->mapSpread(fn ($feature, $scope) => "{$feature}:{$scope}")
                ->mapWithKeys(fn ($value) => [
                    $value => true,
                ])
                ->all()
        );
    }

    /**
     * @param  string|array<string>  $feature
     * @param  mixed  $scope
     * @return void
     */
    public function deactivate($feature, $scope = null)
    {
        $this->features = array_merge(
            $this->features,
            Collection::wrap($feature)
                ->crossJoin($this->resolveKeys($feature, $scope))
                ->mapSpread(fn ($feature, $scope) => "{$feature}:{$scope}")
                ->mapWithKeys(fn ($value) => [
                    $value => false,
                ])
                ->all()
        );
    }

    /**
     * @param string $feature
     * @param callable $resolver
     */
    public function register($feature, $resolver)
    {
        $this->resolvers[$feature] = $resolver;

        return $this;
    }

    /**
     * @param  mixed  $scope
     */
    public function for($scope)
    {
        return (new PendingScopedFeatureEvaluation($this))->for($scope);
    }

    /**
     * @param string $feature
     * @param mixed $scope
     * @return \Illuminate\Support\Collection
     */
    protected function resolveKeys($feature, $scope)
    {
        $scope = is_array($scope) ? $scope : [$scope];

        return Collection::make($scope)->map(fn ($item) => $this->resolveKey($feature, $item));
    }

    /**
     * @param  string  $feature
     * @param  mixed  $scope
     * @return string
     */
    protected function resolveKey($feature, $scope)
    {
        if ($scope instanceof Model) {
            return $scope::class.':'.$scope->getKey();
        }

        return json_encode($scope);
    }
}
