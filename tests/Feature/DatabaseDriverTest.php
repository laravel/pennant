<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Laravel\Feature\Contracts\FeatureScopeable;
use Laravel\Feature\Events\RetrievingKnownFeature;
use Laravel\Feature\Events\RetrievingUnknownFeature;
use Laravel\Feature\Feature;
use Tests\TestCase;

class DatabaseDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('features.default', 'database');

        DB::enableQueryLog();
    }

    public function test_it_defaults_to_false_for_unknown_values()
    {
        $result = Feature::isActive('foo');

        $this->assertFalse($result);

        $this->assertCount(2, DB::getQueryLog());
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

        $this->assertCount(2, DB::getQueryLog());
    }

    public function test_it_can_register_default_boolean_values()
    {
        Feature::register('true', fn () => true);
        Feature::register('false', fn () => false);

        $true = Feature::isActive('true');
        $false = Feature::isActive('false');

        $this->assertTrue($true);
        $this->assertFalse($false);

        $this->assertCount(4, DB::getQueryLog());
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

        $this->assertCount(4, DB::getQueryLog());
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

        $this->assertCount(2, DB::getQueryLog());
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

        $this->assertCount(12, DB::getQueryLog());
    }

    public function test_it_can_programatically_activate_and_deativate_features()
    {
        Feature::activate('foo');
        $this->assertTrue(Feature::isActive('foo'));

        Feature::deactivate('foo');
        $this->assertFalse(Feature::isActive('foo'));

        Feature::activate('foo');
        $this->assertTrue(Feature::isActive('foo'));

        $this->assertCount(4, DB::getQueryLog());
    }

    public function test_it_dispatches_events_when_checking_known_features()
    {
        Event::fake([RetrievingKnownFeature::class]);
        Feature::register('foo', fn () => true);

        Feature::isActive('foo');
        Feature::isActive('foo');

        Event::assertDispatchedTimes(RetrievingKnownFeature::class, 1);
        Event::assertDispatched(function (RetrievingKnownFeature $event) {
            return $event->feature === 'foo' && $event->scope === null;
        });

        $this->assertCount(2, DB::getQueryLog());
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

        $this->assertCount(10, DB::getQueryLog());
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

        $this->assertCount(7, DB::getQueryLog());
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

        $this->assertCount(6, DB::getQueryLog());
    }

    public function test_it_can_activate_and_deactivate_features_with_scope()
    {
        $first = new User(['id' => 1]);
        $second = new User(['id' => 2]);

        Feature::for($first)->activate('foo');

        $this->assertFalse(Feature::isActive('foo'));
        $this->assertTrue(Feature::for($first)->isActive('foo'));
        $this->assertFalse(Feature::for($second)->isActive('foo'));

        $this->assertCount(6, DB::getQueryLog());
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

        $this->assertCount(8, DB::getQueryLog());
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

        $this->assertCount(16, DB::getQueryLog());
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

        $this->assertCount(12, DB::getQueryLog());
    }

    public function test_null_is_same_as_global()
    {
        Feature::activate('foo');

        $this->assertTrue(Feature::for(null)->isActive('foo'));

        $this->assertCount(2, DB::getQueryLog());
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

        $this->assertCount(8, DB::getQueryLog());
    }

    public function test_scope_can_be_strings_like_email_addresses()
    {
        Feature::for('tim@laravel.com')->activate('foo');

        $this->assertFalse(Feature::for('james@laravel.com')->isActive('foo'));
        $this->assertTrue(Feature::for('tim@laravel.com')->isActive('foo'));

        $this->assertCount(4, DB::getQueryLog());
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

        $this->assertCount(4, DB::getQueryLog());
    }

    public function test_it_serializes_eloquent_models()
    {
        Schema::create('users', function ($table) {
            $table->id();
            $table->timestamps();
        });
        Feature::for(User::create())->activate('foo');

        $scope = DB::table('features')->value('scope');

        $this->assertStringContainsString('ModelIdentifier', $scope);
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
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::isActive('foo');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::load('bar');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::isActive('bar');
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
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->isActive('foo');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::load(['bar' => 'loaded']);
        $this->assertSame(1, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::for('loaded')->isActive('bar');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::for('noloaded')->isActive('bar');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(2, $called['bar']);

        Feature::load([
            'foo' => [1, 2, 3],
            'bar' => [2],
        ]);
        $this->assertSame(4, $called['foo']);
        $this->assertSame(3, $called['bar']);

        Feature::for([1, 2, 3])->isActive('foo');
        Feature::for([2])->isActive('bar');
        $this->assertSame(4, $called['foo']);
        $this->assertSame(3, $called['bar']);

        $this->assertCount(9, DB::getQueryLog());
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
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->isActive('foo');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(0, $called['bar']);

        Feature::for('loaded')->load('bar');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::for('loaded')->isActive('bar');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(1, $called['bar']);

        Feature::for('noloaded')->isActive('bar');
        $this->assertSame(1, $called['foo']);
        $this->assertSame(2, $called['bar']);

        Feature::for([1, 2, 3])->load(['foo']);
        Feature::for(2)->load(['bar']);
        $this->assertSame(4, $called['foo']);
        $this->assertSame(3, $called['bar']);

        Feature::for([1, 2, 3])->isActive('foo');
        Feature::for([2])->isActive('bar');
        $this->assertSame(4, $called['foo']);
        $this->assertSame(3, $called['bar']);

        $this->assertCount(11, DB::getQueryLog());
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

        $this->assertCount(8, DB::getQueryLog());
    }

    public function test_missing_results_are_inserted_on_load()
    {
        Feature::register('foo', function () use (&$called) {
            return 1;
        });
        Feature::register('bar', function () use (&$called) {
            return 2;
        });

        Feature::for('taylor@laravel.com')->activate('foo', 99);
        Feature::for(['tim@laravel.com', 'jess@laravel.com', 'taylor@laravel.com'])->load(['foo', 'bar']);

        $this->assertCount(4, DB::getQueryLog());
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
        Feature::register('foo', fn () => true);
        Feature::register('bar', fn () => false);
        Feature::register('baz', fn () => false);

        $registered = Feature::registered();

        $this->assertSame(['foo', 'bar', 'baz'], $registered);
        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_it_can_clear_the_cache()
    {
        Feature::register('foo', fn () => true);

        Feature::isActive('foo');
        Feature::flushCache();
        Feature::isActive('foo');

        $this->assertCount(3, DB::getQueryLog());
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

    public function test_it_can_reevaluate_feature_state()
    {
        Feature::register('foo', fn () => false);
        $this->assertFalse(Feature::for('tim')->value('foo'));

        Feature::for('tim')->forget('foo');

        Feature::register('foo', fn () => true);
        $this->assertTrue(Feature::for('tim')->value('foo'));
    }

    public function test_it_can_prune_flags()
    {
        Feature::register('foo', fn () => 1);
        Feature::register('bar', fn () => 2);

        $result = Feature::for('tim')->values(['foo', 'bar']);
        $this->assertSame([
            'foo' => 1,
            'bar' => 2,
        ], $result);

        Feature::forgetDrivers();
        Feature::register('bar', fn () => 2);

        Feature::prune();

        $result = Feature::for('tim')->values(['foo', 'bar']);
        $this->assertSame([
            'foo' => false,
            'bar' => 2,
        ], $result);
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

        Feature::setDefaultScopeResolver(fn () => 'bar');
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

        Feature::setDefaultScopeResolver(fn () => null);
        Feature::isActive('foo');

        $this->assertSame([
            null,
        ], $scopes);
    }
}
