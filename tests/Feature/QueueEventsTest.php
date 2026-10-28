<?php

namespace Tests\Feature;

use Exception;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Config;
use Laravel\Pennant\Feature;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QueueEventsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pennant.default', 'array');
    }

    #[DataProvider('queueEvents')]
    public function test_it_flushes_the_cache_after_queue_events($event)
    {
        $called = 0;
        Feature::define('foo', function () use (&$called) {
            $called++;

            return true;
        });

        Feature::active('foo');
        Feature::active('foo');
        $this->assertSame(1, $called);

        $this->app['events']->dispatch($event(Mockery::mock(Job::class)));

        Feature::active('foo');
        $this->assertSame(2, $called);
    }

    public static function queueEvents()
    {
        return [
            'processed' => [fn ($job) => new JobProcessed('sync', $job)],
            'exception occurred' => [fn ($job) => new JobExceptionOccurred('sync', $job, new Exception)],
            'failed' => [fn ($job) => new JobFailed('sync', $job, new Exception)],
        ];
    }
}
