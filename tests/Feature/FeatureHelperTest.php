<?php

namespace Tests\Feature;

use BadFunctionCallException;
use BadMethodCallException;
use Illuminate\Support\Facades\Config;
use Laravel\Pennant\Feature;
use RuntimeException;
use Tests\TestCase;

class FeatureHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('features.default', 'array');
    }

    public function testItReturnsFeatureManager()
    {
        $this->assertNotNull(feature());
        $this->assertSame(Feature::getFacadeRoot(), feature());
    }

    public function testItReturnsTheFeatureValue()
    {
        Feature::activate('foo', 'bar');

        $this->assertSame('bar', feature('foo'));
    }

    public function testItConditionallyExecutesCodeBlocks()
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
