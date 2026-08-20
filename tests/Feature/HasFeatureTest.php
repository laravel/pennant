<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Laravel\Pennant\Attributes\Name;
use Laravel\Pennant\Attributes\Store;
use Laravel\Pennant\Concerns\HasFeatures;
use Laravel\Pennant\Contracts\FeatureScopeable;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class HasFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pennant.default', 'array');
    }

    public function test_it_can_check_for_active_features()
    {
        Feature::define('foo', 'foo-value');
        $class = new class implements FeatureScopeable
        {
            use HasFeatures;

            public function toFeatureIdentifier(string $driver): mixed
            {
                return 'scope';
            }
        };

        $value = $class->features()->value('foo');

        $this->assertSame('foo-value', $value);
    }

    public function test_it_routes_features_to_their_declared_store()
    {
        Config::set('pennant.stores.secondary', ['driver' => 'array']);

        $class = new class implements FeatureScopeable
        {
            use HasFeatures;

            public function toFeatureIdentifier(string $driver): mixed
            {
                return 'scope';
            }
        };

        $class->features()->activate(TraitFeatureWithStore::class, 'via-trait');

        $this->assertSame('via-trait', $class->features('secondary')->value(TraitFeatureWithStore::class));
        $this->assertSame([], Feature::store('array')->stored());
    }
}

#[Name('trait-feature-with-store')]
#[Store('secondary')]
class TraitFeatureWithStore
{
    public function resolve(mixed $scope): mixed
    {
        return 'resolved';
    }
}
