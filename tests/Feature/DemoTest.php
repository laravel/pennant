<?php

 namespace Tests\Feature;

 use Illuminate\Database\Eloquent\Model;
 use Illuminate\Support\Facades\Auth;
 use Laravel\Feature\Contracts\FeatureScopeable;
 use Laravel\Feature\Feature;
use Laravel\Feature\HasFeature;
use Tests\TestCase;

 class DemoTest extends TestCase
 {
     public function test_the_demo()
     {
         // Let's assume we only want to scope feature checks to "User" models, for now.

         // Our users...

         $jess = User::make(['id' => 1, 'is_subscribed' => true]);
         $tim = User::make(['id' => 2]);
         $james = User::make(['id' => 3]);
         $team = Team::make(['id' => 4, 'member_count' => 3]);



         // "new-login" is registered as only being active for Jess...

         Feature::register('new-login', fn (?User $user) => $user?->is($jess));

         $this->assertFalse(
             Feature::isActive('new-login') // ❌
         );

         $this->assertTrue(
             Feature::for($jess)->isActive('new-login') // ✅
         );

         $this->assertFalse(
             Feature::for($james)->isActive('new-login') // ❌
         );

         $this->assertFalse(
             Feature::for($jess)->andFor($james)->isActive('new-login') // ❌
         );



         // Jess logs in...

         Auth::login($jess);

         $this->assertFalse(
             Feature::isActive('new-login') // ❌
         );

         $this->assertTrue( // $jess is authenticated.
             Feature::forTheAuthenticatedUser()->isActive('new-login') // ✅
         );



         // Let's programatically activate the feature for James...

         Feature::for($james)->activate('new-login');

         // $jess and $james now have `new-login` active.

         $this->assertFalse(
             Feature::isActive('new-login') // ❌
         );

         $this->assertTrue(
             Feature::for($jess)->isActive('new-login') // ✅
         );

         $this->assertTrue(
             Feature::for($james)->isActive('new-login') // ✅
         );

         $this->assertTrue(
             Feature::for($jess)->andFor($james)->isActive('new-login') // ✅
         );

         $this->assertTrue( // $jess is authenticated.
             Feature::forTheAuthenticatedUser()->andFor($james)->isActive('new-login') // ✅
         );

         $this->assertFalse(
             Feature::for($tim)->isActive('new-login') // ❌
         );



         // Let's register another feature that is active for *everyone*...

         Feature::register('new-faster-api', fn () => true);

         $this->assertTrue(
             Feature::for($jess)->isActive('new-faster-api') // ✅
         );

         $this->assertTrue(
             Feature::for($james)->isActive('new-faster-api') // ✅
         );

         $this->assertTrue(
             Feature::for($jess)->andFor($james)->isActive('new-faster-api') // ✅
         );

         $this->assertTrue(
             Feature::forTheAuthenticatedUser()->andFor($james)->isActive('new-faster-api') // ✅
         );

         $this->assertTrue(
             Feature::isActive('new-faster-api') // ✅
         );

         $this->assertTrue(
             Feature::for($tim)->isActive('new-faster-api') // ✅
         );

         // Oh wait, we just remembered we should have excluded $jess from the new-fast-api as $jess is a major customer
         // that we don't want to test the new API on. Problem is, we've persisteded that $jess should use the new API
         // because we checked above.

         // Let's deactive that feature for $jess manually.

         Feature::for($jess)->deactivate('new-faster-api');

         $this->assertTrue(
             Feature::isActive('new-faster-api') // ✅
         );

         $this->assertFalse(
             Feature::for($jess)->isActive('new-faster-api') // ❌
         );

         $this->assertTrue(
             Feature::for($james)->isActive('new-faster-api') // ✅
         );

         $this->assertFalse(
             Feature::for($jess)->andFor($james)->isActive('new-faster-api') // ❌
         );

         $this->assertFalse( // $jess is authenticated
             Feature::forTheAuthenticatedUser()->for($james)->isActive('new-faster-api') // ❌
         );

         $this->assertTrue(
             Feature::for($tim)->isActive('new-faster-api') // ✅
         );

        // Some features may be based on certain scope. Perhaps you need the
        // user, the "current" team, and some arbitrary value.

         $currentTeam = $team;

         Feature::register('new-search', function ($user, $team, $country = null) {
             return $user->is_subscribed && $team->member_count < 3 && $country === 'AU';
         });

         // 'CF-IPCountry' => 'US'
         Feature::for($jess, $currentTeam, request()->header('CF-IPCountry'))->isActive('new-search'); // false ❌

         // 'CF-IPCountry' => 'AU'
         Feature::for($jess, $currentTeam, request()->header('CF-IPCountry'))->isActive('new-search'); // true ✅


         // Objects may implement the `HasFeatures` trait and the following API is not available...

         $obj = new class (['id' => 55]) extends User {
             use HasFeature;
         };

         $obj->featureIsActive('new-login');


         // Everytime we check a feature, there is potential the driver has to make a request,
         // hit a database, etc. To reduce that count, we may eagerly load feature
         // states in a service provider, middleware, in a controller, etc.

         // Note: I hate this API. New suggestion below to improve this - just not yet implemented.

         Feature::load([
             'new-faster-api',
             'new-login' => [
                 [$jess],
                 [$james],
                 [$tim],
             ],
             'new-search' => [
                 [$jess, $currentTeam, request()->header('CF-IPCountry')],
                 [$james, $currentTeam, request()->header('CF-IPCountry')],
                 [$tim, $currentTeam, request()->header('CF-IPCountry')],
             ],
         ]);


         // Perhaps we could create a pending object using the destructor pattern here instead...
         //
         // Feature::load('new-faster-api')
         //     ->andLoad('new-login')
         //     ->for($jess)->andFor($james)->andFor($tim)
         //     ->andLoad('new-search')
         //     ->for($jess, $currentTeam, request()->header('CF-IPCountry'))
         //     ->andFor($james, $currentTeam, request()->header('CF-IPCountry'))
         //     ->andFor($tim, $currentTeam, request()->header('CF-IPCountry'));

         // we may want a `forMany` method here and in other bits...

         // Feature::load('new-faster-api')
         //     ->andLoad('new-login')
         //     ->forMany([[$jess], [$james], [$tim]])
         //     ->andLoad('new-search')
         //     ->forMany([
         //         [$jess, $currentTeam, request()->header('CF-IPCountry')]
         //         [$james, $currentTeam, request()->header('CF-IPCountry')]
         //         [$tim, $currentTeam, request()->header('CF-IPCountry')]
         //     ])



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
     }
}

