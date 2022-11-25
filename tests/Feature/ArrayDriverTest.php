<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Laravel\Feature\Contracts\FeatureScopeable;
use Laravel\Feature\Events\CheckingKnownFeature;
use Laravel\Feature\Events\CheckingUnknownFeature;
use Tests\TestCase;

class ArrayDriverTest extends TestCase
{
    public function test_it_defaults_to_false_for_unknown_values_and_dispatches_unknown_feature_event()
    {
        Event::fake([CheckingUnknownFeature::class]);
        $driver = $this->createManager()->driver('array')->toBaseDriver();

        $result = $driver->isActive('foo');

        $this->assertFalse($result);
        Event::assertDispatchedTimes(CheckingUnknownFeature::class, 1);
        Event::assertDispatched(function (CheckingUnknownFeature $event) {
            $this->assertSame('foo', $event->feature);
            $this->assertNull($event->scope);

            return true;
        });
    }

    public function test_it_can_register_default_values()
    {
        $driver = $this->createManager()->driver('array')->toBaseDriver();

        $driver->register('true', fn () => true);
        $driver->register('false', fn () => false);

        $true = $driver->isActive('true');
        $false = $driver->isActive('false');

        $this->assertTrue($true);
        $this->assertFalse($false);
    }

    public function test_it_caches_state_after_resolving()
    {
        $driver = $this->createManager()->driver('array')->toBaseDriver();

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

    public function test_user_returned_boolean_ish_values_are_cast_to_booleans()
    {
        $driver = $this->createManager()->driver('array')->toBaseDriver();
        $driver->register('foo', function () use (&$called) {
            return 1;
        });

        $result = $driver->isActive('foo');

        $this->assertTrue($result);
    }

    public function test_it_can_check_if_a_feature_is_active_or_inactive_and_it_dispatches_events()
    {
        Event::fake([CheckingKnownFeature::class]);
        $driver = $this->createManager()->driver('array')->toBaseDriver();

        $driver->activate('foo');
        $this->assertTrue($driver->isActive('foo'));

        $driver->deactivate('foo');
        $this->assertFalse($driver->isActive('foo'));

        $driver->activate('foo');
        $this->assertTrue($driver->isActive('foo'));

        Event::assertDispatchedTimes(CheckingKnownFeature::class, 3);
        Event::assertDispatched(function (CheckingKnownFeature $event) {
            $this->assertSame('foo', $event->feature);
            $this->assertNull($event->scope);

            return true;
        });
    }

    public function test_it_can_activate_and_deactivate_several_features_at_once()
    {
        $driver = $this->createManager()->driver('array')->toBaseDriver();

        $driver->activate(['foo', 'bar']);
        $this->assertTrue($driver->isActive('foo'));
        $this->assertTrue($driver->isActive('bar'));

        $driver->deactivate(['foo', 'bar']);
        $this->assertFalse($driver->isActive('foo'));
        $this->assertFalse($driver->isActive('bar'));

        $driver->activate(['foo', 'bar']);
        $this->assertTrue($driver->isActive('foo'));
        $this->assertTrue($driver->isActive('bar'));
    }

    public function test_it_provides_scope_to_resolvers()
    {
        $driver = $this->createManager()->driver('array')->toBaseDriver();
        $active = new User(['id' => 1]);
        $inactive = new User(['id' => 2]);

        $driver->register('foo', fn ($user) => $user?->id === 1);

        $this->assertFalse($driver->isActive('foo'));
        $this->assertTrue($driver->isActive('foo', collect([$active])));
        $this->assertFalse($driver->isActive('foo', collect([$inactive])));
    }

    public function test_it_can_check_if_a_feature_is_active_or_inactive_with_scope()
    {
        $driver = $this->createManager()->driver('array')->toBaseDriver();
        $active = new User(['id' => 1]);
        $inactive = new User(['id' => 2]);

        $driver->activate('foo', collect([$active]));
        $this->assertFalse($driver->isActive('foo'));
        $this->assertFalse($driver->isActive('foo', collect([])));
        $this->assertFalse($driver->isActive('foo', collect([null])));
        $this->assertTrue($driver->isActive('foo', collect([$active])));
        $this->assertFalse($driver->isActive('foo', collect([$inactive])));

        $driver->deactivate('foo', collect([$active]));
        $this->assertFalse($driver->isActive('foo'));
        $this->assertFalse($driver->isActive('foo', collect([])));
        $this->assertFalse($driver->isActive('foo', collect([null])));
        $this->assertFalse($driver->isActive('foo', collect([$active])));
        $this->assertFalse($driver->isActive('foo', collect([$inactive])));
    }

    public function test_it_can_activate_and_deactivate_feature_with_an_array_of_scope()
    {
        $driver = $this->createManager()->driver('array')->toBaseDriver();
        $first = new User(['id' => 1]);
        $second = new User(['id' => 2]);
        $third = new User(['id' => 3]);

        $driver->activate('foo', collect([$first, $second]));
        $this->assertFalse($driver->isActive('foo'));
        $this->assertTrue($driver->isActive('foo', collect([$first])));
        $this->assertTrue($driver->isActive('foo', collect([$second])));
        $this->assertFalse($driver->isActive('foo', collect([$third])));

        $driver->deactivate('foo', collect([$first, $second]));
        $this->assertFalse($driver->isActive('foo', collect([$first])));
        $this->assertFalse($driver->isActive('foo', collect([$second])));
        $this->assertFalse($driver->isActive('foo', collect([$third])));
    }

    public function test_it_can_check_if_a_feature_is_active_or_inactive_with_an_array_of_scope()
    {
        $driver = $this->createManager()->driver('array')->toBaseDriver();
        $first = new User(['id' => 1]);
        $second = new User(['id' => 2]);

        $driver->activate('foo', collect([$first]));
        $this->assertFalse($driver->isActive('foo'));
        $this->assertTrue($driver->isActive('foo', collect([$first])));
        $this->assertFalse($driver->isActive('foo', collect([$first, null])));
        $this->assertFalse($driver->isActive('foo', collect([$first, $second])));
        $this->assertFalse($driver->isActive('foo', collect([$second])));

        $driver->activate('foo', collect([$second]));
        $this->assertFalse($driver->isActive('foo'));
        $this->assertTrue($driver->isActive('foo', collect([$first])));
        $this->assertFalse($driver->isActive('foo', collect([$first, null])));
        $this->assertTrue($driver->isActive('foo', collect([$first, $second])));
        $this->assertTrue($driver->isActive('foo', collect([$second])));

        $driver->activate('foo');
        $this->assertTrue($driver->isActive('foo'));
        $this->assertTrue($driver->isActive('foo', collect([$first])));
        $this->assertTrue($driver->isActive('foo', collect([$first, null])));
        $this->assertTrue($driver->isActive('foo', collect([$first, $second])));
        $this->assertTrue($driver->isActive('foo', collect([$second])));
    }

    public function test_it_sees_null_and_empty_string_as_different_things()
    {
        $driver = $this->createManager()->driver('array')->toBaseDriver();

        $driver->activate('foo');
        $this->assertFalse($driver->isActive('foo', collect([''])));
        $this->assertTrue($driver->isActive('foo', collect([null])));
        $this->assertTrue($driver->isActive('foo'));

        $driver->activate('bar', collect(['']));
        $this->assertTrue($driver->isActive('bar', collect([''])));
        $this->assertFalse($driver->isActive('bar', collect([null])));
        $this->assertFalse($driver->isActive('bar'));
    }

    public function test_scope_can_be_strings_like_email_addresses()
    {
        $driver = $this->createManager()->driver('array')->toBaseDriver();

        $driver->activate('foo', collect(['tim@laravel.com']));
        $this->assertFalse($driver->isActive('foo', collect(['tim@example.com'])));
        $this->assertTrue($driver->isActive('foo', collect(['tim@laravel.com'])));
    }

    public function test_it_can_handle_feature_scopeable_objects()
    {
        $driver = $this->createManager()->driver('array')->toBaseDriver();
        $scopeable = new class implements FeatureScopeable
        {
            public function toFeatureScopeIdentifier()
            {
                return 'tim@laravel.com';
            }
        };

        $driver->activate('foo', collect([$scopeable]));
        $this->assertFalse($driver->isActive('foo', collect(['tim@example.com'])));
        $this->assertTrue($driver->isActive('foo', collect(['tim@laravel.com'])));
        $this->assertTrue($driver->isActive('foo', collect([$scopeable])));
    }

    public function test_it_users_the_morph_map()
    {
        $driver = $this->createManager()->driver('array')->toBaseDriver();
        $user = new User(['id' => 1]);

        Relation::morphMap([]);
        $driver->activate('foo', collect([$user]));

        $this->assertTrue($driver->isActive('foo', collect([$user])));
        $this->assertTrue($driver->isActive('foo', collect(['eloquent_model:Tests\Feature\User:1'])));
        $this->assertFalse($driver->isActive('foo', collect(['eloquent_model:user:1'])));

        $driver = $this->createManager()->driver('array')->toBaseDriver();
        $user = new User(['id' => 1]);

        Relation::morphMap(['user' => User::class]);
        $driver->activate('foo', collect([$user]));

        $this->assertTrue($driver->isActive('foo', collect([$user])));
        $this->assertFalse($driver->isActive('foo', collect(['eloquent_model:Tests\Feature\User:1'])));
        $this->assertTrue($driver->isActive('foo', collect(['eloquent_model:user:1'])));

        // cleanup
        Relation::$morphMap = [];
    }

    public function test_it_sees_null_and_empty_array_and_empyt_array_with_null_as_same_thing()
    {
        $driver = $this->createManager()->driver('array')->toBaseDriver();

        $driver->activate('foo');
        $this->assertTrue($driver->isActive('foo', collect([])));
        $this->assertTrue($driver->isActive('foo', collect([null])));
        $this->assertTrue($driver->isActive('foo'));
    }
}
