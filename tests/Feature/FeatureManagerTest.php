<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Lottery;
use Laravel\Feature\Drivers\ArrayDriver;
use Laravel\Feature\Drivers\DatabaseDriver;
use Laravel\Feature\Feature;
use Tests\TestCase;

class FeatureManagerTest extends TestCase
{
    public function test_default_driver_is_array_driver()
    {
        $this->assertInstanceOf(ArrayDriver::class, Feature::driver()->toBaseDriver());
    }

    public function test_it_checks_config_for_driver()
    {
        Config::set('features.default', 'database');

        $this->assertInstanceOf(DatabaseDriver::class, Feature::driver()->toBaseDriver());
    }

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

    public function test_it_can_add_the_authenicated_user_to_scope()
    {
        $user = new User(['id' => 2]);
        Auth::login($user);

        Feature::forTheAuthenticatedUser()->activate('foo');

        $this->assertFalse(Feature::isActive('foo'));
        $this->assertFalse(Feature::for('misc')->isActive('foo'));
        $this->assertTrue(Feature::forTheAuthenticatedUser()->isActive('foo'));
        $this->assertTrue(Feature::for($user)->isActive('foo'));
        $this->assertTrue(Feature::for('eloquent_model:Tests\Feature\User:2')->isActive('foo'));
    }

    public function test_it_throws_if_there_is_no_authenticated_user()
    {
        $this->expectExceptionMessage('There is no user currently authenticated.');

        Feature::forTheAuthenticatedUser();
    }

    public function test_it_can_return_lottery_as_value()
    {
        Lottery::fix([true, true, true, true, false]);

        Feature::register('foo', Lottery::odds(1, 100));

        Feature::load('foo');
        $this->assertTrue(Feature::isActive('foo'));

        Feature::load('foo');
        $this->assertTrue(Feature::isActive('foo'));

        Feature::load(['foo']);
        $this->assertTrue(Feature::isActive('foo'));

        Feature::load(['foo']);
        $this->assertTrue(Feature::isActive('foo'));

        Feature::load(['foo']);
        $this->assertFalse(Feature::isActive('foo'));
    }
}
