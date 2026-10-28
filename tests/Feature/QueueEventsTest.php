<?php

namespace Tests\Feature;

use Exception;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Config;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class QueueEventsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('pennant.default', 'array');
    }

    public function test_it_flushes_the_cache_when_a_job_is_processed()
    {
        $called = 0;
        Feature::define('foo', function () use (&$called) {
            $called++;

            return true;
        });

        Feature::active('foo');
        Feature::active('foo');
        $this->assertSame(1, $called);

        event(new JobProcessed('sync', $this->createJob()));

        Feature::active('foo');
        $this->assertSame(2, $called);
    }

    public function test_it_flushes_the_cache_when_a_job_throws_an_exception()
    {
        $called = 0;
        Feature::define('foo', function () use (&$called) {
            $called++;

            return true;
        });

        Feature::active('foo');
        Feature::active('foo');
        $this->assertSame(1, $called);

        event(new JobExceptionOccurred('sync', $this->createJob(), new Exception));

        Feature::active('foo');
        $this->assertSame(2, $called);
    }

    public function test_it_flushes_the_cache_when_a_job_fails()
    {
        $called = 0;
        Feature::define('foo', function () use (&$called) {
            $called++;

            return true;
        });

        Feature::active('foo');
        Feature::active('foo');
        $this->assertSame(1, $called);

        event(new JobFailed('sync', $this->createJob(), new Exception));

        Feature::active('foo');
        $this->assertSame(2, $called);
    }

    protected function createJob(): SyncJob
    {
        return new SyncJob($this->app, '', 'sync', 'sync');
    }
}
