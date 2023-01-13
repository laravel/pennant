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

        $this->assertFalse(Feature::active('foo'));
        $this->assertTrue(Feature::for('tim@laravel.com')->active('foo'));
        $this->assertTrue(Feature::for('jess@laravel.com')->active('foo'));
        $this->assertFalse(Feature::for('taylor@laravel.com')->active('foo'));
        $this->assertTrue(Feature::for('jess@laravel.com')->for('tim@laravel.com')->active('foo'));
        $this->assertFalse(Feature::for('tim@laravel.com')->for('jess@laravel.com')->for('taylor@laravel.com')->active('foo'));

        Feature::for('taylor@laravel.com')->activate('foo');
        $this->assertTrue(Feature::for('tim@laravel.com')->for('jess@laravel.com')->for('taylor@laravel.com')->active('foo'));
    }

    public function test_the_authenticated_user_is_the_default_scope()
    {
        $user = new User(['id' => 2]);
        Auth::login($user);

        Feature::activate('foo');

        $this->assertFalse(Feature::for('misc')->active('foo'));
        $this->assertTrue(Feature::active('foo'));
        $this->assertTrue(Feature::for($user)->active('foo'));
    }

    public function test_it_can_return_lottery_as_value()
    {
        Lottery::fix([true, true, true, true, false]);

        Feature::define('foo', Lottery::odds(1, 100));

        Feature::load('foo');
        $this->assertTrue(Feature::active('foo'));
        Feature::forget('foo');

        Feature::load('foo');
        $this->assertTrue(Feature::active('foo'));
        Feature::forget('foo');

        Feature::load(['foo']);
        $this->assertTrue(Feature::active('foo'));
        Feature::forget('foo');

        Feature::load(['foo']);
        $this->assertTrue(Feature::active('foo'));
        Feature::forget('foo');

        Feature::load(['foo']);
        $this->assertFalse(Feature::active('foo'));
    }
}
