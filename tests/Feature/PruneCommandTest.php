<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Laravel\Feature\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;

class PruneCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_prune_flags()
    {
        Feature::register('foo', true);
        Feature::register('bar', false);

        Feature::for('tim')->isActive('foo');
        Feature::for('taylor')->isActive('foo');
        Feature::for('taylor')->isActive('bar');

        $this->assertSame(3, DB::table('features')->count());

        $this->artisan('pennant:prune foo');

        $this->assertSame(1, DB::table('features')->count());

        $this->artisan('pennant:prune bar');

        $this->assertSame(0, DB::table('features')->count());
    }

    public function test_it_can_prune_all_feature_flags()
    {
        Feature::register('foo', true);
        Feature::register('bar', false);

        Feature::for('tim')->isActive('foo');
        Feature::for('taylor')->isActive('foo');
        Feature::for('taylor')->isActive('bar');

        $this->assertSame(3, DB::table('features')->count());

        $this->artisan('pennant:prune');

        $this->assertSame(0, DB::table('features')->count());
    }

    public function test_it_can_specify_a_driver()
    {
        Feature::driver('database')->register('foo', true);
        Feature::driver('database')->register('bar', false);

        Feature::for('tim')->isActive('foo');
        Feature::for('taylor')->isActive('foo');
        Feature::for('taylor')->isActive('bar');

        $this->assertSame(3, DB::table('features')->count());

        $this->artisan('pennant:prune --driver=array');

        $this->assertSame(3, DB::table('features')->count());

        $this->artisan('pennant:prune --driver=database');

        $this->assertSame(0, DB::table('features')->count());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver [foo] not supported.');
        $this->artisan('pennant:prune --driver=foo');
    }
}
