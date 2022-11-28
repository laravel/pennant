## API

### Retrieving state

```php
<?php

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

Feature::globally()->activate('foo');

// Activate a feature for specific user...

$user = User::first();

Feature::for($user)->activate('foo');

// Activate a feature for many users...

$user = User::limit(10)->get();

Feature::for($users)->activate('foo');

// Activate for a random amount of users...

Feature::for(Lottery::odds(1, 100))->activate('foo'))

// Deactivate a feature for everyone...

Feature::globally()->deactivate('foo');


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
- events 
- users should be generic, as they might be "Organisation" or "Team". Feature::for($team)
- Implement a "HasFeatureIdentifer" trait or something for objects to specify their cache key?
- Should scope allow for more than a single thing. an array vs a parameter list are different things. What about scenarios like Feature::for($team, $user)->active('foo') which is checking if a feature is active for the given team.
- Allow a feature to be re-evaluated Feature::for($user)->reEvaluate('foo')
- For registering things, could we detect the parameter type, and allow multiple registrations for different types but the same feature?
- user morph map for identifiers?
- Allow only a class to be registered. Feature::register(Foo::class);
- https://3.basecamp.com/3734608/buckets/19188934/messages/5567248106
- Segment responses when `foo.a` is set but checking for `foo`
- Ability to eagerly load a users active features in a service provider, middleware, etc. Then have that only trigger once the feature system is interacted with.
- Lazy / Eager feature saving. After request, for example.
- post 1.0: Keeping track of feature usage / dashboard
- All drivers should have in-memory caches, as we don't want the state to change throughout a single request.
- Should we be able to check multiple features at once? Feature::active(['a', 'b', 'c'], $user])?
- see test around `null`s. This needs to be seen.
- A check against a user should not then fallback to the "for everyone" existing key. The reason being that it is possible that it is "on" for everyone, except user Y
- naming: laravel/switch
- trigger event when checking
- trigger event when resolving
- Ability to provide reasoning for the current value. Maybe return a rich object from the register closure for local versions. Maybe actually just "meta" data.
- For something like launch darkly, the closure on register() may just be used for "offline" mode.
- Is it possible to get all features for a given user / entity?
- Ability to get all features for a user.
- Allow "throw on unknown flag". Just add a listener.
