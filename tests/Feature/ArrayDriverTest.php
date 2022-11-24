<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Laravel\Feature\Events\CheckingUnknownFeature;
use Laravel\Feature\FeatureManager;
use Tests\TestCase;

class ArrayDriverTest extends TestCase
{
    public function test_it_defaults_to_false_for_unknown_values()
    {
        Event::fake([CheckingUnknownFeature::class]);
        $driver = $this->createManager()->driver('array');

        $result = $driver->isActive('foo');

        $this->assertFalse($result);
        Event::assertDispatched(function (CheckingUnknownFeature $event) {
            $this->assertSame('foo', $event->feature);
            $this->assertNull($event->scope);

            return true;
        });
    }

    public function test_it_can_have_default_value()
    {
        $driver = $this->createManager()->driver('array');

        $driver->register('true', fn () => true);
        $driver->register('false', fn () => false);

        $true = $driver->isActive('true');
        $false = $driver->isActive('false');

        $this->assertTrue($true);
        $this->assertFalse($false);
    }

    public function test_it_caches_result_of_resolvers()
    {
        $driver = $this->createManager()->driver('array');

        $called = 0;
        $driver->register('foo', function () use (&$called) {
            $called++;

            return true;
        });

        $driver->isActive('foo');
        $this->assertSame(1, $called);

        $driver->isActive('foo');
        $this->assertSame(1, $called);
    }

    public function test_boolean_ish_values_are_cast_to_boolean()
    {
        $driver = $this->createManager()->driver('array');
        $driver->register('foo', function () use (&$called) {
            return 1;
        });

        $result = $driver->isActive('foo');

        $this->assertTrue($result);
    }

    public function test_it_can_check_feature_is_active_or_inactive()
    {
        $driver = $this->createManager()->driver('array');

        $driver->activate('foo');
        $this->assertTrue($driver->isActive('foo'));

        $driver->deactivate('foo');
        $this->assertFalse($driver->isActive('foo'));

        $driver->activate('foo');
        $this->assertTrue($driver->isActive('foo'));
    }

    public function test_it_can_activate_and_deactivate_several_features_at_once()
    {
        $driver = $this->createManager()->driver('array');

        $driver->activate(['foo', 'bar']);
        $this->assertTrue($driver->isActive('foo'));
        $this->assertTrue($driver->isActive('bar'));

        $driver->deactivate(['foo', 'bar']);
        $this->assertFalse($driver->isActive('foo'));
        $this->assertFalse($driver->isActive('bar'));
    }

    public function test_it_can_have_a_default_value_based_on_scope()
    {
        $driver = $this->createManager()->driver('array');
        $first = new User(['id' => 1]);
        $second = new User(['id' => 2]);

        $driver->register('foo', fn ($user) => $user?->id === 1);

        $this->assertFalse($driver->isActive('foo'));
        $this->assertTrue($driver->isActive('foo', $first));
        $this->assertFalse($driver->isActive('foo', $second));
    }

    public function test_it_can_check_feature_is_active_or_inactive_with_scope()
    {
        $driver = $this->createManager()->driver('array');
        $first = new User(['id' => 1]);
        $second = new User(['id' => 2]);

        $driver->activate('foo', $first);
        $this->assertFalse($driver->isActive('foo', null));
        $this->assertTrue($driver->isActive('foo', $first));
        $this->assertFalse($driver->isActive('foo', $second));

        $driver->deactivate('foo', $first);
        $this->assertFalse($driver->isActive('foo', null));
        $this->assertFalse($driver->isActive('foo', $first));
        $this->assertFalse($driver->isActive('foo', $second));
    }

    public function test_it_can_activate_and_deactivate_feature_with_scope_list()
    {
        $driver = $this->createManager()->driver('array');
        $first = new User(['id' => 1]);
        $second = new User(['id' => 2]);
        $third = new User(['id' => 3]);

        $driver->activate('foo', [$first, $second]);
        $this->assertFalse($driver->isActive('foo'));
        $this->assertTrue($driver->isActive('foo', $first));
        $this->assertTrue($driver->isActive('foo', $second));
        $this->assertFalse($driver->isActive('foo', $third));

        $driver->deactivate('foo', [$first, $second]);
        $this->assertFalse($driver->isActive('foo', $first));
        $this->assertFalse($driver->isActive('foo', $second));
        $this->assertFalse($driver->isActive('foo', $third));
    }

    public function test_it_can_check_feature_is_active_or_inactive_with_scope_list()
    {
        $driver = $this->createManager()->driver('array');
        $first = new User(['id' => 1]);
        $second = new User(['id' => 2]);

        $driver->activate('foo', $first);
        $this->assertFalse($driver->isActive('foo'));
        $this->assertTrue($driver->isActive('foo', $first));
        $this->assertTrue($driver->isActive('foo', [$first]));
        $this->assertFalse($driver->isActive('foo', [$first, null]));
        $this->assertFalse($driver->isActive('foo', [$first, $second]));
        $this->assertFalse($driver->isActive('foo', $second));
        $this->assertFalse($driver->isActive('foo', [$second]));

        $driver->activate('foo', $second);
        $this->assertFalse($driver->isActive('foo'));
        $this->assertTrue($driver->isActive('foo', $first));
        $this->assertTrue($driver->isActive('foo', [$first]));
        $this->assertFalse($driver->isActive('foo', [$first, null]));
        $this->assertTrue($driver->isActive('foo', [$first, $second]));
        $this->assertTrue($driver->isActive('foo', $second));
        $this->assertTrue($driver->isActive('foo', [$second]));

        $driver->activate('foo');
        $this->assertTrue($driver->isActive('foo'));
        $this->assertTrue($driver->isActive('foo', $first));
        $this->assertTrue($driver->isActive('foo', [$first]));
        $this->assertTrue($driver->isActive('foo', [$first, null]));
        $this->assertTrue($driver->isActive('foo', [$first, $second]));
        $this->assertTrue($driver->isActive('foo', $second));
        $this->assertTrue($driver->isActive('foo', [$second]));
    }

    public function test_it_sees_null_and_empty_string_as_different_things()
    {
        $driver = $this->createManager()->driver('array');

        $driver->activate('foo');
        $this->assertFalse($driver->isActive('foo', ''));
        $this->assertTrue($driver->isActive('foo', null));
        $this->assertTrue($driver->isActive('foo'));

        $driver->activate('bar', '');
        $this->assertTrue($driver->isActive('bar', ''));
        $this->assertFalse($driver->isActive('bar', null));
        $this->assertFalse($driver->isActive('bar'));
    }

    public function test_it_sees_null_and_empty_array_and_empyt_array_with_null_as_same_thing()
    {
        $driver = $this->createManager()->driver('array');

        $driver->activate('foo');
        $this->assertTrue($driver->isActive('foo', []));
        $this->assertTrue($driver->isActive('foo', [null]));
        $this->assertTrue($driver->isActive('foo', null));
        $this->assertTrue($driver->isActive('foo'));
    }

    protected function createManager()
    {
        return new FeatureManager($this->app);
    }
}

class User extends Model
{
    protected $guarded = [];
}
