<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class FeatureHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pennant.default', 'array');
    }

    public function test_it_returns_feature_manager()
    {
        $this->assertNotNull(feature());
        $this->assertSame(Feature::getFacadeRoot(), feature());
    }

    public function test_it_returns_the_feature_value()
    {
        Feature::activate('foo', 'bar');

        $this->assertSame('bar', feature('foo'));
    }

    public function test_it_conditionally_executes_code_blocks()
    {
        Feature::activate('foo');
        $inactive = $active = null;

        feature('foo', function () use (&$active) {
            $active = true;
        }, function () use (&$inactive) {
            $inactive = true;
        });

        $this->assertTrue($active);
        $this->assertNull($inactive);

        Feature::deactivate('foo');
        $inactive = $active = null;

        feature('foo', function () use (&$active) {
            $active = true;
        }, function () use (&$inactive) {
            $inactive = true;
        });

        $this->assertNull($active);
        $this->assertTrue($inactive);
    }
}
