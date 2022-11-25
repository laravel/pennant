// <?php
//
// namespace Tests\Feature;
//
// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Support\Facades\Auth;
// use Laravel\Feature\Contracts\FeatureScopeable;
// use Laravel\Feature\Feature;
// use Tests\TestCase;
//
// class DemoTest extends TestCase
// {
//     public function test_the_demo()
//     {
//         // Let's assume we only want to scope feature checks to "User" models, for now.
//
//         // Our users...
//
//         $jess = User::make(['id' => 1]);
//         $tim = User::make(['id' => 2]);
//         $james = User::make(['id' => 3]);
//         $team = Team::make(['id' => 4]);
//
//
//         // "new-login" is registered as only being active for Jess...
//
//         Feature::register('new-login', fn (?Model $user) => $user?->is($jess));
//
//         $this->assertTrue(
//             Feature::for($jess)->isActive('new-login') // ✅
//         );
//
//         $this->assertFalse(
//             Feature::isActive('new-login') // ❌
//         );
//
//         $this->assertFalse(
//             Feature::for($james)->isActive('new-login') // ❌
//         );
//
//         $this->assertFalse(
//             Feature::for([$jess, $james])->isActive('new-login') // ❌
//         );
//
//
//
//
//
//         // Jess logs in...
//
//         Auth::login($jess);
//
//         $this->assertTrue(
//             Feature::forTheAuthenticatedUser()->isActive('new-login') // ✅
//         );
//
//
//
//         // Let's programatically activate the feature for James...
//
//         Feature::for($james)->activate('new-login');
//
//         $this->assertTrue(
//             Feature::for($jess)->isActive('new-login') // ✅
//         );
//
//         $this->assertTrue(
//             Feature::for($james)->isActive('new-login') // ✅
//         );
//
//         $this->assertTrue(
//             Feature::for([$jess, $james])->isActive('new-login') // ✅
//         );
//
//         $this->assertTrue(
//             Feature::forTheAuthenticatedUser()->for($james)->isActive('new-login') // ✅
//         );
//
//         $this->assertFalse(
//             Feature::isActive('new-login') // ❌
//         );
//
//         $this->assertFalse(
//             Feature::for($tim)->isActive('new-login') // ❌
//         );
//
//
//
//         // Let's register another feature that is active for everyone...
//
//
//         Feature::register('new-ducks', fn () => true);
//
//
//         $this->assertTrue(
//             Feature::for($jess)->isActive('new-ducks') // ✅
//         );
//
//         $this->assertTrue(
//             Feature::for($james)->isActive('new-ducks') // ✅
//         );
//
//         $this->assertTrue(
//             Feature::for([$jess, $james])->isActive('new-ducks') // ✅
//         );
//
//         $this->assertTrue(
//             Feature::forTheAuthenticatedUser()->for($james)->isActive('new-ducks') // ✅
//         );
//
//         $this->assertTrue(
//             Feature::isActive('new-ducks') // ✅
//         );
//
//         $this->assertTrue(
//             Feature::for($tim)->isActive('new-ducks') // ✅
//         );
//
//         // Let's programmatically deactivate access, just for Jess...
//
//         Feature::for($jess)->deactivate('new-ducks');
//
//         $this->assertFalse(
//             Feature::for($jess)->isActive('new-ducks') // ❌
//         );
//
//         $this->assertTrue(
//             Feature::for($james)->isActive('new-ducks') // ✅
//         );
//
//         $this->assertFalse(
//             Feature::for([$jess, $james])->isActive('new-ducks') // ❌
//         );
//
//         $this->assertFalse(
//             Feature::forTheAuthenticatedUser()->for($james)->isActive('new-ducks') // ❌
//         );
//
//         $this->assertTrue(
//             Feature::isActive('new-ducks') // ✅
//         );
//
//         $this->assertTrue(
//             Feature::for($tim)->isActive('new-ducks') // ✅
//         );
//
//         Feature::register('new-states', function ($foo, $bar, $baz) {
//             return $user->is($jess);
//         });
//
//         // Feature::register('new-states', function ($user) {
//         //     return $user->is($jess);
//         // });
//
//         Feature::for($jess)->isActive('new-states'); // true. cached in "my-feature-flag:1"
//         Feature::for($tim)->isActive('new-states'); // false. cached in "my-feature-flag:3"
//
//         Feature::for($jess)->activate('new-states');
//
//
//
//         Feature::for($jess, $james, $tim)->load(['...', '...']);
//         Feature::for($jess, $james, $tim)->loadMissing(['...', '...']);
//
//         Feature::for($jess)->for($james)->isActive(['new-ducks', 'new-states']);
//         Feature::for($tim)->isActive(['new-ducks', 'new-states']);
//
//
//         $jess->hasFeature('new-ducks', 'QLD');
//
//         public function hasFeature($feature, ...$stuff)
//         {
//             Feature::for($this, ...$stuff)->isActive($feature);
//         }
//
//         Feature::for($jess)->isActive('new-states');
//
//         // only true when active for everyone. Shared context.
//         Feature::for($jess, ['QLD'])->isActive('new-ous');
//
//         // only true when active for everyone. Individual context.
//         // Feature::forAll([
//         //     [$jess, ['QLD', 'NSW']],
//         //     [$tim, ['VIC']],
//         // ])->isActive('new-ous');
//
//         Feature::for($jess, ['foo', 'bar', 'baz'])->isActive('new-ous');
//
//
//         Feature::register('foo', function ($env) {
//             // 'prod'
//             return $env === 'prod';
//         });
//
//         Feature::for(app()->env())->isActive('foo'); // { "foo: true" }
//
//         Feature::register('foo', function ($scope) {
//             if ($scope === 'tim@laravel.com') {
//                 return true;
//             }
//
//             if ($scope instanceof Model && $scope->is($tim)) {
//                 return true;
//             }
//
//             if (is_object($scope)) {
//                 return true;
//             }
//
//             return false;
//         });
//
//         Feature::for('tim@laravel.com')->isActive('foo'); // { "foo:\"tim@laravel.com\"": true }
//         Feature::for($tim)->isActive('foo'); // { "foo:eloquent_model:App\Models\User:3": true }
//         Feature::for(new class () implements FeatureScopeable  {
//             public function toFeatureScopeIdentifier()
//             {
//                 return 'tim@laravel.com';
//             }
//         })->isActive('foo'); // { "foo:tim@laravel.com": true }
//
//         Feature::initially('experimental-delivery', function ($invoice) {
//             if ($invoice->highValue()) {
//                 return false;
//             }
//
//             return Lottery::odds(5 / 100);
//         });
//
//         php artisan feature:purge --inactive
//         php artisan feature:toggle --inactive
//
//         Feature::forTheAuthenticatedUser()->activate('new-search');
//
//         Feature::remember('new-design', function ($plan) {
//             if ($plan->isExpensive()) {
//                 return false;
//             }
//
//             return true;
//         });
//
//         Feature::register('new-fast-api', function ($user) {
//             if ($user->is($jess)) {
//                 return true;
//             }
//
//             return config('features.fast_api_odds');
//         });
//
//         Feature::for('robby')->deactivate('new-fast-api');
//
//         Feature::for('robby')->isActive('new-fast-api'); // always `false`
//         Feature::for($foo)->isActive('new-fast-api'); // random every time
//
//
//
//         Feature::for($jess)->isActive('new-translations');
//
//         // HTTP POST switch.io
//         // { id: 1, name: "Jess", "age": 38 }
//
//         if ($_POST['age'] === 38) {
//             return true;
//         }
//
//         if (request()->user()->plan->hasFeature('new-fast-deploy')) {
//             //
//         }
//
//
//         if ($invoice->hasFeature('experimental-delivery')) {
//             //
//         }
//
//         if ($invoice->hasFeature('experimental-delivery')) { // same as above
//             //
//         }
//
//
//
//
//
//
//
//
//
//         // Questions...
//
//
//         // What should we do in the following scenario...
//
// //        Feature::register('foo', fn ($user) => $user->is($tim));
// //
// //        Feature::isActive('foo');  // ❌ this value has been remebered for next time.
// //
// //        Feature::for($tim)->isActive('foo'); // ✅ this value has been remembered for next time.
// //
// //        Feature::globally()->activate('foo'); // turn the feature on globally.
// //
// //        Feature::isActive('foo');  // ✅ this value has been remebered for next time.
// //
// //        Feature::for($tim)->isActive('foo'); // ❓ Should this now be active for Tim?
// //
// //        // Should we introduce modifiers?
// //
// //        Feature::globally()->activate('foo')->flushingExisting();
// //        Feature::globally()->activate('foo')->rememberExisting();
//     }
//
// }
//
// class Team extends Model
// {
//     protected $guarded = [];
// }
