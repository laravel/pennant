<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Lottery;
use Laravel\Pennant\Feature;
use Tests\TestCase;
use Workbench\App\Models\User;

class FeatureManagerTest extends TestCase
{
    use LazilyRefreshDatabase;

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

    public function test_it_can_apply_macros()
    {
        Feature::resolveScopeUsing(fn () => 'default-scope');
        Feature::macro('forSession', function () {
            return $this->for('session|{Session::getId()');
        });

        $this->assertFalse(Feature::forSession()->active('my-feature'));
        $this->assertFalse(Feature::for('default-scope')->active('my-feature'));

        Feature::for('session|{Session::getId()')->activate('my-feature');

        $this->assertTrue(Feature::forSession()->active('my-feature'));
        $this->assertFalse(Feature::for('default-scope')->active('my-feature'));
    }
}
