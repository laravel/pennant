<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Feature\Events\CheckingUnknownFeature;
use Tests\TestCase;

class DatbaseDriverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_defaults_to_false_for_unknown_values_and_dispatches_unknown_feature_event()
    {
        Event::fake([CheckingUnknownFeature::class]);
        $driver = $this->createManager()->driver('database')->toBaseDriver();

        $result = $driver->isActive(['foo']);

        $this->assertFalse($result);
        Event::assertDispatchedTimes(CheckingUnknownFeature::class, 1);
        Event::assertDispatched(function (CheckingUnknownFeature $event) {
            $this->assertSame('foo', $event->feature);
            $this->assertNull($event->scope);

            return true;
        });
    }

    public function test_it_can_register_default_values()
    {
        $driver = $this->createManager()->driver('database')->toBaseDriver();

        $driver->register('true', fn () => true);
        $driver->register('false', fn () => false);

        $true = $driver->isActive(['true']);
        $false = $driver->isActive(['false']);

        $this->assertTrue($true);
        $this->assertFalse($false);
    }
}
