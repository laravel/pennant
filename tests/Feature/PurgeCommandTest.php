<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class PurgeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_purge_flags()
    {
        Feature::define('foo', true);
        Feature::define('bar', false);

        Feature::for('tim')->active('foo');
        Feature::for('taylor')->active('bar');
        Feature::for('taylor')->active('foo');

        $this->assertSame(3, DB::table('features')->count());

        $this->artisan('pennant:purge foo')->expectsOutputToContain('foo successfully purged from storage.');

        $this->assertSame(1, DB::table('features')->count());

        $this->artisan('pennant:purge bar');

        $this->assertSame(0, DB::table('features')->count());
    }

    public function test_it_can_purge_all_feature_flags()
    {
        Feature::define('foo', true);
        Feature::define('bar', false);

        Feature::for('tim')->active('foo');
        Feature::for('taylor')->active('foo');
        Feature::for('taylor')->active('bar');

        $this->assertSame(3, DB::table('features')->count());

        $this->artisan('pennant:purge')->expectsOutputToContain('All features successfully purged from storage.');

        $this->assertSame(0, DB::table('features')->count());
    }

    public function test_it_can_specify_a_driver()
    {
        Feature::extend('custom', fn () => new class
        {
            public function purge()
            {
                //
            }
        });

        Feature::driver('database')->define('foo', true);
        Feature::driver('database')->define('bar', false);

        Feature::for('tim')->active('foo');
        Feature::for('taylor')->active('foo');
        Feature::for('taylor')->active('bar');

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
