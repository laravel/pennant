## Basic usage

The following feature flag expects there to be "scope". Scope is the thing we are checking the feature against.  Scope may be an Eloquent model, an email address, a country ('AU', 'US'), etc. Anything really.

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

### The scope passed in

The Closure will receive the "scope" for the feature check being performed. The scope may be `null`, and eloquent model, a `string` or something else entirely. We will learn more about scope values later.

### The returned state

The feature resolver should return whatever the value of the feature should be when not already persisted by the driver. Generally a `boolean` would be returned, however the package also supports complex values. We will learn more about the supported values later.

### Lotteries

Lotteries are a first party citizen. Because they are callable, they may be passed as an argument...

```php
<?php

Feature::register('foo', Lottery::odds(1 / 1000));
```

Lotteries may also be returned from the Closure...

```php
<?php

Feature::register('foo', function ($user) {
    if ($user->isAdmin) {
        return true;
    }

    return Lottery::odds(1 / 1000);
});
```

### Fallback values

Our first party drivers fallback to `false` for unknown features i.e. if we try to access a feature with no resolver, the result will always be `false`.

```php
<?php

Feature::for($tim)->isActive('random-misspelt-feature');
// false
```

However, once checked this feature will be persisted to storage. I think this makes sense, but is also something we should consider.

### Other drivers

It is possible that some drivers may just throw an exception when trying to "register" a feature, as that process is handled completely on their end.

Take something like LaunchDarkly. Their driver / docs may tell developers that they do not need to "register" features as that is all handled in their dashboard. That is no trouble and I've tried to build this in a way to facilitate that as a "feature".

## Feature values

As previously stated, generally feature flags are "on" or "off". However, it can often be useful to store more "complex" values as a flag. "complex" really meaning things like a string, array of JSON, etc.

Take the example of a new button design. We may be trialling 3 different button colours.

```php
<?php

// Register the feature...

Feature::register('buy-now-button-color', function (): string {
    return Arr::random(['green', 'blue', 'black']);
});


// Use the feature in Blade...

@if(Feature::for($user)->value('buy-now-button-color') === 'green')
    <GreenButton />
@elseif(Feature::for($user)->value('buy-now-button-color') === 'blue')
    <BlueButton />
@else
    <BlackButton />
@endif
```

