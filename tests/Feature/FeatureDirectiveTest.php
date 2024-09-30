<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class FeatureDirectiveTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pennant.default', 'database');
    }

    public function test_it_renders_active_features()
    {
        $blade = <<< 'BLADE'
            @feature("foo")
                foo is active
            @else
                foo is inactive
            @endfeature
            BLADE;

        $output = trim(Blade::render($blade));
        $this->assertSame('foo is inactive', $output);

        Feature::activate('foo');
        $output = trim(Blade::render($blade));
        $this->assertSame('foo is active', $output);
    }

    public function test_it_checks_feature_value()
    {
        $blade = <<< 'BLADE'
            @feature("foo", 888)
                foo is 888
            @else
                foo is not 888
            @endfeature
            BLADE;

        Feature::activate('foo', 999);
        $output = trim(Blade::render($blade));
        $this->assertSame('foo is not 888', $output);

        Feature::activate('foo', 888);
        $output = trim(Blade::render($blade));
        $this->assertSame('foo is 888', $output);
    }

    public function test_it_checks_features_against_default_scope_by_default()
    {
        $blade = <<< 'BLADE'
            @feature("foo", 888)
                foo is 888
            @else
                foo is not 888
            @endfeature
            BLADE;

        Feature::resolveScopeUsing(fn () => 'tim');

        Feature::for('tim')->activate('foo', 999);
        $output = trim(Blade::render($blade));
        $this->assertSame('foo is not 888', $output);

        Feature::for('tim')->activate('foo', 888);
        $output = trim(Blade::render($blade));
        $this->assertSame('foo is 888', $output);
    }

    public function test_it_renders_any_active_features()
    {
        $blade = <<<'BLADE'
        @featureany(['foo', 'bar'])
            foo or bar is active
        @else
            foo and bar are inactive
        @endfeatureany
        BLADE;

        $output = trim(Blade::render($blade));
        $this->assertSame('foo and bar are inactive', $output);

        Feature::activate('foo');
        $output = trim(Blade::render($blade));
        $this->assertSame('foo or bar is active', $output);

        Feature::deactivate('foo');
        Feature::activate('bar');
        $output = trim(Blade::render($blade));
        $this->assertSame('foo or bar is active', $output);
    }
}
