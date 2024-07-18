<?php

namespace Tests\Feature;

use Exception;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Pennant\Contracts\FeatureScopeable;
use Laravel\Pennant\Events\AllFeaturesPurged;
use Laravel\Pennant\Events\DynamicallyRegisteringFeatureClass;
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
use Workbench\Database\Factories\UserFactory;

use function Orchestra\Testbench\workbench_path;

class DatabaseDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pennant.default', 'database');

        DB::enableQueryLog();
    }

    public function test_it_defaults_to_false_for_unknown_values()
    {
        $result = Feature::active('foo');

        $this->assertFalse($result);

        $this->assertCount(1, DB::getQueryLog());
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

        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_it_can_register_default_boolean_values()
    {
        Feature::define('true', fn () => true);
        Feature::define('false', fn () => false);

        $true = Feature::active('true');
        $false = Feature::active('false');

        $this->assertTrue($true);
        $this->assertFalse($false);

        $this->assertCount(4, DB::getQueryLog());
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

        $this->assertCount(3, DB::getQueryLog());
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

        $this->assertCount(2, DB::getQueryLog());
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

        $this->assertCount(12, DB::getQueryLog());
    }

    public function test_it_can_programatically_activate_and_deativate_features()
    {
        Feature::activate('foo');
        $this->assertTrue(Feature::active('foo'));

        Feature::deactivate('foo');
        $this->assertFalse(Feature::active('foo'));

        Feature::activate('foo');
        $this->assertTrue(Feature::active('foo'));

        $this->assertCount(3, DB::getQueryLog());
    }

    public function test_it_dispatches_events_when_checking_known_features()
    {
        Event::fake([FeatureResolved::class]);
        Feature::define('foo', fn () => true);

        Feature::active('foo');
        Feature::active('foo');

        Event::assertDispatchedTimes(FeatureResolved::class, 1);
        Event::assertDispatched(function (FeatureResolved $event) {
            return $event->feature === 'foo' && $event->scope === null;
        });

        $this->assertCount(2, DB::getQueryLog());
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

        $this->assertCount(7, DB::getQueryLog());
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

        $this->assertCount(4, DB::getQueryLog());
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

        $this->assertCount(6, DB::getQueryLog());
    }

    public function test_it_can_activate_and_deactivate_features_with_scope()
    {
        $first = new User(['id' => 1]);
        $second = new User(['id' => 2]);

        Feature::for($first)->activate('foo');

        $this->assertFalse(Feature::active('foo'));
        $this->assertTrue(Feature::for($first)->active('foo'));
        $this->assertFalse(Feature::for($second)->active('foo'));

        $this->assertCount(3, DB::getQueryLog());
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

        $this->assertCount(4, DB::getQueryLog());
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

        $this->assertCount(8, DB::getQueryLog());
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

        $this->assertCount(6, DB::getQueryLog());
    }

    public function test_null_is_same_as_global()
    {
        Feature::activate('foo');

        $this->assertTrue(Feature::for(null)->active('foo'));

        $this->assertCount(1, DB::getQueryLog());
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

        $this->assertCount(4, DB::getQueryLog());
    }

    public function test_scope_can_be_strings_like_email_addresses()
    {
        Feature::for('tim@laravel.com')->activate('foo');

        $this->assertFalse(Feature::for('james@laravel.com')->active('foo'));
        $this->assertTrue(Feature::for('tim@laravel.com')->active('foo'));

        $this->assertCount(2, DB::getQueryLog());
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

        $this->assertCount(2, DB::getQueryLog());
    }

    public function test_it_serializes_eloquent_models()
    {
        Feature::for(UserFactory::new()->create())->activate('foo');

        $scope = DB::table('features')->value('scope');

        $this->assertStringContainsString('Workbench\App\Models\User|1', $scope);
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
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::active('foo');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::load('bar');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::active('bar');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::load(['bar']);
        $this->assertSame(1, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::load(['foo', 'bar']);
        $this->assertSame(1, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::allAreActive(['foo', 'bar']);
        $this->assertSame(1, $called['foo']);
        $this->assertSame(1, $called['bar']);

        $this->assertCount(7, DB::getQueryLog());
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

        Feature::for('loaded')->load('foo');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->active('foo');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->load(['foo']);
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->active('foo');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->load(['bar']);
        $this->assertSame(1, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::for('loaded')->active('bar');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::for('noloaded')->active('bar');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(2, $called['bar']);

        Feature::getAll([
            'foo' => [1, 2, 3],
            'bar' => [2],
        ]);
        $this->assertSame(4, $called['foo']);
        $this->assertSame(3, $called['bar']);

        Feature::for([1, 2, 3])->active('foo');
        Feature::for([2])->active('bar');
        $this->assertSame(4, $called['foo']);
        $this->assertSame(3, $called['bar']);

        $this->assertCount(9, DB::getQueryLog());
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
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->active('foo');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->load('bar');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::for('loaded')->active('bar');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::for('noloaded')->active('bar');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(2, $called['bar']);

        Feature::for([1, 2, 3])->load(['foo']);
        Feature::for(2)->load(['bar']);
        $this->assertSame(4, $called['foo']);
        $this->assertSame(3, $called['bar']);

        Feature::for([1, 2, 3])->active('foo');
        Feature::for([2])->active('bar');
        $this->assertSame(4, $called['foo']);
        $this->assertSame(3, $called['bar']);

        $this->assertCount(11, DB::getQueryLog());
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

        Feature::getAllMissing([
            'foo' => [1, 2, 3],
            'bar' => [2],
        ]);
        $this->assertSame(4, $called['foo']);
        $this->assertSame(2, $called['bar']);

        Feature::for([1, 2, 3])->active('foo');
        Feature::for([2])->active('bar');
        $this->assertSame(4, $called['foo']);
        $this->assertSame(2, $called['bar']);

        $this->assertCount(6, DB::getQueryLog());
    }

    public function test_it_does_not_hit_db_when_features_are_empty()
    {
        Feature::load([]);
        Feature::loadMissing([]);
        $this->assertCount(0, DB::getQueryLog());

        Feature::active('foo');
        Feature::loadMissing(['foo']);
        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_unknown_features_are_no_persisted_when_loading()
    {
        Event::fake([UnknownFeatureResolved::class]);
        Feature::load(['foo', 'bar']);

        Event::assertDispatchedTimes(UnknownFeatureResolved::class, 2);
        $this->assertCount(1, DB::getQueryLog());
        $this->assertCount(0, DB::table('features')->get());
    }

    public function test_missing_results_are_inserted_on_load()
    {
        Feature::define('foo', function () {
            return 1;
        });
        Feature::define('bar', function () {
            return 2;
        });

        Feature::for('taylor@laravel.com')->activate('foo', 99);
        Feature::for(['tim@laravel.com', 'jess@laravel.com', 'taylor@laravel.com'])->load(['foo', 'bar']);

        $this->assertCount(3, DB::getQueryLog());
        $this->assertDatabaseHas('features', [
            'name' => 'foo',
            'scope' => 'tim@laravel.com',
            'value' => '1',
        ]);
        $this->assertDatabaseHas('features', [
            'name' => 'foo',
            'scope' => 'jess@laravel.com',
            'value' => '1',
        ]);
        $this->assertDatabaseHas('features', [
            'name' => 'foo',
            'scope' => 'taylor@laravel.com',
            'value' => '99',
        ]);
        $this->assertDatabaseHas('features', [
            'name' => 'bar',
            'scope' => 'tim@laravel.com',
            'value' => '2',
        ]);
        $this->assertDatabaseHas('features', [
            'name' => 'bar',
            'scope' => 'jess@laravel.com',
            'value' => '2',
        ]);
        $this->assertDatabaseHas('features', [
            'name' => 'bar',
            'scope' => 'taylor@laravel.com',
            'value' => '2',
        ]);
    }

    public function test_it_can_retrieve_registered_features()
    {
        Feature::define('foo', fn () => true);
        Feature::define('bar', fn () => false);
        Feature::define('baz', fn () => false);

        $registered = Feature::defined();

        $this->assertSame(['foo', 'bar', 'baz'], $registered);
        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_it_can_clear_the_cache()
    {
        Feature::define('foo', fn () => true);

        Feature::active('foo');
        Feature::flushCache();
        Feature::active('foo');

        $this->assertCount(3, DB::getQueryLog());
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

    public function test_it_can_reevaluate_feature_state()
    {
        Feature::define('foo', fn () => false);
        $this->assertFalse(Feature::for('tim')->value('foo'));

        Feature::for('tim')->forget('foo');

        Feature::define('foo', fn () => true);
        $this->assertTrue(Feature::for('tim')->value('foo'));
    }

    public function test_it_can_purge_flags()
    {
        Feature::define('foo', true);
        Feature::define('bar', false);

        Feature::for('tim')->active('foo');
        Feature::for('taylor')->active('foo');
        Feature::for('taylor')->active('bar');

        $this->assertSame(3, DB::table('features')->count());

        Feature::purge('foo');

        $this->assertSame(1, DB::table('features')->count());

        Feature::purge('bar');

        $this->assertSame(0, DB::table('features')->count());
    }

    public function test_it_can_purge_multiple_flags_at_once()
    {
        Feature::define('foo', true);
        Feature::define('bar', false);
        Feature::define('baz', false);

        Feature::for('tim')->active('foo');
        Feature::for('tim')->active('foo');
        Feature::for('taylor')->active('foo');
        Feature::for('taylor')->active('bar');
        Feature::for('taylor')->active('baz');

        $this->assertSame(4, DB::table('features')->count());

        Feature::purge(['foo', 'bar']);

        $this->assertSame(1, DB::table('features')->count());

        Feature::purge(['baz']);

        $this->assertSame(0, DB::table('features')->count());
    }

    public function test_retrieving_values_after_purging()
    {
        Feature::define('foo', false);

        Feature::for('tim')->activate('foo');

        $this->assertTrue(Feature::for('tim')->active('foo'));
        $this->assertSame(1, DB::table('features')->count());

        Feature::purge('foo');

        $this->assertSame(0, DB::table('features')->count());

        $this->assertFalse(Feature::for('tim')->active('foo'));
    }

    public function test_it_can_purge_all_feature_flags()
    {
        Feature::define('foo', true);
        Feature::define('bar', false);

        Feature::for('tim')->active('foo');
        Feature::for('taylor')->active('foo');
        Feature::for('taylor')->active('bar');

        $this->assertSame(3, DB::table('features')->count());

        Feature::purge();

        $this->assertSame(0, DB::table('features')->count());
    }

    public function test_it_can_customise_default_scope()
    {
        $scopes = [];
        Feature::define('foo', function ($scope) use (&$scopes) {
            $scopes[] = $scope;
        });

        Feature::active('foo');

        Auth::login($user = new User());
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

    public function test_it_does_not_store_unknown_features()
    {
        Event::fake([UnknownFeatureResolved::class]);

        Feature::active('foo');
        Feature::active('foo');

        $this->assertSame(0, DB::table('features')->count());
    }

    public function test_it_can_use_unregistered_class_features()
    {
        Event::fake([DynamicallyRegisteringFeatureClass::class]);

        Feature::value(UnregisteredFeature::class);
        $value = Feature::value(UnregisteredFeature::class);
        $registered = Feature::defined();

        $this->assertSame('unregistered-value', $value);
        $this->assertSame([UnregisteredFeature::class], $registered);
        Event::assertDispatched(DynamicallyRegisteringFeatureClass::class, 1);
        Event::assertDispatched(function (DynamicallyRegisteringFeatureClass $event) {
            $this->assertSame($event->feature, UnregisteredFeature::class);

            return true;
        });
    }

    public function test_it_can_use_unregistered_class_features_with_resolve_method()
    {
        Event::fake([DynamicallyRegisteringFeatureClass::class]);

        Feature::value(UnregisteredFeatureWithResolve::class);
        $value = Feature::value(UnregisteredFeatureWithResolve::class);
        $registered = Feature::defined();

        $this->assertSame('unregistered-value.resolve', $value);
        $this->assertSame([UnregisteredFeatureWithResolve::class], $registered);
        Event::assertDispatched(DynamicallyRegisteringFeatureClass::class, 1);
        Event::assertDispatched(function (DynamicallyRegisteringFeatureClass $event) {
            $this->assertSame($event->feature, UnregisteredFeatureWithResolve::class);

            return true;
        });
    }

    public function test_it_can_use_unregistered_class_features_with_name_property()
    {
        Event::fake([DynamicallyRegisteringFeatureClass::class]);

        Feature::value(UnregisteredFeatureWithName::class);
        $value = Feature::value(UnregisteredFeatureWithName::class);
        $registered = Feature::defined();

        $this->assertSame('unregistered-value', $value);
        $this->assertSame(['feature-name'], $registered);
        Event::assertDispatched(DynamicallyRegisteringFeatureClass::class, 1);
        Event::assertDispatched(function (DynamicallyRegisteringFeatureClass $event) {
            $this->assertSame($event->feature, UnregisteredFeatureWithName::class);

            return true;
        });
    }

    public function test_it_can_delete_unregistered_class_features_with_name_property()
    {
        Event::fake([DynamicallyRegisteringFeatureClass::class]);

        Feature::value(UnregisteredFeatureWithName::class);
        $this->assertSame(1, DB::table('features')->where('name', 'feature-name')->count());

        Feature::forgetDrivers();

        Feature::forget(UnregisteredFeatureWithName::class);
        $this->assertSame(0, DB::table('features')->where('name', 'feature-name')->count());

        Event::assertDispatched(DynamicallyRegisteringFeatureClass::class, 2);
        Event::assertDispatched(function (DynamicallyRegisteringFeatureClass $event) {
            $this->assertSame($event->feature, UnregisteredFeatureWithName::class);

            return true;
        });
    }

    public function test_it_can_activate_unregistered_class_features_with_name_property()
    {
        Event::fake([DynamicallyRegisteringFeatureClass::class]);

        Feature::activate(UnregisteredFeatureWithName::class, 'expected-value');
        $this->assertSame(1, DB::table('features')->where('name', 'feature-name')->where('value', '"expected-value"')->count());

        Feature::forgetDrivers();

        Feature::forget(UnregisteredFeatureWithName::class);
        $this->assertSame(0, DB::table('features')->where('name', 'feature-name')->where('value', '"expected-value"')->count());

        Event::assertDispatched(DynamicallyRegisteringFeatureClass::class, 2);
        Event::assertDispatched(function (DynamicallyRegisteringFeatureClass $event) {
            $this->assertSame($event->feature, UnregisteredFeatureWithName::class);

            return true;
        });
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

    public function test_it_handles_multiscope_checks()
    {
        Feature::define('foo', false);

        $result = Feature::for(['tim', 'taylor'])->allAreInactive(['foo', 'bar']);
        $this->assertTrue($result);

        $result = Feature::for(['tim', 'taylor'])->someAreInactive(['foo', 'bar']);
        $this->assertTrue($result);

        $result = Feature::for(['tim', 'taylor'])->someAreActive(['foo', 'bar']);
        $this->assertFalse($result);

        $result = Feature::for(['tim', 'taylor'])->allAreActive(['foo', 'bar']);
        $this->assertFalse($result);

        Feature::for('tim')->activate('foo');

        $result = Feature::for(['tim', 'taylor'])->allAreInactive(['foo', 'bar']);
        $this->assertFalse($result);

        $result = Feature::for(['tim', 'taylor'])->someAreInactive(['foo', 'bar']);
        $this->assertTrue($result);

        $result = Feature::for(['tim', 'taylor'])->someAreActive(['foo', 'bar']);
        $this->assertFalse($result);

        $result = Feature::for(['tim', 'taylor'])->allAreActive(['foo', 'bar']);
        $this->assertFalse($result);

        Feature::for('taylor')->activate('foo');

        $result = Feature::for(['tim', 'taylor'])->allAreInactive(['foo', 'bar']);
        $this->assertFalse($result);

        $result = Feature::for(['tim', 'taylor'])->someAreInactive(['foo', 'bar']);
        $this->assertTrue($result);

        $result = Feature::for(['tim', 'taylor'])->someAreActive(['foo', 'bar']);
        $this->assertTrue($result);

        $result = Feature::for(['tim', 'taylor'])->allAreActive(['foo', 'bar']);
        $this->assertFalse($result);

        Feature::for('tim')->activate('bar');

        $result = Feature::for(['tim', 'taylor'])->allAreInactive(['foo', 'bar']);
        $this->assertFalse($result);

        $result = Feature::for(['tim', 'taylor'])->someAreInactive(['foo', 'bar']);
        $this->assertFalse($result);

        $result = Feature::for(['tim', 'taylor'])->someAreActive(['foo', 'bar']);
        $this->assertTrue($result);

        $result = Feature::for(['tim', 'taylor'])->allAreActive(['foo', 'bar']);
        $this->assertFalse($result);

        Feature::for('taylor')->activate('bar');

        $result = Feature::for(['tim', 'taylor'])->allAreInactive(['foo', 'bar']);
        $this->assertFalse($result);

        $result = Feature::for(['tim', 'taylor'])->someAreInactive(['foo', 'bar']);
        $this->assertFalse($result);

        $result = Feature::for(['tim', 'taylor'])->someAreActive(['foo', 'bar']);
        $this->assertTrue($result);

        $result = Feature::for(['tim', 'taylor'])->allAreActive(['foo', 'bar']);
        $this->assertTrue($result);
    }

    public function test_bulk_insert_adds_timestamps()
    {
        Feature::define('foo', true);

        Feature::values(['foo']);
        $record = DB::table('features')->first();

        $this->assertNotNull($record->updated_at);
        $this->assertNotNull($record->created_at);
    }

    public function test_stores_may_be_configured()
    {
        $this->app['config']->set('database.connections.foo_connection', $this->app['config']->get('database.connections.testing'));
        $this->app['config']->set('database.connections.bar_connection', $this->app['config']->get('database.connections.testing'));
        $this->app['config']->set('pennant.stores.foo', [
            'driver' => 'database',
            'connection' => 'foo_connection',
            'table' => 'foo_features',
        ]);
        $this->app['config']->set('pennant.stores.bar', [
            'driver' => 'database',
            'connection' => 'bar_connection',
            'table' => 'bar_features',
        ]);
        $connectionResolver = function () {
            return $this->newQuery()->connection->getName();
        };
        $tableResolver = function () {
            return $this->newQuery()->from;
        };

        $driver = Feature::store('foo')->getDriver();
        $this->assertSame('foo_connection', $connectionResolver->bindTo($driver, $driver)());
        $this->assertSame('foo_features', $tableResolver->bindTo($driver, $driver)());

        $driver = Feature::store('bar')->getDriver();
        $this->assertSame('bar_connection', $connectionResolver->bindTo($driver, $driver)());
        $this->assertSame('bar_features', $tableResolver->bindTo($driver, $driver)());
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

    public function test_it_can_use_eloquent_morph_map_for_scope_serialization()
    {
        $model = new User(['id' => 6]);
        Relation::morphMap([
            'user-morph' => $model::class,
        ]);
        $scopes = [];
        Feature::define('foo', fn () => true);

        Feature::useMorphMap();
        Feature::for($model)->active('foo');

        $this->assertDatabaseHas('features', [
            'name' => 'foo',
            'scope' => 'user-morph|6',
            'value' => 'true',
        ]);
    }

    public function test_it_respects_updated_connection_configuration()
    {
        Feature::flushCache();
        Config::set('pennant.stores.database.connection', null);
        Feature::store('database')->active('feature-name');

        Feature::flushCache();
        Config::set('pennant.stores.database.connection', 'xxxx');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Database connection [xxxx] not configured.');
        Feature::store('database')->active('feature-name');
    }

    public function test_it_can_list_stored_features()
    {
        Feature::define('foo', fn () => true);
        Feature::define('bar', fn () => true);

        Feature::for('tim')->active('bar');
        DB::table('features')->insert([
            'name' => 'baz',
            'scope' => 'Tim',
            'value' => true,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $this->assertSame(Feature::stored(), ['bar', 'baz']);
    }

    public function test_it_retries_3_times_and_then_fails()
    {
        Feature::define('foo', fn () => true);
        Feature::define('bar', fn () => true);
        $insertAttempts = 0;
        DB::listen(function (QueryExecuted $event) use (&$insertAttempts) {
            if (Str::startsWith($event->sql, 'insert into "features"')) {
                $insertAttempts++;
                DB::table('features')->delete();
                throw new UniqueConstraintViolationException($event->connectionName, $event->sql, $event->bindings, new RuntimeException());
            }
        });

        try {
            Feature::for('tim')->loadMissing(['foo', 'bar']);
            $this->fail('Should have failed.');
        } catch (Exception $e) {
            $this->assertInstanceOf(RuntimeException::class, $e);
            $this->assertSame('Unable to insert feature values into the database.', $e->getMessage());
            $this->assertInstanceOf(UniqueConstraintViolationException::class, $e->getPrevious());
        }

        $this->assertSame(3, $insertAttempts);
    }

    public function test_it_only_retries_on_conflicts()
    {
        Feature::define('foo', fn () => true);
        Feature::define('bar', fn () => true);
        $insertAttempts = 0;
        DB::listen(function (QueryExecuted $event) use (&$insertAttempts) {
            if (Str::startsWith($event->sql, 'insert into "features"')) {
                $insertAttempts++;
                throw new UniqueConstraintViolationException($event->connectionName, $event->sql, $event->bindings, new RuntimeException());
            }
        });

        Feature::for('tim')->loadMissing(['foo', 'bar']);

        $this->assertSame(1, $insertAttempts);
    }

    public function test_it_retries_2_times_and_then_fails_for_individual_queries()
    {
        Feature::define('foo', fn () => true);
        Feature::define('bar', fn () => true);
        $insertAttempts = 0;
        DB::listen(function (QueryExecuted $event) use (&$insertAttempts) {
            if (Str::startsWith($event->sql, 'insert into "features"')) {
                $insertAttempts++;
                DB::table('features')->delete();
                throw new UniqueConstraintViolationException($event->connectionName, $event->sql, $event->bindings, new RuntimeException());
            }
        });

        try {
            Feature::driver('database')->get('foo', 'tim');
            $this->fail('Should have failed.');
        } catch (Exception $e) {
            $this->assertInstanceOf(RuntimeException::class, $e);
            $this->assertSame('Unable to insert feature value into the database.', $e->getMessage());
            $this->assertInstanceOf(UniqueConstraintViolationException::class, $e->getPrevious());
        }

        $this->assertSame(2, $insertAttempts);
    }

    public function test_it_only_retries_on_conflicts_for_individual_queries()
    {
        Feature::define('foo', fn () => true);
        Feature::define('bar', fn () => true);
        $insertAttempts = 0;
        DB::listen(function (QueryExecuted $event) use (&$insertAttempts) {
            if (Str::startsWith($event->sql, 'insert into "features"')) {
                $insertAttempts++;
                throw new UniqueConstraintViolationException($event->connectionName, $event->sql, $event->bindings, new RuntimeException());
            }
        });

        Feature::driver('database')->get('foo', 'tim');

        $this->assertSame(1, $insertAttempts);
    }

    public function test_it_can_intercept_feature_values_using_before_hook()
    {
        $queries = 0;
        FeatureWithBeforeHook::$before = fn ($scope) => 'before';
        Feature::define(FeatureWithBeforeHook::class);
        Feature::activate(FeatureWithBeforeHook::class, 'stored-value');
        Feature::flushCache();
        DB::listen(function (QueryExecuted $event) use (&$queries) {
            $queries++;
        });

        $value = Feature::for(null)->value(FeatureWithBeforeHook::class);

        $this->assertSame('before', $value);
        $this->assertSame(0, $queries);
    }

    public function test_it_can_merges_before_with_resolved_features()
    {
        $queries = 0;
        DB::listen(function (QueryExecuted $event) use (&$queries) {
            $queries++;
        });
        FeatureWithBeforeHook::$before = fn ($scope) => ['before' => 'value'];
        Feature::define('foo', 'bar');
        Feature::define(FeatureWithBeforeHook::class);

        $values = Feature::for('user:1')->all();

        $this->assertSame([
            'foo' => 'bar',
            'Tests\Feature\FeatureWithBeforeHook' => ['before' => 'value'],
        ], $values);
        $this->assertSame(2, $queries);
    }

    public function test_it_can_get_features_with_before_hook()
    {
        $queries = 0;
        DB::listen(function (QueryExecuted $event) use (&$queries) {
            $queries++;
        });
        FeatureWithBeforeHook::$before = fn ($scope) => ['before' => 'value'];

        $value = Feature::get(FeatureWithBeforeHook::class, null);

        $this->assertSame(['before' => 'value'], $value);
        $this->assertSame(0, $queries);
    }

    public function test_it_handles_null_scope_for_before_hook()
    {
        Event::fake(UnexpectedNullScopeEncountered::class);
        $queries = 0;
        DB::listen(function (QueryExecuted $event) use (&$queries) {
            $queries++;
        });
        FeatureWithTypedBeforeHook::$before = fn ($scope) => 'before-value';
        Feature::define(FeatureWithTypedBeforeHook::class);

        $values = Feature::for(null)->value(FeatureWithTypedBeforeHook::class);

        $this->assertSame('feature-value', $values);
        $this->assertSame(2, $queries);

        Feature::flushCache();

        $value = Feature::get(FeatureWithTypedBeforeHook::class, null);

        $this->assertSame('feature-value', $values);
        $this->assertSame(3, $queries);
        Event::assertDispatchedTimes(UnexpectedNullScopeEncountered::class, 2);
    }

    public function test_it_maintains_scope_feature_keys()
    {
        $count = 0;
        FeatureWithBeforeHook::$before = function ($scope) use (&$count) {
            if ($scope === 'user:2') {
                return null;
            }

            $count++;

            return "before-value-{$count}";
        };
        Feature::define(FeatureWithBeforeHook::class);

        $values = Feature::getAll([
            FeatureWithBeforeHook::class => ['user:1', 'user:2', 'user:3'],
        ]);

        $this->assertSame([
            'Tests\Feature\FeatureWithBeforeHook' => ['before-value-1', 'feature-value', 'before-value-2'],
        ], $values);
    }

    public function test_it_keys_by_feature_name()
    {
        Feature::define(FeatureWithName::class);
        Feature::define(FeatureWithoutName::class);

        $this->assertSame([
            'feature-with-name' => 'feature-with-name-value',
            'Tests\\Feature\\FeatureWithoutName' => 'feature-without-name-value',
        ], Feature::all());

        $this->assertSame([
            'feature-with-name' => 'feature-with-name-value',
            'feature-with-name' => 'feature-with-name-value',
            'Tests\\Feature\\FeatureWithoutName' => 'feature-without-name-value',
        ], Feature::values([
            'feature-with-name',
            FeatureWithName::class,
            FeatureWithoutName::class,
        ]));

        $this->assertSame([
            'feature-with-name' => ['feature-with-name-value'],
            'Tests\\Feature\\FeatureWithoutName' => ['feature-without-name-value'],
        ], Feature::load([
            'feature-with-name',
            FeatureWithoutName::class,
        ]));

        $this->assertSame([
            'feature-with-name' => ['feature-with-name-value'],
            'Tests\\Feature\\FeatureWithoutName' => ['feature-without-name-value'],
        ], Feature::load([
            FeatureWithName::class,
            FeatureWithoutName::class,
        ]));

        Feature::flushCache();

        $this->assertSame([
            'feature-with-name' => ['feature-with-name-value'],
            'Tests\\Feature\\FeatureWithoutName' => ['feature-without-name-value'],
        ], Feature::loadMissing([
            'feature-with-name',
            FeatureWithoutName::class,
        ]));

        Feature::flushCache();

        $this->assertSame([
            'feature-with-name' => ['feature-with-name-value'],
            'Tests\\Feature\\FeatureWithoutName' => ['feature-without-name-value'],
        ], Feature::loadMissing([
            FeatureWithName::class,
            FeatureWithoutName::class,
        ]));
    }

    public function testItCanLoadAllFeaturesForScope()
    {
        Feature::define('bar', fn ($scope) => $scope === 'taylor');
        Feature::define('foo', fn ($scope) => $scope === 'tim');

        Feature::for(['tim', 'taylor'])->loadAll();

        $records = DB::table('features')->orderBy('scope')->orderBy('name')->get(['name', 'scope', 'value'])->all();
        $this->assertCount(4, $records);
        $this->assertEquals((object) [
            'scope' => 'taylor',
            'value' => 'true',
            'name' => 'bar',
        ], $records[0]);
        $this->assertEquals((object) [
            'scope' => 'taylor',
            'value' => 'false',
            'name' => 'foo',
        ], $records[1]);
        $this->assertEquals((object) [
            'scope' => 'tim',
            'value' => 'false',
            'name' => 'bar',
        ], $records[2]);
        $this->assertEquals((object) [
            'scope' => 'tim',
            'value' => 'true',
            'name' => 'foo',
        ], $records[3]);
    }
}

class UnregisteredFeature
{
    public function __invoke()
    {
        return 'unregistered-value';
    }
}

class UnregisteredFeatureWithResolve
{
    public function resolve()
    {
        return 'unregistered-value.resolve';
    }
}

class UnregisteredFeatureWithName
{
    public $name = 'feature-name';

    public function __invoke()
    {
        return 'unregistered-value';
    }
}

class FeatureWithBeforeHook
{
    public static $before;

    public function resolve()
    {
        return 'feature-value';
    }

    public function before()
    {
        return (static::$before)(...func_get_args());
    }
}

class FeatureWithTypedBeforeHook
{
    public static $before;

    public function resolve()
    {
        return 'feature-value';
    }

    public function before(string $scope)
    {
        return (static::$before)(...func_get_args());
    }
}

class FeatureWithoutName
{
    public function __invoke()
    {
        return 'feature-without-name-value';
    }
}

class FeatureWithName
{
    public $name = 'feature-with-name';

    public function __invoke()
    {
        return 'feature-with-name-value';
    }
}
