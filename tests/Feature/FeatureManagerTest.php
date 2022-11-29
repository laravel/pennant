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

    public function test_feature_is_false_when_not_registered($driver)
    {
        $manager = $this->createManager();

        $this->assertFalse($manager->isActive('foo'));

        $manager->activate('foo');
        $this->assertTrue($manager->isActive('foo'));

        $manager->deactivate('foo');
        $this->assertFalse($manager->isActive('foo'));
    }

    /**
     * @dataProvider drivers
     */
    public function test_it_can_ch($driver)
    {
        $manager = $this->createManager();

        $this->assertFalse($manager->isActive('foo'));

        $manager->activate('foo');
        $this->assertTrue($manager->isActive('foo'));

        $manager->deactivate('foo');
        $this->assertFalse($manager->isActive('foo'));
    }

    public function test_it_can_build_up_scope_for_activating_and_deactivating_features()
    {
        $manager = $this->createManager();

        $this->assertFalse($manager->for('tim@laravel.com')->isActive('foo'));

        $manager->for('tim@laravel.com')->activate('foo');
        $this->assertFalse($manager->isActive('foo'));
        $this->assertTrue($manager->for('tim@laravel.com')->isActive('foo'));
        $this->assertFalse($manager->for('foo')->isActive('foo'));

        $manager->for('tim@laravel.com')->deactivate('foo');
        $this->assertFalse($manager->isActive('foo'));
        $this->assertFalse($manager->for('tim@laravel.com')->isActive('foo'));
        $this->assertFalse($manager->for('foo')->isActive('foo'));
    }

    public function test_it_can_chain_scope_additions()
    {
        $manager = $this->createManager();

        $manager->for('tim@laravel.com')->for('jess@laravel.com')->activate('foo');

        $this->assertFalse($manager->isActive('foo'));
        $this->assertTrue($manager->for('tim@laravel.com')->isActive('foo'));
        $this->assertTrue($manager->for('jess@laravel.com')->isActive('foo'));
        $this->assertFalse($manager->for('taylor@laravel.com')->isActive('foo'));
        $this->assertTrue($manager->for('jess@laravel.com')->for('tim@laravel.com')->isActive('foo'));
        $this->assertFalse($manager->for('tim@laravel.com')->for('jess@laravel.com')->for('taylor@laravel.com')->isActive('foo'));

        $manager->for('taylor@laravel.com')->activate('foo');
        $this->assertTrue($manager->for('tim@laravel.com')->for('jess@laravel.com')->for('taylor@laravel.com')->isActive('foo'));
    }

    public function test_it_can_add_everyone_to_the_scope()
    {
        $manager = $this->createManager();

        $manager->globally()->activate('foo');

        $this->assertTrue($manager->isActive('foo'));
        $this->assertTrue($manager->globally()->isActive('foo'));
        $this->assertFalse($manager->for('tim@laravel.com')->isActive('foo'));
        $this->assertFalse($manager->globally()->for('tim@laravel.com')->isActive('foo'));

        $manager->for('tim@laravel.com')->activate('foo');

        $this->assertTrue($manager->isActive('foo'));
        $this->assertTrue($manager->globally()->isActive('foo'));
        $this->assertTrue($manager->for('tim@laravel.com')->isActive('foo'));
        $this->assertTrue($manager->globally()->for('tim@laravel.com')->isActive('foo'));
    }

    public function test_it_can_add_the_authenicated_user_to_scope()
    {
        $manager = $this->createManager();
        $user = new User(['id' => 2]);
        Auth::login($user);

        $manager->forTheAuthenticatedUser()->activate('foo');

        $this->assertFalse($manager->isActive('foo'));
        $this->assertFalse($manager->for('misc')->isActive('foo'));
        $this->assertTrue($manager->forTheAuthenticatedUser()->isActive('foo'));
        $this->assertTrue($manager->for($user)->isActive('foo'));
        $this->assertTrue($manager->for('eloquent_model:Tests\Feature\User:2')->isActive('foo'));
    }

    public function test_it_throws_if_there_is_no_authenticated_user()
    {
        $manager = $this->createManager();

        $this->expectExceptionMessage('There is no user currently authenticated.');

        $manager->forTheAuthenticatedUser();
    }

    public function test_it_can_return_lottery_as_value()
    {
        $manager = $this->createManager();
        Lottery::fix([true, true, true, true, false]);

        $manager->register('foo', Lottery::odds(1, 100));

        $manager->load(['foo']);
        $this->assertTrue($manager->isActive('foo'));

        $manager->load(['foo']);
        $this->assertTrue($manager->isActive('foo'));

        $manager->load(['foo']);
        $this->assertTrue($manager->isActive('foo'));

        $manager->load(['foo']);
        $this->assertTrue($manager->isActive('foo'));

        $manager->load(['foo']);
        $this->assertFalse($manager->isActive('foo'));
    }
}
