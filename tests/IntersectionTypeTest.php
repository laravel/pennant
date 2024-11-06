<?php

namespace Tests;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;

class IntersectionTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pennant.default', 'database');

        DB::enableQueryLog();
    }

    public function testCanRetrieveAllFeaturesForDifferingScopeTypes(): void
    {
        Feature::define('user', fn (User $user) => 1);
        Feature::define('nullable-user', fn (?User $user) => 2);
        Feature::define('team', fn (Team $team) => 3);
        Feature::define('nullable-team', fn (?Team $team) => 4);
        Feature::define('mixed', fn (mixed $v) => 5);
        Feature::define('none', fn ($v) => 6);
        Feature::define('array', fn (array $t) => 7);
        Feature::define('string', fn (string $str) => 8);
        Feature::define('team-or-user', fn (Team|User $model) => 9);
        Feature::define('team-or-user-intersection', fn (Team|(Authenticatable&Authorizable) $model) => 10);

        $features = Feature::for(new User)->all();
        $this->assertSame([
            'user' => 1,
            'nullable-user' => 2,
            'mixed' => 5,
            'none' => 6,
            'team-or-user' => 9,
            'team-or-user-intersection' => 10,
        ], $features);
        $this->assertCount(2, DB::getQueryLog());
        $this->assertCount(6, DB::table('features')->get()); // query

        $features = Feature::for(new Team)->all();
        $this->assertSame([
            'team' => 3,
            'nullable-team' => 4,
            'mixed' => 5,
            'none' => 6,
            'team-or-user' => 9,
            'team-or-user-intersection' => 10,
        ], $features);
        $this->assertCount(5, DB::getQueryLog());
        $this->assertCount(12, DB::table('features')->get()); // query

        $features = Feature::for('scope')->all();
        $this->assertSame([
            'mixed' => 5,
            'none' => 6,
            'string' => 8,
        ], $features);
        $this->assertCount(8, DB::getQueryLog());
        $this->assertCount(15, DB::table('features')->get()); // query
    }
}
