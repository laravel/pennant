<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class PruneCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_can_prune_flags()
    {
        Feature::define('foo', true);

        Feature::driver('database')->set('foo', 'tim', true);
        Feature::driver('database')->set('foo', 'taylor', true);
        Feature::driver('database')->set('bar', 'taylor', true);

        $this->assertSame(3, DB::table('features')->count());

        $this->artisan('pennant:prune')->expectsOutputToContain('Features successfully pruned from storage.');

        $this->assertSame(2, DB::table('features')->count());
    }

    public function test_it_can_specify_a_driver()
    {
        config(['pennant.stores.custom' => ['driver' => 'custom']]);

        Feature::extend('custom', fn () => new class
        {
            public function prune()
            {
                //
            }
        });

        Feature::store('database')->define('foo', true);

        Feature::driver('database')->set('foo', 'tim', true);
        Feature::driver('database')->set('foo', 'taylor', true);
        Feature::driver('database')->set('bar', 'taylor', true);

        $this->assertSame(3, DB::table('features')->count());

        $this->artisan('pennant:prune --store=custom');

        $this->assertSame(3, DB::table('features')->count());

        $this->artisan('pennant:prune --store=database');

        $this->assertSame(2, DB::table('features')->count());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pennant store [foo] is not defined.');
        $this->artisan('pennant:prune --store=foo');
    }
}
