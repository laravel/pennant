This doc outlines the features, but it would be good to go an checkout the actual contract for implementing a driver. It is rather small. Most of the features come from our decorator.

## Basic usage

The following feature flag expects there to be scope. Scope is the thing we are checking the feature against. Scope may be an Eloquent model, an email address, a country ('AU', 'US'), etc. Anything really.

```php
<?php

/*
 * Register the feature resolver in a service provider, middleware, etc.
 */

Feature::register('new-api', function ($user) {
    if ($user->isInternal()) {
        return true;
    }

    return Lottery::odds(1 / 1000);
});

/*
 * Check if the feature is active throughout the application usage, via the Facade.
 */

if (Feature::for($user)->isActive('new-api')) {
    //
}

/*
 * Or check via an object using the provided `HasFeatures` trait.
 */

if ($user->featureIsActive('new-api')) {
    //
}
```

## Registering feature resolvers

In your service provider, middleware, etc, you may register a feature resolver.

```php
<?php

Feature::register('foo', function (mixed $scope): mixed {
    // return ...
});
```

### The `$scope` parameter

The Closure will receive the scope for the feature check being performed. The scope may be `null` (used to indicate that no scope was given which can be useful for anonymous / global feature flags), an eloquent model, a `string` or something else entirely. We will learn more about scope values later.

### Returned value

The feature resolver should return the value of the feature flag. Generally a `boolean` would be returned, however the package also supports complex values (such as strings or arrays). We will learn more about the supported values later.

### Returning a lottery

Lotteries are a first party citizen. Because they are callable, they may be passed as an argument...

```php
<?php

Feature::register('foo', Lottery::odds(1 / 1000));
```

Lotteries may also be returned from the Closure to compose more complex resolution rules...

```php
<?php

Feature::register('foo', function ($user) {
    if ($user->isAdmin) {
        return true;
    }

    return Lottery::odds(1 / 1000);
});
```

### Static values

You may also pass a static value as the argument, such as a config option that is reading from the ENV.

```php
<?php

Feature::register('new-cron-job-provisioning', config('features.new-cron-job-provisioning'));
Feature::register('new-foo-provisioning', config('features.new-foo-provisioning'));
```

### Other drivers

It is possible that some drivers may throw an exception when trying to register a feature, as that process is handled completely on their end.

Take something like LaunchDarkly. Their driver may tell developers that they do not need to register features as that is all handled in their first party dashboard. That is no trouble and I've tried to build this in a way to facilitate that as a feature.

Alternatively, they may still get you to register features and the result of that could be used in "offline" mode when the service is down for maintenance or otherwise unavailable.

## Checking a feature

To check if a feature is active or inactive, we provide the following APIs:

```php
<?php

Feature::for($tim)->isActive('foo');
Feature::for($tim)->isInactive('foo');
```

It is possible to check if multiple values are all active in one method call...

```php
<?php

Feature::for($tim)->allAreActive(['foo', 'bar']);
Feature::for($tim)->allAreInactive(['foo', 'bar']);
```

It is possible to check if any given values are active in one method call...

```php
<?php

Feature::for($tim)->anyAreActive(['foo', 'bar']);
Feature::for($tim)->anyAreInactive(['foo', 'bar']);
```

Note: `all` vs `any`

It is also possible to check against multiple scope at once...

```php
<?php

Feature::for([$tim, $jess])->allAreActive(['foo', 'bar']);
```

## Feature values

As previously stated, generally feature flags are on or off and represented by `boolean` values. However, it can often be useful to store more complex values as a flag. "Complex" meaning things like a string, array of JSON, etc.

Take the example of a new button design. We may be trialling 3 different button colours.

```php
<?php

// Register the feature...

Feature::register('buy-now-button-color', function (): string {
    return Arr::random(['green', 'blue', 'black']);
});

Feature::for($user)->value('buy-now-button-color'); // 'green'|'blue'|'black'


// Use the feature in Blade...

@if(request()->user()->featureValue('buy-now-button-color') === 'green')
    <GreenButton />
@elseif(request()->user()->featureValue('buy-now-button-color') === 'blue')
    <BlueButton />
@else
    <BlackButton />
@endif
```

