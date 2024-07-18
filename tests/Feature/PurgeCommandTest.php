<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class PurgeCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

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

    public function test_it_can_purge_multiple_features()
    {
        Feature::define('foo', true);
        Feature::define('bar', true);
        Feature::define('baz', true);

        Feature::for('tim')->active('foo');
        Feature::for('tim')->active('bar');
        Feature::for('taylor')->active('bar');
        Feature::for('taylor')->active('baz');

        $this->assertSame(4, DB::table('features')->count());

        $this->artisan('pennant:purge foo bar')->expectsOutputToContain('foo, bar successfully purged from storage.');

        $this->assertSame(1, DB::table('features')->count());

        $this->artisan('pennant:purge baz');

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
        config(['pennant.stores.custom' => ['driver' => 'custom']]);

        Feature::extend('custom', fn () => new class
        {
            public function purge()
            {
                //
            }
        });

        Feature::store('database')->define('foo', true);
        Feature::store('database')->define('bar', false);

        Feature::for('tim')->active('foo');
        Feature::for('taylor')->active('foo');
        Feature::for('taylor')->active('bar');

        $this->assertSame(3, DB::table('features')->count());

        $this->artisan('pennant:purge --store=custom');

        $this->assertSame(3, DB::table('features')->count());

        $this->artisan('pennant:purge --store=database');

        $this->assertSame(0, DB::table('features')->count());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pennant store [foo] is not defined.');
        $this->artisan('pennant:purge --store=foo');
    }

    public function test_it_can_exclude_features_to_purge_from_storage()
    {
        Feature::define('foo', true);
        Feature::define('bar', false);

        Feature::for('tim')->active('foo');
        Feature::for('taylor')->active('foo');

        Feature::for('taylor')->active('bar');

        DB::table('features')->insert([
            'name' => 'baz',
            'scope' => 'Tim',
            'value' => true,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $this->assertCount(3, DB::table('features')->get()->unique('name'));

        $this->artisan('pennant:purge --except=foo')->expectsOutputToContain('bar, baz successfully purged from storage.');

        $this->assertCount(1, DB::table('features')->get()->unique('name'));

        $this->artisan('pennant:purge foo')->expectsOutputToContain('foo successfully purged from storage.');

        $this->assertSame(0, DB::table('features')->count());
    }

    public function test_it_outputs_that_no_features_have_been_purged()
    {
        Feature::define('foo', true);

        Feature::for('tim')->active('foo');
        Feature::for('taylor')->active('foo');

        $this->assertSame(2, DB::table('features')->where('name', 'foo')->count());

        $this->artisan('pennant:purge --except=foo')->expectsOutputToContain('No features to purge from storage.');

        $this->assertSame(2, DB::table('features')->where('name', 'foo')->count());
    }

    public function test_it_can_combine_except_and_features_as_arguments()
    {
        DB::table('features')->insert([
            'name' => 'foo',
            'scope' => 'Tim',
            'value' => true,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
        DB::table('features')->insert([
            'name' => 'bar',
            'scope' => 'Tim',
            'value' => true,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
        DB::table('features')->insert([
            'name' => 'baz',
            'scope' => 'Tim',
            'value' => true,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $this->artisan('pennant:purge foo bar --except=bar')->expectsOutputToContain('foo successfully purged from storage.');

        $this->assertSame(['bar', 'baz'], DB::table('features')->pluck('name')->all());
    }

    public function test_it_can_purge_features_except_those_registered()
    {
        Feature::define('foo', fn () => true);
        DB::table('features')->insert([
            'name' => 'foo',
            'scope' => 'Tim',
            'value' => true,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
        DB::table('features')->insert([
            'name' => 'bar',
            'scope' => 'Tim',
            'value' => true,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
        DB::table('features')->insert([
            'name' => 'baz',
            'scope' => 'Tim',
            'value' => true,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $this->artisan('pennant:purge --except-registered')->expectsOutputToContain('bar, baz successfully purged from storage.');

        $this->assertSame(['foo'], DB::table('features')->pluck('name')->all());
    }
}
