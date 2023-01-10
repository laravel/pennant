<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Lottery;
use Laravel\Feature\Contracts\FeatureScopeable;
use Laravel\Feature\Events\RetrievingKnownFeature;
use Laravel\Feature\Events\RetrievingUnknownFeature;
use Laravel\Feature\Feature;
use RuntimeException;
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
        Event::fake([RetrievingUnknownFeature::class]);

        Feature::isActive('foo');

        Event::assertDispatchedTimes(RetrievingUnknownFeature::class, 1);
        Event::assertDispatched(function (RetrievingUnknownFeature $event) {
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

    public function test_it_can_register_complex_values()
    {
        Feature::register('config', fn () => [
            'color' => 'red',
            'default' => 'api',
        ]);

        $isActive = Feature::isActive('config');
        $value = Feature::value('config');

        $this->assertTrue($isActive);
        $this->assertSame([
            'color' => 'red',
            'default' => 'api',
        ], $value);

        Feature::for('tim')->activate('new-api', 'foo');

        $isActive = Feature::for('tim')->isActive('new-api');
        $value = Feature::for('tim')->value('new-api');

        $this->assertTrue($isActive);
        $this->assertSame('foo', $value);
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
        Feature::register('true', fn () => true);
        Feature::register('false', fn () => false);
        Feature::register('one', fn () => 1);
        Feature::register('zero', fn () => 0);
        Feature::register('null', fn () => null);
        Feature::register('empty-string', fn () => '');

        $this->assertTrue(Feature::isActive('true'));
        $this->assertFalse(Feature::isActive('false'));
        $this->assertTrue(Feature::isActive('one'));
        $this->assertTrue(Feature::isActive('zero'));
        $this->assertTrue(Feature::isActive('null'));
        $this->assertTrue(Feature::isActive('empty-string'));

        $this->assertFalse(Feature::isInactive('true'));
        $this->assertTrue(Feature::isInactive('false'));
        $this->assertFalse(Feature::isInactive('one'));
        $this->assertFalse(Feature::isInactive('zero'));
        $this->assertFalse(Feature::isInactive('null'));
        $this->assertFalse(Feature::isInactive('empty-string'));
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

    public function test_it_dispatches_events_when_resolving_feature_into_memory()
    {
        Event::fake([RetrievingKnownFeature::class]);
        Feature::register('foo', fn () => true);

        Feature::isActive('foo');
        Feature::isActive('foo');

        Event::assertDispatchedTimes(RetrievingKnownFeature::class, 1);
        Event::assertDispatched(function (RetrievingKnownFeature $event) {
            return $event->feature === 'foo' && $event->scope === null;
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

        $this->assertTrue(Feature::allAreActive(['foo']));
        $this->assertTrue(Feature::allAreActive(['foo', 'bar']));
        $this->assertFalse(Feature::allAreActive(['foo', 'bar', 'baz']));

        Feature::deactivate('baz');

        $this->assertTrue(Feature::allAreActive(['foo']));
        $this->assertTrue(Feature::allAreActive(['foo', 'bar']));
        $this->assertFalse(Feature::allAreActive(['foo', 'bar', 'baz']));
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

        $this->assertFalse(Feature::allAreActive(['foo', 'bar']));
        $this->assertTrue(Feature::for($first)->allAreActive(['foo', 'bar']));
        $this->assertTrue(Feature::for($second)->allAreActive(['foo', 'bar']));
        $this->assertFalse(Feature::for($third)->allAreActive(['foo', 'bar']));

        $this->assertTrue(Feature::for([$first, $second])->allAreActive(['foo', 'bar']));
        $this->assertFalse(Feature::for([$second, $third])->allAreActive(['foo', 'bar']));
        $this->assertFalse(Feature::for([$first, $second, $third])->allAreActive(['foo', 'bar']));
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
            public function toFeatureIdentifier($driver)
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

        Feature::allAreActive(['foo', 'bar']);
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

    public function test_it_can_load_against_scope()
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

        Feature::for('loaded')->load(['foo']);
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->isActive('foo');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->load('foo');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->isActive('foo');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->load('bar');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::for('loaded')->isActive('bar');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::for('noloaded')->isActive('bar');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(2, $called['bar']);

        Feature::for([1, 2, 3])->load(['foo']);
        Feature::for(2)->load(['bar']);
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

    public function test_it_throws_when_calling_value_with_multiple_scope()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('It is not possible to retrieve the values for mutliple scopes.');

        Feature::for([1, 2, 3])->value('foo');
    }

    public function test_it_can_retrive_value_for_multiple_scopes()
    {
        Feature::register('foo', fn ($scope) => $scope);

        $values = Feature::for(1)->values(['foo']);

        $this->assertSame([
            'foo' => 1,
        ], $values);
    }

    public function test_it_can_retrive_value_for_multiple_scopes_and_features()
    {
        Feature::register('foo', fn ($scope) => $scope);
        Feature::register('bar', fn ($scope) => $scope * 2);

        $values = Feature::for(2)->values(['foo', 'bar']);

        $this->assertSame([
            'foo' => 2,
            'bar' => 4,
        ], $values);
    }

    public function test_it_may_register_shorthand_feature_values()
    {
        Feature::register('foo', 'value');

        $value = Feature::value('foo');

        $this->assertSame('value', $value);
    }

    public function test_it_can_use_lottery()
    {
        Feature::register('foo', Lottery::odds(1, 1));
        Feature::register('bar', Lottery::odds(0, 1));
        Feature::register('baz', fn () => Lottery::odds(0, 1));

        $this->assertTrue(Feature::value('foo'));
        $this->assertFalse(Feature::value('bar'));
        $this->assertFalse(Feature::value('baz'));
    }

    public function test_it_can_retrieve_registered_features()
    {
        Feature::register('foo', fn () => true);
        Feature::register('bar', fn () => false);
        Feature::register('baz', fn () => false);

        $registered = Feature::registered();

        $this->assertSame(['foo', 'bar', 'baz'], $registered);
    }

    public function test_it_can_clear_the_cache()
    {
        $called = 0;
        Feature::register('foo', function () use (&$called) {
            $called++;
        });

        Feature::isActive('foo');
        Feature::flushCache();
        Feature::isActive('foo');

        $this->assertSame(2, $called);
    }

    public function test_it_can_get_all_features()
    {
        Feature::register('foo', fn () => true);
        Feature::register('bar', fn () => false);

        $all = Feature::all();

        $this->assertSame([
            'foo' => true,
            'bar' => false,
        ], $all);
    }

    public function test_it_can_register_feature_via_class()
    {
        Feature::register(MyFeature::class);

        $value = Feature::for('shared')->value('my-feature');

        $this->assertSame('shared-123', $value);
    }

    public function test_it_can_register_features_via_class_without_name()
    {
        Feature::register(MyUnnamedFeature::class);

        $value = Feature::for('shared')->value(MyUnnamedFeature::class);

        $this->assertSame('shared-123', $value);
    }

    public function test_it_can_reevaluate_feature_state()
    {
        Feature::register('foo', fn () => false);
        $this->assertFalse(Feature::for('tim')->value('foo'));

        Feature::for('tim')->forget('foo');

        Feature::register('foo', fn () => true);
        $this->assertTrue(Feature::for('tim')->value('foo'));
    }

    public function test_it_can_customise_default_scope()
    {
        $scopes = [];
        Feature::register('foo', function ($scope) use (&$scopes) {
            $scopes[] = $scope;
        });

        Feature::isActive('foo');

        Auth::login($user = new User());
        Feature::isActive('foo');

        Feature::resolveScopeUsing(fn () => 'bar');
        Feature::isActive('foo');

        $this->assertSame([
            null,
            $user,
            'bar',
        ], $scopes);
    }

    public function test_it_doesnt_include_default_scope_when_null()
    {
        $scopes = [];
        Feature::register('foo', function ($scope) use (&$scopes) {
            $scopes[] = $scope;
        });

        Feature::resolveScopeUsing(fn () => null);
        Feature::isActive('foo');

        $this->assertSame([
            null,
        ], $scopes);
    }

    public function test_it_uses_default_scope_for_loading_with_string()
    {
        Feature::register('feature', fn () => false);
        Feature::for('tim')->activate('feature');

        $loaded = Feature::load('feature');
        $this->assertSame(['feature' => [false]], $loaded);

        Feature::resolveScopeUsing(fn () => 'tim');

        $loaded = Feature::load('feature');
        $this->assertSame(['feature' => [true]], $loaded);
    }

    public function test_retrieving_values_after_purging()
    {
        Feature::register('foo', false);

        Feature::for('tim')->activate('foo');

        $this->assertTrue(Feature::for('tim')->isActive('foo'));

        Feature::purge('foo');

        $this->assertFalse(Feature::for('tim')->isActive('foo'));
    }
}

class MyFeature
{
    public $name = 'my-feature';

    public function __invoke($scope)
    {
        return "{$scope}-123";
    }
}

class MyUnnamedFeature
{
    public function __invoke($scope)
    {
        return "{$scope}-123";
    }
}
