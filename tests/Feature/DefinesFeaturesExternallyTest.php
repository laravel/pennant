<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Laravel\Pennant\Contracts\DefinesFeaturesExternally;
use Laravel\Pennant\Contracts\FeatureScopeable;
use Laravel\Pennant\Drivers\ArrayDriver;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class DefinesFeaturesExternallyTest extends TestCase
{
    public function test_it_can_retrieve_all_features_when_features_are_defined_externally()
    {
        $driver = new class(app('events'), []) extends ArrayDriver implements DefinesFeaturesExternally
        {
            /**
             * Retrieve the defined features for the given scope.
             *
             * @return list<string>
             */
            public function definedFeaturesForScope(mixed $scope): array
            {
                return [
                    'feature-1',
                    'feature-2',
                ];
            }
        };
        Feature::extend('external', fn () => $driver);
        Config::set('pennant.stores.external', ['driver' => 'external']);

        $features = Feature::driver('external')->all();

        $this->assertSame([
            'feature-1' => false,
            'feature-2' => false,
        ], $features);
    }

    public function test_when_features_are_defined_externally_that_scope_is_correctly_resolved()
    {
        $driver = new class(app('events'), []) extends ArrayDriver implements DefinesFeaturesExternally
        {
            /**
             * Retrieve the defined features for the given scope.
             *
             * @return list<string>
             */
            public function definedFeaturesForScope(mixed $scope): array
            {
                return [
                    $scope,
                ];
            }
        };
        Feature::extend('external', fn () => $driver);
        Config::set('pennant.stores.external', ['driver' => 'external']);

        $features = Feature::driver('external')->for(new class implements FeatureScopeable
        {
            public function toFeatureIdentifier(string $driver): mixed
            {
                return 'scope-value';
            }
        })->all();

        $this->assertSame([
            'scope-value' => false,
        ], $features);
    }
}
