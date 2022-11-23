<?php

namespace Tests;

use Illuminate\Support\Facades\Artisan;
use Laravel\Package\LaravelPackage;
use Laravel\Package\LaravelPackageServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function defineEnvironment($app)
    {
        Artisan::call('vendor:publish', ['--tag' => 'laravel-package-assets']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        LaravelPackage::$authUsing = function () {
            return true;
        };
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        LaravelPackage::$authUsing = null;
    }

    protected function getPackageProviders($app)
    {
        return [LaravelPackageServiceProvider::class];
    }
}
