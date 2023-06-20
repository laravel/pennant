<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Laravel\Pennant\Contracts\FeatureScopeable;
use Laravel\Pennant\Events\AllFeaturesPurged;
use Laravel\Pennant\Events\DynamicallyRegisteringFeatureClass;
use Laravel\Pennant\Events\FeatureDeleted;
use Laravel\Pennant\Events\FeatureResolved;
use Laravel\Pennant\Events\FeaturesPurged;
use Laravel\Pennant\Events\FeatureUpdated;
use Laravel\Pennant\Events\FeatureUpdatedForAllScopes;
use Laravel\Pennant\Events\UnknownFeatureResolved;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class FeatureDirectiveTest extends TestCase
{
    use RefreshDatabase;

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
}
