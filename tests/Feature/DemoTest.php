<?php
// Let's assume we only want to scope feature checks to "User" models, for now.

// Our users...

$jess = new User(['id' => 1, 'is_subscribed' => true]);
$tim = new User(['id' => 2]);
$james = new User(['id' => 3]);
$team = new Team(['id' => 4, 'member_count' => 3]);

// "new-login" is registered as only being active for Jess...

Feature::register('new-login', fn (?User $user) => $user?->is($jess) === true);

Feature::isActive('new-login'); // ❌

Feature::for($jess)->isActive('new-login'); // ✅

Feature::for($james)->isActive('new-login'); // ❌

Feature::for([$jess, $james])->isActive('new-login'); // ❌

// Jess logs in...

Auth::login($jess);

Feature::isActive('new-login'); // ❌

// $jess is authenticated.
Feature::forTheAuthenticatedUser()->isActive('new-login'); // ✅

// Let's programatically activate the feature for James...

Feature::for($james)->activate('new-login');

// $jess and $james now have `new-login` active.

Feature::isActive('new-login'); // ❌

Feature::for($jess)->isActive('new-login'); // ✅

Feature::for($james)->isActive('new-login'); // ✅

Feature::for([$jess, $james])->isActive('new-login'); // ✅

// $jess is authenticated.
Feature::forTheAuthenticatedUser()->for($james)->isActive('new-login'); // ✅

Feature::for($tim)->isActive('new-login'); // ❌

// Let's register another feature that is active for *everyone*...

Feature::register('new-faster-api', fn () => true);

Feature::for($jess)->isActive('new-faster-api'); // ✅

Feature::for($james)->isActive('new-faster-api'); // ✅

Feature::for($jess)->for($james)->isActive('new-faster-api'); // ✅

Feature::forTheAuthenticatedUser()->for($james)->isActive('new-faster-api'); // ✅

Feature::isActive('new-faster-api'); // ✅

Feature::for($tim)->isActive('new-faster-api'); // ✅


// It is possible to check for several features states at once against an array of scope...

Feature::for([$jess, $james])->isActive(['new-faster-api', 'new-api-credentials']);

// Oh wait, we just remembered we should have excluded $jess from the new-fast-api as $jess is a major customer
// that we don't want to test the new API on. Problem is, we've persisteded that $jess should use the new API
// because we checked above.

// Let's deactive that feature for $jess manually.

Feature::for($jess)->deactivate('new-faster-api');

Feature::isActive('new-faster-api'); // ✅

Feature::for($jess)->isActive('new-faster-api'); // ❌

Feature::for($james)->isActive('new-faster-api'); // ✅

Feature::for($jess)->for($james)->isActive('new-faster-api'); // ❌

// $jess is authenticated
Feature::forTheAuthenticatedUser()->for($james)->isActive('new-faster-api'); // ❌

Feature::for($tim)->isActive('new-faster-api'); // ✅

// Objects may implement the `HasFeatures` trait and the following API is not available...

$obj = new class(['id' => 55]) extends User {
    use HasFeature;
};

$obj->featureIsActive('new-login');

// Lotteries may be returned from resolvers to have a random value assigned
// based on the odds.

Feature::register('new-checkout', Lottery::odds(1, 1000));

Feature::for($jess)->isActive('new-checkout'); // 1 in 1000 chance. Result will be remembered.
Feature::for($james)->isActive('new-checkout'); // 1 in 1000 chance. Result will be remembered.
Feature::for($tim)->isActive('new-checkout'); // 1 in 1000 chance. Result will be remembered.

// Features do not have to be used against Models. They may also apply to other values...
// This will apply the new email layout to 1 in 10 "gmail" email addresses.

Feature::register('new-mail-layout', Lottery::odds(1, 10)->winner(
    fn ($email) => Str::endsWith($email, '@gmail.com')
));

Feature::for('tim@gmail.com')->isActive('new-mail-layout'); // 1 in 10 chance. Result will be remembered.
Feature::for('tim@laravel.com')->isActive('new-mail-layout'); // will always be false
Feature::for('taylor@gmail.com')->isActive('new-mail-layout'); // 1 in 10 chance. Result will be remembered.

// Everytime we check a feature, there is potential the driver has to make a request,
// hit a database, etc. To reduce that count, we may eagerly load feature
// states in a service provider, middleware, in a controller, etc.

Feature::load([
    'new-faster-api',
    'new-login' => [$jess, $james, $tim],
    'new-search' => [$jess, $james],
]);


// We can also only load values that are not yet in memory...
// This would be a no-op after the previous "load" call.

Feature::loadMissing([
    'new-faster-api',
    'new-login' => [$jess, $james, $tim],
    'new-search' => [$jess, $james],
]);

// Features do not only have to be only be booleans on / off flags.
// They may also be numbers, strings, or JSON blobs if you wanna get
// really fancy.

Feature::register('button-design', fn () => Arr::random(['green', 'black', 'blue']));

// in blade...

@if(Feature::for($tim)->get('button-design') === 'green')
    <Button color="green" label="Buy Now"/>
@elseif(Feature::for($tim)->get('button-design') === 'black')
    <Button color="black" label="Buy Now"/>
@else
    <Button color="blue" label="Buy Now"/>
@endif
