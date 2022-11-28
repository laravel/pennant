<?php

 namespace Tests\Feature;

 use Illuminate\Database\Eloquent\Model;
 use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Lottery;
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
             Feature::for([$jess, $james])->isActive('new-login') // ❌
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
             Feature::for([$jess, $james])->isActive('new-login') // ✅
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

         // Objects may implement the `HasFeatures` trait and the following API is not available...

         $obj = new class (['id' => 55]) extends User {
             use HasFeature;
         };

         $obj->featureIsActive('new-login');



         // Lotteries may be returned from resolvers to have a random value assigned
         // based on the odds.

         Feature::register('new-checkout', Lottery::odds(1, 1000));

        Feature::for($jess)->isActive('new-checkout'); // 1 in 1000 chance. Result will be remembered.
        Feature::for($james)->isActive('new-checkout'); // 1 in 1000 chance. Result will be remembered.
        Feature::for($tim)->isActive('new-checkout'); // 1 in 1000 chance. Result will be remembered.


         // Everytime we check a feature, there is potential the driver has to make a request,
         // hit a database, etc. To reduce that count, we may eagerly load feature
         // states in a service provider, middleware, in a controller, etc.

         Feature::load([
             'new-faster-api',
             'new-login' => [$jess, $james, $tim],
             'new-search' => [$jess, $james]
         ]);



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

