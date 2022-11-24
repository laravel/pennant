<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Laravel\Feature\FeatureManager;
use Tests\TestCase;

class DatabaseDriverTest extends TestCase
{
    public function test_it_can_enable_and_disable_features()
    {
        $driver = $this->createManager()->driver('database');
        $this->assertFalse($driver->isActive('foo'));

        $driver->activate('foo');
        $this->assertTrue($driver->isActive('foo'));

        $driver->deactivate('foo');
        $this->assertFalse($driver->isActive('foo'));

        $driver->activate('foo');
        $this->assertTrue($driver->isActive('foo'));
    }

    public function test_it_can_enable_feature_for_specific_user()
    {
        $driver = $this->createManager()->driver('database');
        $first = new User(['id' => 1]);
        $second = new User(['id' => 2]);

        $this->assertFalse($driver->isActive('foo'));
        $this->assertFalse($driver->for($first)->isActive('foo'));
        $this->assertFalse($driver->for($second)->isActive('foo'));

        $driver->for($first)->activate('foo');

        $this->assertFalse($driver->isActive('foo'));
        $this->assertTrue($driver->for($first)->isActive('foo'));
        $this->assertFalse($driver->for($second)->isActive('foo'));

        $driver->for($first)->deactivate('foo');
        $driver->for($second)->activate('foo');

        $this->assertFalse($driver->isActive('foo'));
        $this->assertFalse($driver->for($first)->isActive('foo'));
        $this->assertTrue($driver->for($second)->isActive('foo'));
    }

    private function createManager()
    {
        return new FeatureManager($this->app);
    }
}

class User extends Model
{
    protected $guarded = [];
}
