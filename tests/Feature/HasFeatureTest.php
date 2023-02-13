<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
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
}
