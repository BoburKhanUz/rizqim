<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // P4-33: no panel to redirect to; without PARTNER_WEB_URL the root is a plain 404 (WebRootTest)
        config(['services.partner_web.url' => null]);
        $response = $this->get('/');

        $response->assertStatus(404);
    }
}
