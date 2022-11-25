<?php

namespace Tests\Feature;

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
}
