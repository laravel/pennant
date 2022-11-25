<?php

namespace Tests\Feature;

use Laravel\Feature\Feature;

class DemoTest
{
    public function test_the_demo()
    {
        $jess = User::make(['id' => 1]);
        $tim = User::make(['id' => 2]);
        $james = User::make(['id' => 3]);

        // Register a feature...

        Feature::toBaseDriver()->register('new-login', fn () => $user);
    }
}
