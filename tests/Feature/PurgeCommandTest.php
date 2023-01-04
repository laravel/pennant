<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Laravel\Feature\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;

class PurgeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_purge_flags()
    {
        Feature::register('foo', true);
        Feature::register('bar', false);

        Feature::for('tim')->isActive('foo');
        Feature::for('taylor')->isActive('foo');
        Feature::for('taylor')->isActive('bar');

        $this->assertSame(3, DB::table('features')->count());

        $this->artisan('pennant:purge foo');

        $this->assertSame(1, DB::table('features')->count());

        $this->artisan('pennant:purge bar');

        $this->assertSame(0, DB::table('features')->count());
    }

    public function test_it_can_purge_all_feature_flags()
    {
        Feature::register('foo', true);
        Feature::register('bar', false);

        Feature::for('tim')->isActive('foo');
        Feature::for('taylor')->isActive('foo');
        Feature::for('taylor')->isActive('bar');

        $this->assertSame(3, DB::table('features')->count());

        $this->artisan('pennant:purge');

        $this->assertSame(0, DB::table('features')->count());
    }

    public function test_it_can_specify_a_driver()
    {
        Feature::extend('custom', fn () => new class {
            public function purge() {
                //
            }
        });

        Feature::driver('database')->register('foo', true);
        Feature::driver('database')->register('bar', false);


        Feature::for('tim')->isActive('foo');
        Feature::for('taylor')->isActive('foo');
        Feature::for('taylor')->isActive('bar');

        $this->assertSame(3, DB::table('features')->count());

        $this->artisan('pennant:purge --driver=custom');

        $this->assertSame(3, DB::table('features')->count());

        $this->artisan('pennant:purge --driver=database');

        $this->assertSame(0, DB::table('features')->count());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver [foo] not supported.');
        $this->artisan('pennant:purge --driver=foo');
    }
}
