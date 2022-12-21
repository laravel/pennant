This doc outlines the features, but it would be good to go an checkout the actual contract for implementing a driver. It is rather small. Most of the features come from our decorator.

By default, the feature is checked against the currently authenticated user. This removes the need to specify the user in the most common scenarios.

## Basic usage

```php
<?php

/*
 * Register the feature resolver in a service provider, middleware, etc.
 */

Feature::register('new-api', function (?User $user): mixed {
    if ($user?->isInternal()) {
        return true;
    }

    return Lottery::odds(1 / 1000);
});

Auth::login($tim);

/*
 * For convenience, the default behaviour is to check if the feature is active
 * against the currently authenticated user, i.e. "$tim"
 */

if (Feature::isActive('new-api')) {
    // Feature is active for $tim
}

/*
 * If you want to check against another user, you may do the following...
 */

if (Feature::for($james)->isActive('new-api')) {
    // Feature is active for $james
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

The Closure will receive the scope for the feature check being performed. The scope may be `null` (used to indicate that no scope was given which can be useful for anonymous / global feature flags), an eloquent model, a `string` or something else entirely. We will learn more about scope values later, but just remember it is what you are checking the feature against.

### Returned value

The feature resolver should return the value of the feature flag. Generally a `boolean` would be returned indicating that a feature is "active" or "inactive", however the package also supports complex values (such as strings or arrays). We will learn more about the supported values later.

### Returning a lottery

As a convenience, lotteries are a first party citizen. Because they are callable, they may be passed as an argument without the need for a closure...

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

You may also pass a static value as the argument. This is useful if you are creating feature flags only based on the ENV.

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

As already mentioned, the currently authenticated user is the default scope, so for most application usage, the `for` call won't be required.

```php
Feature::isActive('foo');
Feature::isInactive('foo');
```

## Feature values

Generally feature flags are on or off and represented by `boolean` values. However, it can be useful to store more complex values as a flag. "Complex" meaning things like a string, array of JSON, etc.

Take the example of a new button design. We may be trialling 3 different button colours.

```php
<?php

// Register the feature...

Feature::register('buy-now-button-color', function (): string {
    return Arr::random([
        'green',
        'blue',
        'black',
    ]);
});

Feature::value('buy-now-button-color'); // 'green'|'blue'|'black'


// Use the feature in Blade...

@if(Feature::value('buy-now-button-color') === 'green')
    <GreenButton />
@elseif(Feature::value('buy-now-button-color') === 'blue')
    <BlueButton />
@else
    <BlackButton />
@endif
```

This is a feature that is nice for supporting 3rd party vendors as most (all the ones I've looked at) support this kind of behaviour.

This raises the question, what does "active" vs "inactive" mean when using rich values: an inactive feature is any feature that is explicitly set to `false` - everything else is considered active.

## Caching

To reduce complexity for driver implementations, we provide the drivers with an in-memory cache of resolved feature states. The drivers are not aware of this cache.

This means that a feature + scope combination will only be resolved from the driver once per request. This is important as it ensures the state of the feature flag is consistent throughout a given request.

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

It is possible to bust the cache programmatically.

```php
Feature::flushCache();
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
    public function toFeatureIdentifier($driver)
    {
        return match ($driver) {
            'launchdarkly' => new LDUser($this->id),
            default => "user:{$this->id}",
        };
    }
}
```

The drivers do not need to worry about this interface, as the decorator takes care of converting the objects.

Eloquent models do not have to implement this feature. We utilise the queue's model serializing trait to help out here, although an Eloquent model can implement this if they want control.

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

This allows users to keep track of what features are being used / not used, or when they are trying to resolve features that do not exist. These events should be triggered by drivers as they could handle things differently.

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

## Fallback values

Our first party drivers fallback to `false` for unknown features i.e. if we try to access a feature with no resolver, the result will always be `false`.

```php
<?php

Feature::for($tim)->isActive('random-misspelt-feature');
// false
```

However, once checked this feature will be persisted to storage. I think this makes sense, but is also something we should consider.

### TODO

- decorator needs to use comparator,
- Database driver needs to use the scope comparator.

```php
<?php

Feature::whenActive('foo', fn ($v) => match ($v) {
    'a' => Bar::dispatch(),
    'b' => Baz::dispatch(),
    default: Other::dispatch(),
});
