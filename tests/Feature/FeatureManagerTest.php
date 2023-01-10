<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Lottery;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class FeatureManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_chain_scope_additions()
    {
        Feature::for('tim@laravel.com')->for('jess@laravel.com')->activate('foo');

        $this->assertFalse(Feature::isActive('foo'));
        $this->assertTrue(Feature::for('tim@laravel.com')->isActive('foo'));
        $this->assertTrue(Feature::for('jess@laravel.com')->isActive('foo'));
        $this->assertFalse(Feature::for('taylor@laravel.com')->isActive('foo'));
        $this->assertTrue(Feature::for('jess@laravel.com')->for('tim@laravel.com')->isActive('foo'));
        $this->assertFalse(Feature::for('tim@laravel.com')->for('jess@laravel.com')->for('taylor@laravel.com')->isActive('foo'));

        Feature::for('taylor@laravel.com')->activate('foo');
        $this->assertTrue(Feature::for('tim@laravel.com')->for('jess@laravel.com')->for('taylor@laravel.com')->isActive('foo'));
    }

    public function test_the_authenticated_user_is_the_default_scope()
    {
        $user = new User(['id' => 2]);
        Auth::login($user);

        Feature::activate('foo');

        $this->assertFalse(Feature::for('misc')->isActive('foo'));
        $this->assertTrue(Feature::isActive('foo'));
        $this->assertTrue(Feature::for($user)->isActive('foo'));
    }

    public function test_it_can_return_lottery_as_value()
    {
        Lottery::fix([true, true, true, true, false]);

        Feature::register('foo', Lottery::odds(1, 100));

        Feature::load('foo');
        $this->assertTrue(Feature::isActive('foo'));
        Feature::forget('foo');

        Feature::load('foo');
        $this->assertTrue(Feature::isActive('foo'));
        Feature::forget('foo');

        Feature::load(['foo']);
        $this->assertTrue(Feature::isActive('foo'));
        Feature::forget('foo');

        Feature::load(['foo']);
        $this->assertTrue(Feature::isActive('foo'));
        Feature::forget('foo');

        Feature::load(['foo']);
        $this->assertFalse(Feature::isActive('foo'));
    }
}
