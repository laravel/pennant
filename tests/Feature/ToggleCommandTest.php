<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class ToggleCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_can_toggle_a_feature_globally()
    {
        Feature::define('foo', true);

        $this->assertTrue(Feature::active('foo'));

        $this->artisan('pennant:toggle foo')
            ->expectsOutputToContain("Feature 'foo' deactivated globally.")
            ->expectsOutputToContain('Before: active')
            ->expectsOutputToContain('After: inactive');

        $this->assertFalse(Feature::active('foo'));

        $this->artisan('pennant:toggle foo')
            ->expectsOutputToContain("Feature 'foo' activated globally.")
            ->expectsOutputToContain('Before: inactive')
            ->expectsOutputToContain('After: active');

        $this->assertTrue(Feature::active('foo'));
    }

    public function test_it_can_force_activate_a_feature()
    {
        Feature::define('bar', false);

        $this->assertFalse(Feature::active('bar'));

        $this->artisan('pennant:toggle bar --on')
            ->expectsOutputToContain("Feature 'bar' activated globally.")
            ->expectsOutputToContain('Before: inactive')
            ->expectsOutputToContain('After: active');

        $this->assertTrue(Feature::active('bar'));

        $this->artisan('pennant:toggle bar --on')
            ->expectsOutputToContain('After: active');

        $this->assertTrue(Feature::active('bar'));
    }

    public function test_it_can_force_deactivate_a_feature()
    {
        Feature::define('foo', true);

        $this->assertTrue(Feature::active('foo'));

        $this->artisan('pennant:toggle foo --off')
            ->expectsOutputToContain("Feature 'foo' deactivated globally.")
            ->expectsOutputToContain('Before: active')
            ->expectsOutputToContain('After: inactive');

        $this->assertFalse(Feature::active('foo'));

        $this->artisan('pennant:toggle foo --off')
            ->expectsOutputToContain('After: inactive');

        $this->assertFalse(Feature::active('foo'));
    }

    public function test_it_can_toggle_a_scoped_feature()
    {
        Feature::define('bar', false);

        $this->assertFalse(Feature::for('user:tim')->active('bar'));

        $this->artisan('pennant:toggle bar --scope=user:tim')
            ->expectsOutputToContain("Feature 'bar' activated for scope 'user:tim'.")
            ->expectsOutputToContain('Before: inactive')
            ->expectsOutputToContain('After: active');

        $this->assertTrue(Feature::for('user:tim')->active('bar'));
        $this->assertFalse(Feature::active('bar'));

        $this->artisan('pennant:toggle bar --scope=user:tim')
            ->expectsOutputToContain("Feature 'bar' deactivated for scope 'user:tim'.")
            ->expectsOutputToContain('Before: active')
            ->expectsOutputToContain('After: inactive');

        $this->assertFalse(Feature::for('user:tim')->active('bar'));
    }

    public function test_it_can_force_activate_for_scope()
    {
        Feature::define('foo', false);

        $this->artisan('pennant:toggle foo --scope=team:dev --on')
            ->expectsOutputToContain("Feature 'foo' activated for scope 'team:dev'.")
            ->expectsOutputToContain('Before: inactive')
            ->expectsOutputToContain('After: active');

        $this->assertTrue(Feature::for('team:dev')->active('foo'));
    }

    public function test_it_handles_non_boolean_values()
    {
        Feature::define('bar', 'control');

        $this->artisan('pennant:toggle bar')
            ->expectsOutputToContain("Feature 'bar' deactivated globally.");

        $this->assertFalse(Feature::value('bar'));

        $this->artisan('pennant:toggle bar --on')
            ->expectsOutputToContain("Feature 'bar' activated globally.");

        $this->assertTrue(Feature::value('bar'));
    }

    public function test_it_can_use_custom_store()
    {
        config(['pennant.stores.custom' => ['driver' => 'array']]);

        Feature::define('foo', true);

        Feature::store('custom')->for('test')->activate('foo');

        $this->assertTrue(Feature::store('custom')->for('test')->active('foo'));

        $this->artisan('pennant:toggle foo --store=custom --scope=test')
            ->expectsOutputToContain("Feature 'foo' deactivated for scope 'test'.");

        $this->assertFalse(Feature::store('custom')->for('test')->active('foo'));

        $this->assertTrue(Feature::active('foo'));
    }

    public function test_it_activates_unknown_features()
    {
        $this->artisan('pennant:toggle unknown')
            ->expectsOutputToContain("Feature 'unknown' activated globally.")
            ->expectsOutputToContain('After: active');

        $this->assertTrue(Feature::active('unknown'));
    }
}