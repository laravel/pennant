<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Laravel\Pennant\Feature;
use Tests\TestCase;
use Workbench\App\Models\User;

class ValidationRulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pennant.default', 'array');
    }

    public function test_it_requires_the_value_when_the_feature_is_active()
    {
        $user = new User(['id' => 1]);

        Feature::for($user)->activate('new-api');

        $validator = Validator::make([], [
            'name' => Feature::for($user)->requiredIfActive('new-api'),
        ]);

        $this->assertTrue($validator->fails());
        $this->assertSame(['name'], array_keys($validator->errors()->messages()));
    }

    public function test_it_does_not_require_the_value_when_the_feature_is_inactive()
    {
        $user = new User(['id' => 1]);

        Feature::for($user)->deactivate('new-api');

        $validator = Validator::make([], [
            'name' => Feature::for($user)->requiredIfActive('new-api'),
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_it_is_scoped_to_the_given_scope()
    {
        $active = new User(['id' => 1]);
        $inactive = new User(['id' => 2]);

        Feature::for($active)->activate('new-api');

        $this->assertSame('required', (string) Feature::for($active)->requiredIfActive('new-api'));
        $this->assertSame('', (string) Feature::for($inactive)->requiredIfActive('new-api'));
    }

    public function test_it_uses_the_default_scope_when_no_scope_is_given()
    {
        $user = new User(['id' => 1]);

        $this->actingAs($user);

        Feature::activate('new-api');

        $this->assertSame('required', (string) Feature::requiredIfActive('new-api'));
    }

    public function test_it_resolves_the_feature_lazily()
    {
        $user = new User(['id' => 1]);

        $rule = Feature::for($user)->requiredIfActive('new-api');

        $this->assertSame('', (string) $rule);

        Feature::for($user)->activate('new-api');

        $this->assertSame('required', (string) $rule);
    }

    public function test_it_requires_the_value_when_the_feature_is_inactive()
    {
        $user = new User(['id' => 1]);

        Feature::for($user)->deactivate('new-api');

        $validator = Validator::make([], [
            'name' => Feature::for($user)->requiredIfInactive('new-api'),
        ]);

        $this->assertTrue($validator->fails());
        $this->assertSame(['name'], array_keys($validator->errors()->messages()));
    }

    public function test_it_does_not_require_the_value_when_the_feature_is_active()
    {
        $user = new User(['id' => 1]);

        Feature::for($user)->activate('new-api');

        $validator = Validator::make([], [
            'name' => Feature::for($user)->requiredIfInactive('new-api'),
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_required_if_inactive_is_scoped_to_the_given_scope()
    {
        $active = new User(['id' => 1]);
        $inactive = new User(['id' => 2]);

        Feature::for($active)->activate('new-api');

        $this->assertSame('', (string) Feature::for($active)->requiredIfInactive('new-api'));
        $this->assertSame('required', (string) Feature::for($inactive)->requiredIfInactive('new-api'));
    }

    public function test_required_if_inactive_resolves_the_feature_lazily()
    {
        $user = new User(['id' => 1]);

        $rule = Feature::for($user)->requiredIfInactive('new-api');

        $this->assertSame('required', (string) $rule);

        Feature::for($user)->activate('new-api');

        $this->assertSame('', (string) $rule);
    }

    public function test_it_prohibits_the_value_when_the_feature_is_active()
    {
        $user = new User(['id' => 1]);

        Feature::for($user)->activate('new-api');

        $validator = Validator::make(['name' => 'Tim'], [
            'name' => Feature::for($user)->prohibitedIfActive('new-api'),
        ]);

        $this->assertTrue($validator->fails());
        $this->assertSame(['name'], array_keys($validator->errors()->messages()));
    }

    public function test_it_does_not_prohibit_the_value_when_the_feature_is_inactive()
    {
        $user = new User(['id' => 1]);

        Feature::for($user)->deactivate('new-api');

        $validator = Validator::make(['name' => 'Tim'], [
            'name' => Feature::for($user)->prohibitedIfActive('new-api'),
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_it_prohibits_the_value_when_the_feature_is_inactive()
    {
        $user = new User(['id' => 1]);

        Feature::for($user)->deactivate('new-api');

        $validator = Validator::make(['name' => 'Tim'], [
            'name' => Feature::for($user)->prohibitedIfInactive('new-api'),
        ]);

        $this->assertTrue($validator->fails());

        Feature::for($user)->activate('new-api');

        $validator = Validator::make(['name' => 'Tim'], [
            'name' => Feature::for($user)->prohibitedIfInactive('new-api'),
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_prohibited_rules_are_scoped_and_lazy()
    {
        $user = new User(['id' => 1]);
        $other = new User(['id' => 2]);

        $active = Feature::for($user)->prohibitedIfActive('new-api');
        $inactive = Feature::for($user)->prohibitedIfInactive('new-api');

        $this->assertSame('', (string) $active);
        $this->assertSame('prohibited', (string) $inactive);

        Feature::for($user)->activate('new-api');

        $this->assertSame('prohibited', (string) $active);
        $this->assertSame('', (string) $inactive);

        $this->assertSame('', (string) Feature::for($other)->prohibitedIfActive('new-api'));
    }

    public function test_it_excludes_the_value_when_the_feature_is_active()
    {
        $user = new User(['id' => 1]);

        Feature::for($user)->activate('new-api');

        $validated = Validator::make(['name' => 'Tim', 'email' => 'tim@laravel.com'], [
            'name' => [Feature::for($user)->excludeIfActive('new-api'), 'string'],
            'email' => ['string'],
        ])->validate();

        $this->assertSame(['email' => 'tim@laravel.com'], $validated);
    }

    public function test_it_does_not_exclude_the_value_when_the_feature_is_inactive()
    {
        $user = new User(['id' => 1]);

        Feature::for($user)->deactivate('new-api');

        $validated = Validator::make(['name' => 'Tim', 'email' => 'tim@laravel.com'], [
            'name' => [Feature::for($user)->excludeIfActive('new-api'), 'string'],
            'email' => ['string'],
        ])->validate();

        $this->assertSame(['name' => 'Tim', 'email' => 'tim@laravel.com'], $validated);
    }

    public function test_it_excludes_the_value_when_the_feature_is_inactive()
    {
        $user = new User(['id' => 1]);

        Feature::for($user)->deactivate('new-api');

        $validated = Validator::make(['name' => 'Tim'], [
            'name' => [Feature::for($user)->excludeIfInactive('new-api'), 'string'],
        ])->validate();

        $this->assertSame([], $validated);
    }

    public function test_exclude_rules_are_scoped_and_lazy()
    {
        $user = new User(['id' => 1]);
        $other = new User(['id' => 2]);

        $active = Feature::for($user)->excludeIfActive('new-api');
        $inactive = Feature::for($user)->excludeIfInactive('new-api');

        $this->assertSame('', (string) $active);
        $this->assertSame('exclude', (string) $inactive);

        Feature::for($user)->activate('new-api');

        $this->assertSame('exclude', (string) $active);
        $this->assertSame('', (string) $inactive);

        $this->assertSame('', (string) Feature::for($other)->excludeIfActive('new-api'));
    }

    public function test_it_accepts_a_feature_class()
    {
        $user = new User(['id' => 1]);

        Feature::define(ValidationRulesTestFeature::class, fn () => true);

        $this->assertSame('required', (string) Feature::for($user)->requiredIfActive(ValidationRulesTestFeature::class));
    }
}

class ValidationRulesTestFeature
{
    //
}
