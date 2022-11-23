<?php

namespace Tests\Unit;

use Laravel\Package\LaravelPackage;
use PHPUnit\Framework\TestCase;

class UnitTest extends TestCase
{
    public function test_it_runs_migrations_by_default()
    {
        $this->assertTrue(LaravelPackage::$runsMigrations);
    }
}