This is a feature that is important for supporting 3rd party vendors as most (all the ones I've looked at) support this kind of behaviour.

This raises the question, what does "active" vs "inactive" mean when using rich values. An inactive feature is any feature that is explicitly set to `false`.

### TODO

- Decide how to serialize the value. Currently throwing `serialize` at the problem, but we should probably use `json_encode`.

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

### TODO

- Provide a `Feature::flushCache()` method to clear the cache when needed.
- Consider if we need to manually clear the cache for Octane / Queue workers.

## Scope

`toFeatureScopeable`
//

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

## API

Do we want a way to detect if the feature has been set yet?

Database driver needs to use the scope comparator.
Database driver needs to handle eloquent model.
How do we feel about serialize? Could be dicey.
Events are only triggered when the feature is being resolved from the base driver. Generally this would be once per request.
Nice api for 'remember' vs 'register'?

### Retrieving state

```php
<?php

Feature::value('foo'); // single value
Feature::for($tim)->value('foo'); // single value
Feature::value(['foo', 'bar']); // array of values
Feature::for([$tim, $james]->value('foo'); // array of values
Feature::for([$tim, $james]->value(['foo', 'bar']); // array of values

Feature::for($tim)->load('foo');
Feature::for($tim)->load(['foo']);
Feature::for([$tim, $james])->load(['foo']);
Feature::load(['asdf' => 'asdf'])

// Determine if a feature is active...

if (Feature::isActive('foo')) {
    //
}

// Determine if a feature is inactive...

if (Feature::isInactive('foo')) {
    //
}

// Execute code paths based on the feature state...

Feature::whenActive('foo',
    fn () => Foo::dispatch(),
    fn () => Bar::dispatch(),
);

// Determine if a feature is active for a specific user...

$user = User::first();

if (Feature::for($user)->isActive('foo')) {
    //
}

// Determine if a feature is inactive for a specific user...

$user = User::first();

if (Feature::for($user)->isInactive('foo')) {
    //
}

// Determine if a feature is active for several users...

$users = User::limit(10)->first();

if (Feature::for($users)->isActive('foo')) {
    //
}

// Determine if a feature is inactive for several users...

$users = User::limit(10)->first();

if (Feature::for($users)->isInactive('foo')) {
    //
}

// Handling "variants" via dot notation...

if (Feature::isActive('foo.a')) {
    //
}

if (Feature::isActive('foo.b')) {
    //
}

if (Feature::isActive('foo.c')) {
    //
}

Feature::whenActive('foo', fn ($v) => match ($v) {
    'a' => Bar::dispatch(),
    'b' => Baz::dispatch(),
    default: Other::dispatch(),
});
```

### Adjusting the default user to resolve

```php
<?php

// Resolve the user that features are being evaluated against.
Feature::user();

// Set the user that features are evaluated against when not useing Feature::for($user)->etc()
Feature::setUserResolver(fn () => User::first());
```

### Registering state

```php
<?php

// Register how to determine the state of a flag...

// Option 1: Pass through the user to the closure, no DI.
// Downside: Cannot use DI to resolve dependencies.

Feature::register('foo', function (User $user): bool => {
    // local, global...
    return true;

    // local, user scoped...
    return $user->isEarlyAdopter();

    // external, global...
    return ServiceFacade::check('foo');

    // external, user scoped...
    return ServiceFacade::check('foo', $user);
});

// Option 2: Allow DI in the closure.
// Downside: Passing through the user to check against doesn't really work, cause it all comes from the container.
// Decision: ❌

Feature::register('foo', function (Service $service, Auth $auth): bool => {
    // local, global...
    return true;

    // local, user scoped (only able to get the authenticated user)...
    return $auth->isEarlyAdopter();

    // external, global...
    return $service->check('foo');

    // external, user scoped...
    return $service->check('foo', $auth->user());
});

// Option 3: DI in the top level closure. Pass user to returned closure.
// Downside: Bit overhead overhead.

Feature::register('foo', function (Service $service): bool|Closure => {
    // local, global...
    return true;

    // local, user scoped...
    return fn (User $user): bool => $user->isEarlyAdopter();

    // external, global...
    return $service->check('foo');

    // external, user scoped...
    return fn (User $user): bool => $service->check('foo', $user);
});

// Option 4: Flip option 3 on its head.
// Downside: As above, but a little nicer if you just reach for facades or don't need services.

Feature::register('foo', function (User $user): bool|Closure => {
    // local, global...
    return true;

    // local, user scoped...
    return $user->isEarlyAdopter();

    // external, global...
    return fn (Service $service) => $service->check('foo');

    // external, user scoped...
    return fn (Service $service) => $service->check('foo', $user);
});

// Option 4: Some stateful magic on the Feature facade
// Note:
//     - I think I prefer this one, as the Facade is already present and being used.
//     - Feature::user() may be null, but apps can also handle that themselves.

Feature::register('foo', function (Service $service): bool|Closure => {
    // local, global...
    return true;

    // local, user scoped...
    return Feature::user()->isEarlyAdopter();

    // external, global...
    return $service->check('foo');

    // external, user scoped...
    return $service->check('foo', Feature::user());
});

// Activate feature based on a lottery.

Feature::register('foo', fn () => Lottery::odds(1, 100));

// Put users into a feature segment...

Feature::register('foo', function (): string {
    if (Feature::user()->isEarlyAdopter()) {
        return 'a';
    }

    if (Feature::user()->isSubscribed()) {
        return 'b';
    }

    return 'c';
});

// Set a start time for a feature...
// Note: Features need a `ttl` that indicates when they should revalidate. There
// should be a default. possibly be the 3rd parameter to the register function, 
// or allow something like `return Feature::activate()->ttl(Interval::month(1))`

Feature::register('foo', function (Auth $auth) {
    // Early adopters get it now.
    if ($auth->user()->earlyAdopter) {
        return true;
    }

    // Otherwise everyone can have it after the end of June 2022 (could also be
    // before a certain date)
     return Carbon::now()
         ->setYear(2022)
         ->setMonth(6)
         ->startOfMonth()
         ->isPast();
});


// Register a feature flag that should never be "cached" and should instead be
// re-evaluated at runtime, every time. We could use 
Feature::register('foo', Lottery::odds(1, 100))->forgetful();
```

### Changing existing state

```php
<?php

// Activate a feature for everyone...

Feature::activate('foo');

// Activate a feature for specific user...

$user = User::first();

Feature::for($user)->activate('foo');

// Activate a feature for many users...

$user = User::limit(10)->get();

Feature::for($users)->activate('foo');

// Activate for a random amount of users...

Feature::for(Lottery::odds(1, 100))->activate('foo'))

// Deactivate a feature for everyone...

Feature::deactivate('foo');


// Deactivate a feature for specific user...

$user = User::first();

Feature::for($user)->deactivate('foo');

// Deactivate a feature for many users...

$user = User::limit(10)->get();

Feature::for($users)->deactivate('foo');

// Deactivate for a random number of users...

Feature::for(Lottery::odds(1, 100))->deactivate('foo'))
```

### To consider

- basic values with segments. "request_limit.5" "rate:4,5,6"
- For registering things, could we detect the parameter type, and allow multiple registrations for different types but the same feature?
- Allow only a class to be registered. Feature::register(Foo::class);
- https://3.basecamp.com/3734608/buckets/19188934/messages/5567248106
- Segment responses when `foo.a` is set but checking for `foo`
- Lazy / Eager feature saving. After request, for example.
- post 1.0: Keeping track of feature usage / dashboard, Allow "throw on unknown flag". Just add a listener.
- Ability to provide reasoning for the current value. Maybe return a rich object from the register closure for local versions. Maybe actually just "meta" data.
- For something like launch darkly, the closure on register() may just be used for "offline" mode.
- Is it possible to get all features for a given user / entity?
- Ability to get all features for given scope.
- Ability to register "remembered" and always "in-memory" features. i.e. ones that re-evaluate each time.
         //// Questions...

         //// What should we do in the following scenario...

         ////        Feature::register('foo', fn ($user) => $user->is($tim));
         ////
         ////        Feature::isActive('foo');  // ❌ this value has been remebered for next time.
         ////
         ////        Feature::for($tim)->isActive('foo'); // ✅ this value has been remembered for next time.
         ////
         ////        Feature::globally()->activate('foo'); // turn the feature on globally.
         ////
         ////        Feature::isActive('foo');  // ✅ this value has been remebered for next time.
         ////
         ////        Feature::for($tim)->isActive('foo'); // ❓ Should this now be active for Tim?
         ////
         ////        // Should we introduce modifiers?
         ////
         ////        Feature::globally()->activate('foo')->flushingExisting();
         ////        Feature::globally()->activate('foo')->rememberExisting();