This is a feature that is nice for supporting 3rd party vendors as most (all the ones I've looked at) support this kind of behaviour.

This raises the question, what does "active" vs "inactive" mean when using rich values. An inactive feature is any feature that is explicitly set to `false` - everything else is considered active.

## Caching

To reduce complexity for driver implementations, we provide the drivers with an in-memory cache of resolved feature states. The drivers are not aware of this cache.

This means that a feature + scope combination will only be resolved from the driver once per request. This is important as allow the state of the feature flag to change throughout a request could result in strange behaviour.

Imagine changing the `"new-design"` flag on a user part way through the request. It would lead to a half-half visual design.

It also means, that if you don't call "load" to eagerly load beforehand, you won't have to hit the DB / service multiple times for the same feature, just like Eloquent relations cache on the model.

```php
<?php

Feature::register('foo', new Lottery(1 / 1000));

// resolved from the driver (hits the DB / feature flag service) and the result is cached in-memory.
Feature::for($taylor)->isActive('foo');

// resolved from the in-memory cache. Does not invoke the driver at all.
Feature::for($taylor)->isActive('foo');
Feature::for($taylor)->isActive('foo');
Feature::for($taylor)->isActive('foo');

// Because we are passing through new "scope", this will be resolved from the driver (hits the DB / feature flag service) and the result is cached in-memory.
Feature::for($jess)->isActive('foo');

// resolved from the in-memory cache. Does not invoke the driver at all.
Feature::for($jess)->isActive('foo');
Feature::for($jess)->isActive('foo');
```

## Eager loading

One thing that is important is offering the ability for drivers to optimize the loading of feature flag values. This is done via a `load` and `loadMissing` method.

You can invoke the load method against a pending feature interaction OR directly against the Facade.

```php
<?php

Feature::for([$tim, $jess])->load([
    'foo', 'bar', 'baz',
]);
```

Alternatively, you may pass an array directly to the load method...

```php
<?php

Feature::load([
    'foo' => [$tim, $jess], 
    'bar' => $tim,
    'baz',
]);
```

The `loadMissing` method will only load values that are not already in memory. Note that drivers do not implement this method and we handle everything for them, including normalising the data into a single known format.

## Scope

As mentioned, scope may be anything. It may be an eloquent model, an email, a number, etc. We really don't care _what_ it is. However we do need to be able to store the scope, and we also want to make it easy for other drivers to work with scope.

It is possible to register a feature that doesn't care about scope. This is useful for things like Jetstream's feature system.

```php
<?php

Feature::register('eu-tax', fn () => false);

Feature::isActive('eu-tax');
// false

Feature::for($tim)->isActive('eu-tax');
// false
```

It may also be the case that you want to pass through a Eloquent model, but allow it be converted into identifiers for other drivers. Take the LaunchDarkly service. It will want an `LDUser`. Objects may implement the `FeatureScopeable` interface.

```php
<?php

class Foo extends Model implements FeatureScopeable
{
    /**
     * The value to use when checking features against the instance.
     *
     * @param  string  $driver
     * @return mixed
     */
    public function toFeatureScopeIdentifier($driver)
    {
        if ($driver === 'launchdarkly') {
            return new LDUser($this->id);
        }

        return $this->id;
    }
}
```

The drivers do not need to worry about this interface, as the decorator takes care of converting the objects.

## Events

There are currently 2 events that are triggered by the package:

- `RetrievingKnownFeature`
- `RetrievingUnknownFeature`

Triggering these events is up to the drivers themselves, as how to detect this will be different for each driver.

For the database driver, the `RetrievingKnownFeature` is triggered when the feature is present in the DB or if there is
a registered "resolver" for the feature.

`RetrievingUnknownFeature` is triggered if there is no value in the DB and there is no resolver.

```php
<?php

Feature::register('foo', fn () => true);

Feature::isActive('bar');
// RetrievingUnknownFeature triggered.

Feature::isActive('foo');
// RetrievingKnownFeature triggered.

// Due to our in-memory caching wrapper, the event for a single feature+scope
// combo should only be triggered once per request. Calling these now will not
// trigger the events again (we could trigger another event though).

Feature::isActive('foo');
Feature::isActive('bar');
// Nothing triggered.
```

This allows users to keep track of what features are being used / not used, or when they are trying to resolve features that do not exist.

### TODO

- Offer a `Feature::throwOnUnknownFeature()` which throws an exception when trying to resolve a feature that does not exist.

## Programmatically changing feature flag values

If you want to manipulate the persisted state of a feature flag, you may use the `activate` and `deactivate` methods.

```php
<?php

// Turn on / off.
Feature::for($tim)->activate('foo');
Feature::for($jess)->deactivate('bar');

// Set a complex value.
Feature::for($tim)->activate('foo', [
    'my' => 'value',
]);
```

It is possible to activate / deactivate several features with one method call.

```php
<?php

Feature::for($tim)->activate(['foo', 'bar']);
```

It is also possible to pass multiple scope through....
```php
<?php

Feature::for([$tim, $jess])->activate(['foo', 'bar']);

Feature::for($circle->members)->activate(['foo', 'bar']);
```

Note that changing the state of feature flags has not been optimized for bulk at the driver level. The driver API will always receive one feature + once scope to activate at a time.

### TODO

- Provide artisan commands to manipulate the state of things:
    - For everyone: `php artisan feature:activate new-api`
    - For those it is currently inactive for: `php artisan feature:activate new-api --only-inactive`
    - etc.

## Fallback values

Our first party drivers fallback to `false` for unknown features i.e. if we try to access a feature with no resolver, the result will always be `false`.

```php
<?php

Feature::for($tim)->isActive('random-misspelt-feature');
// false
```

However, once checked this feature will be persisted to storage. I think this makes sense, but is also something we should consider.

## Misc TODO
- Ability to retrieve the features for a user? or just all available users? Will this resolve the features state?
- decorator needs to use comparator,
- Do we want a way to detect if the feature has been set yet?
- Database driver needs to use the scope comparator.
- Database driver needs to handle eloquent model.
- How do we feel about serialize? Could be dicey.
- Events are only triggered when the feature is being resolved from the base driver. Generally this would be once per request.
- Can we provide a nice api for 'remember' vs 'register' to support in-memory only features for Jetstream et. al.
- `whenActive` on the decorator.
- Allow only a class to be registered. Feature::register(Foo::class);
- Ability to get all features for given scope.
- Ability to register "remembered" and always "in-memory" features. i.e. ones that re-evaluate each time.

```php
<?php

Feature::whenActive('foo', fn ($v) => match ($v) {
    'a' => Bar::dispatch(),
    'b' => Baz::dispatch(),
    default: Other::dispatch(),
});
