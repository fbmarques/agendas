<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
    }

    public function test_spa_fallback_returns_html_for_unknown_routes(): void
    {
        $response = $this->get('/qualquer-rota-que-nao-existe');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function test_api_route_is_registered(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }
}
