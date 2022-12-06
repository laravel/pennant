<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Laravel\Feature\Contracts\FeatureScopeable;
use Laravel\Feature\Events\CheckingKnownFeature;
use Laravel\Feature\Events\CheckingUnknownFeature;
use Laravel\Feature\Feature;
use Tests\TestCase;

class ArrayDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('features.default', 'array');
    }

    public function test_it_defaults_to_false_for_unknown_values()
    {
        $result = Feature::isActive('foo');

        $this->assertFalse($result);
    }

    public function test_it_dispatches_events_on_unknown_feature_checks()
    {
        Event::fake([CheckingUnknownFeature::class]);

        Feature::isActive('foo');

        Event::assertDispatchedTimes(CheckingUnknownFeature::class, 1);
        Event::assertDispatched(function (CheckingUnknownFeature $event) {
            $this->assertSame('foo', $event->feature);
            $this->assertNull($event->scope);

            return true;
        });
    }

    public function test_it_can_register_default_boolean_values()
    {
        Feature::register('true', fn () => true);
        Feature::register('false', fn () => false);

        $true = Feature::isActive('true');
        $false = Feature::isActive('false');

        $this->assertTrue($true);
        $this->assertFalse($false);
    }

    public function test_it_caches_state_after_resolving()
    {
        $called = 0;
        Feature::register('foo', function () use (&$called) {
            $called++;

            return true;
        });

        $this->assertSame(0, $called);

        Feature::isActive('foo');
        $this->assertSame(1, $called);

        Feature::isActive('foo');
        $this->assertSame(1, $called);
    }

    public function test_non_false_registered_values_are_considered_active()
    {
        Feature::register('one', fn () => 1);
        Feature::register('zero', fn () => 0);
        Feature::register('null', fn () => null);
        Feature::register('empty-string', fn () => '');

        $this->assertTrue(Feature::isActive('one'));
        $this->assertTrue(Feature::isActive('zero'));
        $this->assertTrue(Feature::isActive('null'));
        $this->assertTrue(Feature::isActive('empty-string'));
    }

    public function test_it_can_programatically_activate_and_deativate_features()
    {
        Feature::activate('foo');
        $this->assertTrue(Feature::isActive('foo'));

        Feature::deactivate('foo');
        $this->assertFalse(Feature::isActive('foo'));

        Feature::activate('foo');
        $this->assertTrue(Feature::isActive('foo'));
    }

    public function test_it_dispatches_events_when_checking_known_features()
    {
        Event::fake([CheckingKnownFeature::class]);
        Feature::register('foo', fn () => true);
        Feature::deactivate('bar');

        Feature::isActive('foo');
        Feature::isActive('bar');

        Event::assertDispatchedTimes(CheckingKnownFeature::class, 2);
        Event::assertDispatched(function (CheckingKnownFeature $event) {
            return $event->feature === 'foo' && $event->scope === null;
        });
        Event::assertDispatched(function (CheckingKnownFeature $event) {
            return $event->feature === 'bar' && $event->scope === null;
        });
    }

    public function test_it_can_activate_and_deactivate_several_features_at_once()
    {
        Feature::activate(['foo', 'bar']);

        $this->assertTrue(Feature::isActive('foo'));
        $this->assertTrue(Feature::isActive('bar'));
        $this->assertFalse(Feature::isActive('baz'));

        Feature::deactivate(['foo', 'bar']);

        $this->assertFalse(Feature::isActive('foo'));
        $this->assertFalse(Feature::isActive('bar'));
        $this->assertFalse(Feature::isActive('bar'));

        Feature::activate(['bar', 'baz']);

        $this->assertFalse(Feature::isActive('foo'));
        $this->assertTrue(Feature::isActive('bar'));
        $this->assertTrue(Feature::isActive('bar'));
    }

    public function test_it_can_check_if_multiple_features_are_active_at_once()
    {
        Feature::activate(['foo', 'bar']);

        $this->assertTrue(Feature::isActive(['foo']));
        $this->assertTrue(Feature::isActive(['foo', 'bar']));
        $this->assertFalse(Feature::isActive(['foo', 'bar', 'baz']));

        Feature::deactivate('baz');

        $this->assertTrue(Feature::isActive(['foo']));
        $this->assertTrue(Feature::isActive(['foo', 'bar']));
        $this->assertFalse(Feature::isActive(['foo', 'bar', 'baz']));
    }

    public function test_it_can_scope_features()
    {
        $active = new User(['id' => 1]);
        $inactive = new User(['id' => 2]);
        $captured = [];

        Feature::register('foo', function ($scope) use (&$captured) {
            $captured[] = $scope;

            return $scope?->id === 1;
        });

        $this->assertFalse(Feature::isActive('foo'));
        $this->assertTrue(Feature::for($active)->isActive('foo'));
        $this->assertFalse(Feature::for($inactive)->isActive('foo'));
        $this->assertSame([null, $active, $inactive], $captured);
    }

    public function test_it_can_activate_and_deactivate_features_with_scope()
    {
        $first = new User(['id' => 1]);
        $second = new User(['id' => 2]);

        Feature::for($first)->activate('foo');

        $this->assertFalse(Feature::isActive('foo'));
        $this->assertTrue(Feature::for($first)->isActive('foo'));
        $this->assertFalse(Feature::for($second)->isActive('foo'));
    }

    public function test_it_can_activate_and_deactivate_features_for_multiple_scope_at_once()
    {
        $first = new User(['id' => 1]);
        $second = new User(['id' => 2]);
        $third = new User(['id' => 3]);

        Feature::for([$first, $second])->activate('foo');

        $this->assertFalse(Feature::isActive('foo'));
        $this->assertTrue(Feature::for($first)->isActive('foo'));
        $this->assertTrue(Feature::for($second)->isActive('foo'));
        $this->assertFalse(Feature::for($third)->isActive('foo'));
    }

    public function test_it_can_activate_and_deactivate_multiple_features_for_multiple_scope_at_once()
    {
        $first = new User(['id' => 1]);
        $second = new User(['id' => 2]);
        $third = new User(['id' => 3]);

        Feature::for([$first, $second])->activate(['foo', 'bar']);

        $this->assertFalse(Feature::isActive('foo'));
        $this->assertTrue(Feature::for($first)->isActive('foo'));
        $this->assertTrue(Feature::for($second)->isActive('foo'));
        $this->assertFalse(Feature::for($third)->isActive('foo'));

        $this->assertFalse(Feature::isActive('bar'));
        $this->assertTrue(Feature::for($first)->isActive('bar'));
        $this->assertTrue(Feature::for($second)->isActive('bar'));
        $this->assertFalse(Feature::for($third)->isActive('bar'));
    }

    public function test_it_can_check_multiple_features_for_multiple_scope_at_once()
    {
        $first = new User(['id' => 1]);
        $second = new User(['id' => 2]);
        $third = new User(['id' => 3]);

        Feature::for([$first, $second])->activate(['foo', 'bar']);

        $this->assertFalse(Feature::isActive(['foo', 'bar']));
        $this->assertTrue(Feature::for($first)->isActive(['foo', 'bar']));
        $this->assertTrue(Feature::for($second)->isActive(['foo', 'bar']));
        $this->assertFalse(Feature::for($third)->isActive(['foo', 'bar']));

        $this->assertTrue(Feature::for([$first, $second])->isActive(['foo', 'bar']));
        $this->assertFalse(Feature::for([$second, $third])->isActive(['foo', 'bar']));
        $this->assertFalse(Feature::for([$first, $second, $third])->isActive(['foo', 'bar']));
    }

    public function test_null_is_same_as_global()
    {
        Feature::activate('foo');

        $this->assertTrue(Feature::for(null)->isActive('foo'));
    }

    public function test_it_sees_null_and_empty_string_as_different_things()
    {
        Feature::activate('foo');

        $this->assertFalse(Feature::for('')->isActive('foo'));
        $this->assertTrue(Feature::for(null)->isActive('foo'));
        $this->assertTrue(Feature::isActive('foo'));

        Feature::for('')->activate('bar');

        $this->assertTrue(Feature::for('')->isActive('bar'));
        $this->assertFalse(Feature::for(null)->isActive('bar'));
        $this->assertFalse(Feature::isActive('bar'));
    }

    public function test_scope_can_be_strings_like_email_addresses()
    {
        Feature::for('tim@laravel.com')->activate('foo');

        $this->assertFalse(Feature::for('james@laravel.com')->isActive('foo'));
        $this->assertTrue(Feature::for('tim@laravel.com')->isActive('foo'));
    }

    public function test_it_can_handle_feature_scopeable_objects()
    {
        $scopeable = fn () => new class extends User implements FeatureScopeable
        {
            public function toFeatureScopeIdentifier($driver)
            {
                return 'tim@laravel.com';
            }
        };

        Feature::for($scopeable())->activate('foo');

        $this->assertFalse(Feature::for('james@laravel.com')->isActive('foo'));
        $this->assertTrue(Feature::for('tim@laravel.com')->isActive('foo'));
        $this->assertTrue(Feature::for($scopeable())->isActive('foo'));
    }

    public function test_it_can_load_feature_state_into_memory()
    {
        $called = ['foo' => 0, 'bar' => 0];
        Feature::register('foo', function () use (&$called) {
            $called['foo']++;
        });
        Feature::register('bar', function () use (&$called) {
            $called['bar']++;
        });

        $this->assertSame(0, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::load('foo');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::isActive('foo');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::load(['foo']);
        $this->assertSame(2, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::isActive('foo');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::load('bar');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::isActive('bar');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::load(['bar']);
        $this->assertSame(2, $called['foo']);
        $this->assertSame(2, $called['bar']);

        Feature::load(['foo', 'bar']);
        $this->assertSame(3, $called['foo']);
        $this->assertSame(3, $called['bar']);

        Feature::isActive(['foo', 'bar']);
        $this->assertSame(3, $called['foo']);
        $this->assertSame(3, $called['bar']);
    }

    public function test_it_can_load_scoped_feature_state_into_memory()
    {
        $called = ['foo' => 0, 'bar' => 0];
        Feature::register('foo', function ($scope) use (&$called) {
            $called['foo']++;
        });
        Feature::register('bar', function () use (&$called) {
            $called['bar']++;
        });

        $this->assertSame(0, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::load(['foo' => 'loaded']);
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->isActive('foo');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::load(['foo' => 'loaded']);
        $this->assertSame(2, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->isActive('foo');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::load(['bar' => 'loaded']);
        $this->assertSame(2, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::for('loaded')->isActive('bar');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::for('noloaded')->isActive('bar');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(2, $called['bar']);

        Feature::load([
            'foo' => [1, 2, 3],
            'bar' => [2],
        ]);
        $this->assertSame(5, $called['foo']);
        $this->assertSame(3, $called['bar']);

        Feature::for([1, 2, 3])->isActive('foo');
        Feature::for([2])->isActive('bar');
        $this->assertSame(5, $called['foo']);
        $this->assertSame(3, $called['bar']);
    }

    public function test_it_can_load_missing_feature_state_into_memory()
    {
        $called = ['foo' => 0, 'bar' => 0];
        Feature::register('foo', function () use (&$called) {
            $called['foo']++;
        });
        Feature::register('bar', function () use (&$called) {
            $called['bar']++;
        });

        $this->assertSame(0, $called['foo']);

        Feature::loadMissing('foo');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::loadMissing('foo');
        $this->assertSame(0, $called['bar']);

        Feature::isActive('foo');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::isActive('bar');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::loadMissing(['bar']);
        $this->assertSame(1, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::loadMissing([
            'foo' => [1, 2, 3],
            'bar' => [2],
        ]);
        $this->assertSame(4, $called['foo']);
        $this->assertSame(2, $called['bar']);

        Feature::for([1, 2, 3])->isActive('foo');
        Feature::for([2])->isActive('bar');
        $this->assertSame(4, $called['foo']);
        $this->assertSame(2, $called['bar']);
    }
}
