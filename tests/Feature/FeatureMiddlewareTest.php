<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Laravel\Pennant\Middleware\EnsureFeaturesAreActive;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FeatureMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private EnsureFeaturesAreActive $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new EnsureFeaturesAreActive();
    }

    public function test_it_throws_a_http_exception_if_feature_is_not_defined(): void
    {
        $this->expectException(HttpException::class);
        $this->assertFalse(Feature::active('test'));

        $this->middleware->handle(
            request: $this->createRequest('test', 'get'),
            next: fn () => new Response(),
            features: 'test',
        );
    }

    public function test_it_passes_if_feature_is_defined(): void
    {
        Feature::define('test', true);

        $this->assertFalse(Feature::active('foo'));

        $this->assertEquals(
            Response::HTTP_OK,
            $this->middleware->handle(
                request: $this->createRequest('test', 'get'),
                next: fn () => new Response(),
                features: 'test',
            )->getStatusCode(),
        );
    }

    public function test_it_throws_an_exception_if_one_of_the_features_is_not_active(): void
    {
        $this->expectException(HttpException::class);
        $this->assertFalse(Feature::active('test'));

        $this->middleware->handle(
            $this->createRequest('test', 'get'),
            fn () => new Response(),
            'test', 'another',
        );
    }

    public function test_it_allows_custom_responses(): void
    {
        $this->assertFalse(Feature::active('test'));

        EnsureFeaturesAreActive::whenInactive(fn (Request $request, array $features) => 'test-response');

        $response = $this->middleware->handle(
            $this->createRequest('test', 'get'),
            fn () => new Response(),
            'test', 'another',
        );

        $this->assertEquals('test-response', $response);

        EnsureFeaturesAreActive::whenInactive(null);
    }

    public function test_it_passes_if_all_features_are_enabled(): void
    {
        Feature::define('test', true);
        Feature::define('another', true);

        $this->assertFalse(Feature::active('foo'));

        $this->assertEquals(
            Response::HTTP_OK,
            $this->middleware->handle(
                $this->createRequest('test', 'get'),
                fn () => new Response(),
                'test', 'another',
            )->getStatusCode(),
        );
    }

    public function test_middleware_string_can_be_returned(): void
    {
        $this->assertEquals(
            'Laravel\Pennant\Middleware\EnsureFeaturesAreActive:test,another',
            EnsureFeaturesAreActive::all('test', 'another'),
        );
    }

    protected function createRequest(string $uri, string $method): Request
    {
        $request = SymfonyRequest::create(
            uri: $uri,
            method: $method,
        );

        return Request::createFromBase(
            request: $request,
        );
    }
}
