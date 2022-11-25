<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class FeatureManagerTest extends TestCase
{
    public function test_it_proxies_feature_checks_to_driver()
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
        $this->assertTrue($manager->for(['tim@laravel.com'])->isActive('foo'));
        $this->assertTrue($manager->for('jess@laravel.com')->isActive('foo'));
        $this->assertTrue($manager->for(['jess@laravel.com'])->isActive('foo'));
        $this->assertFalse($manager->for('taylor@laravel.com')->isActive('foo'));
        $this->assertFalse($manager->for(['taylor@laravel.com'])->isActive('foo'));
        $this->assertTrue($manager->for('tim@laravel.com')->for('jess@laravel.com')->isActive('foo'));
        $this->assertTrue($manager->for(['tim@laravel.com', 'jess@laravel.com'])->isActive('foo'));
        $this->assertFalse($manager->for('tim@laravel.com')->for('jess@laravel.com')->for('taylor@laravel.com')->isActive('foo'));
        $this->assertFalse($manager->for(['tim@laravel.com', 'jess@laravel.com', 'taylor@laravel.com'])->isActive('foo'));

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
}
