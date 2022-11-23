<?php

namespace Tests\Feature;

use Tests\TestCase;

class FeatureTest extends TestCase
{
    public function test_its_web_routes_can_be_reached()
    {
        $response = $this->get('/laravel-package/hello');

        $response->assertStatus(200);
        $response->assertSee('Hello World!');
    }

    public function test_its_api_routes_can_be_reached()
    {
        $response = $this->get('/laravel-package/api/stats');

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'success',
        ]);
    }

    public function test_the_dashboard_can_be_rendered()
    {
        $response = $this->get('/laravel-package');

        $response->assertStatus(200);
        $response->assertSee('<h1>Laravel Package - Laravel</h1>', false);
    }
}
