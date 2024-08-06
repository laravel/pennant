<?php

namespace Tests\Feature;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Lottery;
use Laravel\Pennant\Contracts\FeatureScopeable;
use Laravel\Pennant\Events\AllFeaturesPurged;
use Laravel\Pennant\Events\FeatureDeleted;
use Laravel\Pennant\Events\FeatureResolved;
use Laravel\Pennant\Events\FeaturesPurged;
use Laravel\Pennant\Events\FeatureUpdated;
use Laravel\Pennant\Events\FeatureUpdatedForAllScopes;
use Laravel\Pennant\Events\UnexpectedNullScopeEncountered;
use Laravel\Pennant\Events\UnknownFeatureResolved;
use Laravel\Pennant\Feature;
use RuntimeException;
use Tests\TestCase;
use Workbench\App\Models\User;

use function Orchestra\Testbench\workbench_path;

class ArrayDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pennant.default', 'array');
    }

    public function test_it_defaults_to_false_for_unknown_values()
    {
        $result = Feature::active('foo');

        $this->assertFalse($result);
    }

    public function test_it_dispatches_events_on_unknown_feature_checks()
    {
        Event::fake([UnknownFeatureResolved::class]);

        Feature::active('foo');

        Event::assertDispatchedTimes(UnknownFeatureResolved::class, 1);
        Event::assertDispatched(function (UnknownFeatureResolved $event) {
            $this->assertSame('foo', $event->feature);
            $this->assertNull($event->scope);

            return true;
        });
    }

    public function test_it_can_register_default_boolean_values()
    {
        Feature::define('true', fn () => true);
        Feature::define('false', fn () => false);

        $true = Feature::active('true');
        $false = Feature::active('false');

        $this->assertTrue($true);
        $this->assertFalse($false);
    }

    public function test_it_can_register_complex_values()
    {
        Feature::define('config', fn () => [
            'color' => 'red',
            'default' => 'api',
        ]);

        $active = Feature::active('config');
        $value = Feature::value('config');

        $this->assertTrue($active);
        $this->assertSame([
            'color' => 'red',
            'default' => 'api',
        ], $value);

        Feature::for('tim')->activate('new-api', 'foo');

        $active = Feature::for('tim')->active('new-api');
        $value = Feature::for('tim')->value('new-api');

        $this->assertTrue($active);
        $this->assertSame('foo', $value);
    }

    public function test_it_caches_state_after_resolving()
    {
        $called = 0;
        Feature::define('foo', function () use (&$called) {
            $called++;

            return true;
        });

        $this->assertSame(0, $called);

        Feature::active('foo');
        $this->assertSame(1, $called);

        Feature::active('foo');
        $this->assertSame(1, $called);
    }

    public function test_non_false_registered_values_are_considered_active()
    {
        Feature::define('true', fn () => true);
        Feature::define('false', fn () => false);
        Feature::define('one', fn () => 1);
        Feature::define('zero', fn () => 0);
        Feature::define('null', fn () => null);
        Feature::define('empty-string', fn () => '');

        $this->assertTrue(Feature::active('true'));
        $this->assertFalse(Feature::active('false'));
        $this->assertTrue(Feature::active('one'));
        $this->assertTrue(Feature::active('zero'));
        $this->assertTrue(Feature::active('null'));
        $this->assertTrue(Feature::active('empty-string'));

        $this->assertFalse(Feature::inactive('true'));
        $this->assertTrue(Feature::inactive('false'));
        $this->assertFalse(Feature::inactive('one'));
        $this->assertFalse(Feature::inactive('zero'));
        $this->assertFalse(Feature::inactive('null'));
        $this->assertFalse(Feature::inactive('empty-string'));
    }

    public function test_it_can_programatically_activate_and_deativate_features()
    {
        Feature::activate('foo');
        $this->assertTrue(Feature::active('foo'));

        Feature::deactivate('foo');
        $this->assertFalse(Feature::active('foo'));

        Feature::activate('foo');
        $this->assertTrue(Feature::active('foo'));
    }

    public function test_it_dispatches_events_when_resolving_feature_into_memory()
    {
        Event::fake([FeatureResolved::class]);
        Feature::define('foo', fn () => true);

        Feature::active('foo');
        Feature::active('foo');

        Event::assertDispatchedTimes(FeatureResolved::class, 1);
        Event::assertDispatched(function (FeatureResolved $event) {
            return $event->feature === 'foo' && $event->scope === null;
        });
    }

    public function test_it_can_activate_and_deactivate_several_features_at_once()
    {
        Feature::activate(['foo', 'bar']);

        $this->assertTrue(Feature::active('foo'));
        $this->assertTrue(Feature::active('bar'));
        $this->assertFalse(Feature::active('baz'));

        Feature::deactivate(['foo', 'bar']);

        $this->assertFalse(Feature::active('foo'));
        $this->assertFalse(Feature::active('bar'));
        $this->assertFalse(Feature::active('bar'));

        Feature::activate(['bar', 'baz']);

        $this->assertFalse(Feature::active('foo'));
        $this->assertTrue(Feature::active('bar'));
        $this->assertTrue(Feature::active('bar'));
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

        Feature::define('foo', function ($scope) use (&$captured) {
            $captured[] = $scope;

            return $scope?->id === 1;
        });

        $this->assertFalse(Feature::active('foo'));
        $this->assertTrue(Feature::for($active)->active('foo'));
        $this->assertFalse(Feature::for($inactive)->active('foo'));
        $this->assertSame([null, $active, $inactive], $captured);
    }

    public function test_it_can_activate_and_deactivate_features_with_scope()
    {
        $first = new User(['id' => 1]);
        $second = new User(['id' => 2]);

        Feature::for($first)->activate('foo');

        $this->assertFalse(Feature::active('foo'));
        $this->assertTrue(Feature::for($first)->active('foo'));
        $this->assertFalse(Feature::for($second)->active('foo'));
    }

    public function test_it_can_activate_and_deactivate_features_for_multiple_scope_at_once()
    {
        $first = new User(['id' => 1]);
        $second = new User(['id' => 2]);
        $third = new User(['id' => 3]);

        Feature::for([$first, $second])->activate('foo');

        $this->assertFalse(Feature::active('foo'));
        $this->assertTrue(Feature::for($first)->active('foo'));
        $this->assertTrue(Feature::for($second)->active('foo'));
        $this->assertFalse(Feature::for($third)->active('foo'));
    }

    public function test_it_can_activate_and_deactivate_multiple_features_for_multiple_scope_at_once()
    {
        $first = new User(['id' => 1]);
        $second = new User(['id' => 2]);
        $third = new User(['id' => 3]);

        Feature::for([$first, $second])->activate(['foo', 'bar']);

        $this->assertFalse(Feature::active('foo'));
        $this->assertTrue(Feature::for($first)->active('foo'));
        $this->assertTrue(Feature::for($second)->active('foo'));
        $this->assertFalse(Feature::for($third)->active('foo'));

        $this->assertFalse(Feature::active('bar'));
        $this->assertTrue(Feature::for($first)->active('bar'));
        $this->assertTrue(Feature::for($second)->active('bar'));
        $this->assertFalse(Feature::for($third)->active('bar'));
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

        $this->assertTrue(Feature::for(null)->active('foo'));
    }

    public function test_it_sees_null_and_empty_string_as_different_things()
    {
        Feature::activate('foo');

        $this->assertFalse(Feature::for('')->active('foo'));
        $this->assertTrue(Feature::for(null)->active('foo'));
        $this->assertTrue(Feature::active('foo'));

        Feature::for('')->activate('bar');

        $this->assertTrue(Feature::for('')->active('bar'));
        $this->assertFalse(Feature::for(null)->active('bar'));
        $this->assertFalse(Feature::active('bar'));
    }

    public function test_scope_can_be_strings_like_email_addresses()
    {
        Feature::for('tim@laravel.com')->activate('foo');

        $this->assertFalse(Feature::for('james@laravel.com')->active('foo'));
        $this->assertTrue(Feature::for('tim@laravel.com')->active('foo'));
    }

    public function test_it_can_handle_feature_scopeable_objects()
    {
        $scopeable = fn () => new class extends User implements FeatureScopeable
        {
            public function toFeatureIdentifier($driver): mixed
            {
                return 'tim@laravel.com';
            }
        };

        Feature::for($scopeable())->activate('foo');

        $this->assertFalse(Feature::for('james@laravel.com')->active('foo'));
        $this->assertTrue(Feature::for('tim@laravel.com')->active('foo'));
        $this->assertTrue(Feature::for($scopeable())->active('foo'));
    }

    public function test_it_can_load_feature_state_into_memory()
    {
        $called = ['foo' => 0, 'bar' => 0];
        Feature::define('foo', function () use (&$called) {
            $called['foo']++;
        });
        Feature::define('bar', function () use (&$called) {
            $called['bar']++;
        });

        $this->assertSame(0, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::load('foo');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::active('foo');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::load(['foo']);
        $this->assertSame(2, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::active('foo');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::load('bar');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::active('bar');
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
        Feature::define('foo', function ($scope) use (&$called) {
            $called['foo']++;
        });
        Feature::define('bar', function () use (&$called) {
            $called['bar']++;
        });

        $this->assertSame(0, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->load(['foo']);
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->active('foo');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->load('foo');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->active('foo');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->load('bar');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::for('loaded')->active('bar');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::for('noloaded')->active('bar');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(2, $called['bar']);

        Feature::getAll([
            'foo' => [1, 2, 3],
            'bar' => [2],
        ]);
        $this->assertSame(5, $called['foo']);
        $this->assertSame(3, $called['bar']);

        Feature::for([1, 2, 3])->active('foo');
        Feature::for([2])->active('bar');
        $this->assertSame(5, $called['foo']);
        $this->assertSame(3, $called['bar']);
    }

    public function test_it_can_load_against_scope()
    {
        $called = ['foo' => 0, 'bar' => 0];
        Feature::define('foo', function ($scope) use (&$called) {
            $called['foo']++;
        });
        Feature::define('bar', function () use (&$called) {
            $called['bar']++;
        });

        $this->assertSame(0, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->load(['foo']);
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->active('foo');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->load('foo');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->active('foo');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->load('bar');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::for('loaded')->active('bar');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::for('noloaded')->active('bar');
        $this->assertSame(2, $called['foo']);
        $this->assertSame(2, $called['bar']);

        Feature::for([1, 2, 3])->load(['foo']);
        Feature::for(2)->load(['bar']);
        $this->assertSame(5, $called['foo']);
        $this->assertSame(3, $called['bar']);

        Feature::for([1, 2, 3])->active('foo');
        Feature::for([2])->active('bar');
        $this->assertSame(5, $called['foo']);
        $this->assertSame(3, $called['bar']);
    }

    public function test_it_can_load_missing_feature_state_into_memory()
    {
        $called = ['foo' => 0, 'bar' => 0];
        Feature::define('foo', function () use (&$called) {
            $called['foo']++;
        });
        Feature::define('bar', function () use (&$called) {
            $called['bar']++;
        });

        $this->assertSame(0, $called['foo']);

        Feature::loadMissing('foo');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::loadMissing('foo');
        $this->assertSame(0, $called['bar']);

        Feature::active('foo');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::active('bar');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::loadMissing(['bar']);
        $this->assertSame(1, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::getAll([
            'foo' => [1, 2, 3],
            'bar' => [2],
        ]);
        $this->assertSame(4, $called['foo']);
        $this->assertSame(2, $called['bar']);

        Feature::for([1, 2, 3])->active('foo');
        Feature::for([2])->active('bar');
        $this->assertSame(4, $called['foo']);
        $this->assertSame(2, $called['bar']);
    }

    public function test_it_throws_when_calling_value_with_multiple_scope()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('It is not possible to retrieve the values for multiple scopes.');

        Feature::for([1, 2, 3])->value('foo');
    }

    public function test_it_can_retrive_value_for_multiple_scopes()
    {
        Feature::define('foo', fn ($scope) => $scope);

        $values = Feature::for(1)->values(['foo']);

        $this->assertSame([
            'foo' => 1,
        ], $values);
    }

    public function test_it_can_retrive_value_for_multiple_scopes_and_features()
    {
        Feature::define('foo', fn ($scope) => $scope);
        Feature::define('bar', fn ($scope) => $scope * 2);

        $values = Feature::for(2)->values(['foo', 'bar']);

        $this->assertSame([
            'foo' => 2,
            'bar' => 4,
        ], $values);
    }

    public function test_it_may_register_shorthand_feature_values()
    {
        Feature::define('foo', 'value');

        $value = Feature::value('foo');

        $this->assertSame('value', $value);
    }

    public function test_it_can_use_lottery()
    {
        Feature::define('foo', Lottery::odds(1, 1));
        Feature::define('bar', Lottery::odds(0, 1));
        Feature::define('baz', fn () => Lottery::odds(0, 1));

        $this->assertTrue(Feature::value('foo'));
        $this->assertFalse(Feature::value('bar'));
        $this->assertFalse(Feature::value('baz'));
    }

    public function test_it_can_retrieve_registered_features()
    {
        Feature::define('foo', fn () => true);
        Feature::define('bar', fn () => false);
        Feature::define('baz', fn () => false);

        $registered = Feature::defined();

        $this->assertSame(['foo', 'bar', 'baz'], $registered);
    }

    public function test_it_can_clear_the_cache()
    {
        $called = 0;
        Feature::define('foo', function () use (&$called) {
            $called++;
        });

        Feature::active('foo');
        Feature::flushCache();
        Feature::active('foo');

        $this->assertSame(2, $called);
    }

    public function test_it_can_get_all_features()
    {
        Feature::define('foo', fn () => true);
        Feature::define('bar', fn () => false);

        $all = Feature::all();

        $this->assertSame([
            'foo' => true,
            'bar' => false,
        ], $all);
    }

    public function test_it_can_register_feature_via_class()
    {
        Feature::define(MyFeature::class);

        $value = Feature::for('shared')->value('my-feature');

        $this->assertSame('shared-123', $value);
    }

    public function test_it_can_register_features_via_class_without_name()
    {
        Feature::define(MyUnnamedFeature::class);

        $value = Feature::for('shared')->value(MyUnnamedFeature::class);

        $this->assertSame('shared-123', $value);
    }

    public function test_it_can_register_feature_via_class_with_resolve()
    {
        Feature::define(MyFeatureWithResolveMethod::class);

        $value = Feature::for('shared')->value(MyFeatureWithResolveMethod::class);

        $this->assertSame('shared-resolve-123', $value);
    }

    public function test_it_can_reevaluate_feature_state()
    {
        Feature::define('foo', fn () => false);
        $this->assertFalse(Feature::for('tim')->value('foo'));

        Feature::for('tim')->forget('foo');

        Feature::define('foo', fn () => true);
        $this->assertTrue(Feature::for('tim')->value('foo'));
    }

    public function test_it_can_customise_default_scope()
    {
        $scopes = [];
        Feature::define('foo', function ($scope) use (&$scopes) {
            $scopes[] = $scope;
        });

        Feature::active('foo');

        Auth::login($user = new User);
        Feature::active('foo');

        Feature::resolveScopeUsing(fn () => 'bar');
        Feature::active('foo');

        $this->assertSame([
            null,
            $user,
            'bar',
        ], $scopes);
    }

    public function test_it_doesnt_include_default_scope_when_null()
    {
        $scopes = [];
        Feature::define('foo', function ($scope) use (&$scopes) {
            $scopes[] = $scope;
        });

        Feature::resolveScopeUsing(fn () => null);
        Feature::active('foo');

        $this->assertSame([
            null,
        ], $scopes);
    }

    public function test_it_uses_default_scope_for_loading_with_string()
    {
        Feature::define('feature', fn () => false);
        Feature::for('tim')->activate('feature');

        $loaded = Feature::load('feature');
        $this->assertSame(['feature' => [false]], $loaded);

        Feature::resolveScopeUsing(fn () => 'tim');

        $loaded = Feature::load('feature');
        $this->assertSame(['feature' => [true]], $loaded);
    }

    public function test_retrieving_values_after_purging()
    {
        Feature::define('foo', false);

        Feature::for('tim')->activate('foo');

        $this->assertTrue(Feature::for('tim')->active('foo'));

        Feature::purge('foo');

        $this->assertFalse(Feature::for('tim')->active('foo'));
    }

    public function test_it_can_conditionally_execute_code_block_for_inactive_feature()
    {
        $active = $inactive = null;

        Feature::when('foo',
            function () use (&$active) {
                $active = true;
            },
            function () use (&$inactive) {
                $inactive = true;
            },
        );

        $this->assertNull($active);
        $this->assertTrue($inactive);
    }

    public function test_it_can_conditionally_execute_code_block_providing_closure_for_only_active()
    {
        $active = null;

        Feature::when('foo', function () use (&$active) {
            $active = true;
        });

        $this->assertNull($active);
    }

    public function test_it_can_conditionally_execute_code_block_for_active_feature()
    {
        $active = $inactive = null;
        Feature::activate('foo');

        Feature::when('foo',
            function () use (&$active) {
                $active = true;
            },
            function () use (&$inactive) {
                $inactive = true;
            },
        );

        $this->assertTrue($active);
        $this->assertNull($inactive);
    }

    public function test_it_receives_value_for_feature_in_conditional_code_execution()
    {
        $active = $inactive = null;
        Feature::activate('foo', ['hello' => 'world']);

        Feature::when('foo',
            function ($value) use (&$active) {
                $active = $value;
            },
            function () use (&$inactive) {
                $inactive = true;
            },
        );

        $this->assertSame(['hello' => 'world'], $active);
        $this->assertNull($inactive);
    }

    public function test_conditionally_executing_code_respects_scope()
    {
        $active = $inactive = null;
        Feature::for('tim')->activate('foo');

        Feature::when('foo',
            function () use (&$active) {
                $active = true;
            },
            function () use (&$inactive) {
                $inactive = true;
            },
        );

        $this->assertNull($active);
        $this->assertTrue($inactive);

        $active = $inactive = null;

        Feature::for('tim')->when('foo',
            function () use (&$active) {
                $active = true;
            },
            function () use (&$inactive) {
                $inactive = true;
            },
        );

        $this->assertTrue($active);
        $this->assertNull($inactive);
    }

    public function test_conditional_closures_receive_current_feature_interaction()
    {
        $active = $inactive = null;
        Feature::for('tim')->activate('foo', ['hello' => 'tim']);

        Feature::for('tim')->when('foo',
            function ($value, $feature) {
                $feature->deactivate('foo');
            },
            function () use (&$inactive) {
                $inactive = true;
            },
        );

        Feature::flushCache();
        $this->assertFalse(Feature::for('tim')->active('foo'));
        $this->assertNull($inactive);
    }

    public function test_it_can_set_for_all()
    {
        Feature::define('foo', fn () => false);

        Feature::for('tim')->activate('foo');
        Feature::for('taylor')->activate('foo');

        $this->assertTrue(Feature::for('tim')->value('foo'));
        $this->assertTrue(Feature::for('taylor')->value('foo'));
        $this->assertTrue(Feature::getDriver()->get('foo', 'tim'));
        $this->assertTrue(Feature::getDriver()->get('foo', 'taylor'));

        Feature::deactivateForEveryone('foo');

        $this->assertFalse(Feature::for('tim')->value('foo'));
        $this->assertFalse(Feature::for('taylor')->value('foo'));
        $this->assertFalse(Feature::getDriver()->get('foo', 'tim'));
        $this->assertFalse(Feature::getDriver()->get('foo', 'taylor'));

        Feature::activateForEveryone('foo');

        $this->assertTrue(Feature::for('tim')->value('foo'));
        $this->assertTrue(Feature::for('taylor')->value('foo'));
        $this->assertTrue(Feature::getDriver()->get('foo', 'tim'));
        $this->assertTrue(Feature::getDriver()->get('foo', 'taylor'));
    }

    public function test_it_can_auto_register_feature_classes()
    {
        Feature::define('marketing-design', 'marketing-design-value');
        Feature::discover('Workbench\\App\\Features', workbench_path('app/Features'));

        $all = Feature::all();

        $this->assertSame([
            'marketing-design' => 'marketing-design-value',
            'Workbench\\App\\Features\\NewApi' => 'new-api-value',
        ], $all);
    }

    public function test_it_accepts_null_scope_for_parameterless_feature()
    {
        Feature::define('foo', fn () => true);

        $result = Feature::for(null)->active('foo');
        $this->assertTrue($result);

        $result = Feature::for(new User)->active('foo');
        $this->assertTrue($result);

        $result = Feature::for(null)->active(MyFeatureWithNoScope::class);
        $this->assertTrue($result);

        $result = Feature::for(new User)->active(MyFeatureWithNoScope::class);
        $this->assertTrue($result);
    }

    public function test_it_accepts_null_scope_for_untyped_feature()
    {
        Feature::define('foo', fn ($user) => true);

        $result = Feature::for(null)->active('foo');
        $this->assertTrue($result);

        $result = Feature::for(new User)->active('foo');
        $this->assertTrue($result);

        $result = Feature::for(null)->active(MyFeatureWithUntypedScope::class);
        $this->assertTrue($result);

        $result = Feature::for(new User)->active(MyFeatureWithUntypedScope::class);
        $this->assertTrue($result);
    }

    public function test_it_accepts_null_scope_for_mixed_typed_feature()
    {
        Feature::define('foo', fn (mixed $user) => true);

        $result = Feature::for(null)->active('foo');
        $this->assertTrue($result);

        $result = Feature::for(new User)->active('foo');
        $this->assertTrue($result);

        $result = Feature::for(null)->active(MyFeatureWithMixedScope::class);
        $this->assertTrue($result);

        $result = Feature::for(new User)->active(MyFeatureWithMixedScope::class);
        $this->assertTrue($result);
    }

    public function test_it_accepts_null_scope_for_nullable_typed_feature()
    {
        Feature::define('foo', fn (?User $user) => true);

        $result = Feature::for(null)->active('foo');
        $this->assertTrue($result);

        $result = Feature::for(new User)->active('foo');
        $this->assertTrue($result);

        $result = Feature::for(null)->active(MyFeatureWithNullableScope::class);
        $this->assertTrue($result);

        $result = Feature::for(new User)->active(MyFeatureWithNullableScope::class);
        $this->assertTrue($result);
    }

    public function test_it_gracefully_handles_null_scope_for_non_nullable_feature()
    {
        Event::fake([UnexpectedNullScopeEncountered::class]);
        Feature::define('foo', function (User $user) {
            return true;
        });

        $result = Feature::for(null)->active('foo');
        $this->assertFalse($result);
        Event::assertDispatchedTimes(UnexpectedNullScopeEncountered::class, 1);
        Event::assertDispatched(function (UnexpectedNullScopeEncountered $event) {
            return $event->feature === 'foo';
        });

        $result = Feature::for(new User)->active('foo');
        $this->assertTrue($result);
        Event::assertDispatchedTimes(UnexpectedNullScopeEncountered::class, 1);

        $result = Feature::for(null)->active(MyFeatureWithUserScope::class);
        $this->assertFalse($result);
        Event::assertDispatchedTimes(UnexpectedNullScopeEncountered::class, 2);
        Event::assertDispatched(function (UnexpectedNullScopeEncountered $event) {
            return $event->feature === MyFeatureWithUserScope::class;
        });

        $result = Feature::for(new User)->active(MyFeatureWithUserScope::class);
        $this->assertTrue($result);
        Event::assertDispatchedTimes(UnexpectedNullScopeEncountered::class, 2);
    }

    public function test_it_does_not_interpret_array_as_callable()
    {
        $class = new class
        {
            public function foo()
            {
                return 'xxxx';
            }
        };
        Feature::define('foo', [$class, 'foo']);

        $result = Feature::value('foo');

        $this->assertSame([$class, 'foo'], $result);
    }

    public function test_feature_class_dependencies_do_not_go_stale()
    {
        $createContainer = function () {
            $container = new Container;
            $container->singleton(FeatureDependency::class);
            $container->instance('events', new class
            {
                public function dispatch()
                {
                    //
                }
            });

            return $container;
        };
        $first = $createContainer();
        $firstDependency = null;
        $second = $createContainer();
        $secondDependency = null;

        Feature::define(MyFeatureWithDependency::class);

        Feature::store()->setContainer($first);
        $first->resolving(MyFeatureWithDependency::class, function (MyFeatureWithDependency $feature) use (&$firstDependency) {
            $firstDependency = $feature->dependency;
        });

        Feature::active(MyFeatureWithDependency::class);
        Feature::flushCache();

        Feature::store()->setContainer($second);
        $second->resolving(MyFeatureWithDependency::class, function (MyFeatureWithDependency $feature) use (&$secondDependency) {
            $secondDependency = $feature->dependency;
        });

        Feature::active(MyFeatureWithDependency::class);

        $this->assertInstanceOf(FeatureDependency::class, $first[FeatureDependency::class]);
        $this->assertInstanceOf(FeatureDependency::class, $second[FeatureDependency::class]);
        $this->assertNotSame($first[FeatureDependency::class], $second[FeatureDependency::class]);
        $this->assertSame($first[FeatureDependency::class], $firstDependency);
        $this->assertSame($second[FeatureDependency::class], $secondDependency);
    }

    public function test_it_dispatches_events_when_purging_features()
    {
        Event::fake([FeaturesPurged::class]);

        Feature::define('foo', fn () => true);
        Feature::define('bar', fn () => true);

        Feature::purge(['foo', 'bar', 'baz']);

        Event::assertDispatchedTimes(FeaturesPurged::class, 1);
        Event::assertDispatched(function (FeaturesPurged $event) {
            return $event->features === ['foo', 'bar', 'baz'];
        });
    }

    public function test_it_dispatches_events_when_purging_all_features()
    {
        Event::fake([AllFeaturesPurged::class]);

        Feature::define('foo', fn () => true);
        Feature::define('bar', fn () => true);

        Feature::purge();

        Event::assertDispatchedTimes(AllFeaturesPurged::class, 1);
    }

    public function test_it_dispatches_events_when_updating_a_scoped_feature()
    {
        Event::fake([FeatureUpdated::class]);

        Feature::define('foo', fn () => false);

        Feature::for('tim')->activate('foo');

        Event::assertDispatchedTimes(FeatureUpdated::class, 1);
        Event::assertDispatched(function (FeatureUpdated $event) {
            return $event->feature === 'foo'
                && $event->scope === 'tim'
                && $event->value === true;
        });
    }

    public function test_it_dispatches_events_when_updating_a_feature_for_all_scopes()
    {
        Event::fake([FeatureUpdatedForAllScopes::class]);

        Feature::define('foo', fn () => false);

        Feature::activateForEveryone('foo', true);

        Event::assertDispatchedTimes(FeatureUpdatedForAllScopes::class, 1);
        Event::assertDispatched(function (FeatureUpdatedForAllScopes $event) {
            return $event->feature === 'foo'
                && $event->value === true;
        });
    }

    public function test_it_dispatches_events_when_deleting_a_feature_value()
    {
        Event::fake([FeatureDeleted::class]);

        Feature::define('foo', fn () => false);

        Feature::for('tim')->forget('foo');

        Event::assertDispatchedTimes(FeatureDeleted::class, 1);
        Event::assertDispatched(function (FeatureDeleted $event) {
            return $event->feature === 'foo'
                && $event->scope === 'tim';
        });
    }

    public function test_it_caches_by_identifier_for_feature_scopable_objects()
    {
        $factory = fn () => new class implements FeatureScopeable
        {
            public function toFeatureIdentifier(string $driver): mixed
            {
                return 'foo';
            }
        };

        Feature::for($object = $factory())->activate('foo');

        $this->assertTrue(Feature::for($object)->active('foo'));
        $this->assertTrue(Feature::for($factory())->active('foo'));
    }

    public function test_it_caches_by_identification_for_other_objects()
    {
        $factory = fn ($name) => new User(['id' => 123, 'name' => $name]);

        Feature::for($object = $factory('Tim'))->activate('foo');

        $this->assertTrue(Feature::for($object)->active('foo'));
        $this->assertTrue(Feature::for($factory('Taylor'))->active('foo'));
    }

    public function test_caching_of_features(): void
    {
        $factory = fn () => new User(['id' => 123]);
        $user1 = $factory();
        $user2 = $factory();

        Feature::for($user1)->activate('myflag', 2);
        $this->assertEquals(2, Feature::for($user1)->active('myflag'));

        Feature::for($user1)->activate('myflag', 3);
        $this->assertEquals(3, Feature::for($user1)->active('myflag'));

        Feature::for($user2)->activate('myflag', 4);

        $this->assertEquals(4, Feature::for($user1)->value('myflag'));
        $this->assertEquals(4, Feature::for($user2)->value('myflag'));
    }

    public function test_it_can_list_stored_features()
    {
        Feature::define('foo', fn () => true);
        Feature::define('bar', fn () => true);

        Feature::for('Tim')->active('bar');

        $this->assertSame(Feature::stored(), ['bar']);
    }

    public function test_it_can_resolve_feature_instance()
    {
        Feature::define(MyFeature::class);
        $this->assertInstanceOf(MyFeature::class, Feature::instance('my-feature'));
        $this->assertInstanceOf(MyFeature::class, Feature::instance(MyFeature::class));

        Feature::define(MyUnnamedFeature::class);
        $this->assertInstanceOf(MyUnnamedFeature::class, Feature::instance(MyUnnamedFeature::class));

        Feature::define('closure-based-feature', $closure = fn () => true);
        $this->assertSame($closure, Feature::instance('closure-based-feature'));

        Feature::define('value-based-feature', '123');
        $feature = Feature::instance('value-based-feature');
        $this->assertInstanceOf(Closure::class, $feature);
        $this->assertSame('123', $feature());

        Feature::define('lottery-based-feature', Lottery::odds(1, 1)->winner(fn () => '345'));
        $feature = Feature::instance('lottery-based-feature');
        $this->assertInstanceOf(Lottery::class, $feature);
        $this->assertSame('345', $feature());
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

class MyFeatureWithResolveMethod
{
    public function resolve($scope)
    {
        return "{$scope}-resolve-123";
    }
}

class MyFeatureWithUserScope
{
    public function resolve(User $user)
    {
        return true;
    }
}

class MyFeatureWithNoScope
{
    public function resolve()
    {
        return true;
    }
}

class MyFeatureWithUntypedScope
{
    public function resolve($scope)
    {
        return true;
    }
}

class MyFeatureWithMixedScope
{
    public function resolve(mixed $scope)
    {
        return true;
    }
}

class MyFeatureWithNullableScope
{
    public function resolve(?User $scope)
    {
        return true;
    }
}

class MyFeatureWithDependency
{
    public function __construct(public FeatureDependency $dependency)
    {
        //
    }

    public function resolve()
    {
        return true;
    }
}

class FeatureDependency
{
    //
}
