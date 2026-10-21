<?php

namespace Tests\Feature;

use Tests\TestCase;

class AccessCorsTest extends TestCase
{
    private function preflight(string $origin)
    {
        return $this->call('OPTIONS', '/api/v1/user', [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,bypass-tunnel-reminder',
        ]);
    }

    public function test_unknown_origins_are_not_allowed(): void
    {
        $this->assertNotContains('*', config('cors.allowed_origins'));
        $this->assertNotSame('https://evil.example.com', $this->preflight('https://evil.example.com')->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $this->preflight('https://evil.example.com')->headers->get('Access-Control-Allow-Origin'));

        config(['cors.allowed_origins' => ['https://food.example.uz']]);
        $allowOrigin = $this->preflight('https://evil.example.com')->headers->get('Access-Control-Allow-Origin');
        $this->assertNotContains($allowOrigin, ['https://evil.example.com', '*']);

        config(['cors.allowed_origins' => ['https://food.example.uz', 'https://admin.example.uz']]);
        $this->assertNull($this->preflight('https://evil.example.com')->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_configured_origins_are_allowed(): void
    {
        config(['cors.allowed_origins' => ['https://food.example.uz']]);

        $this->assertSame('https://food.example.uz', $this->preflight('https://food.example.uz')->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_the_file_name_and_retry_after_headers_are_readable_cross_origin(): void
    {
        config(['cors.allowed_origins' => ['https://partner.example.uz']]);

        $response = $this->call('GET', '/api/v1/user', [], [], [], ['HTTP_ORIGIN' => 'https://partner.example.uz', 'HTTP_ACCEPT' => 'application/json']);

        $exposed = array_map('trim', explode(',', strtolower((string) $response->headers->get('Access-Control-Expose-Headers'))));
        $this->assertContains('content-disposition', $exposed);
        $this->assertContains('retry-after', $exposed);
    }

    public function test_origins_come_from_env_and_default_to_webapp_and_app_urls(): void
    {
        $original = [
            'CORS_ALLOWED_ORIGINS' => $_SERVER['CORS_ALLOWED_ORIGINS'] ?? null,
            'TELEGRAM_WEBAPP_URL' => $_SERVER['TELEGRAM_WEBAPP_URL'] ?? null,
            'APP_URL' => $_SERVER['APP_URL'] ?? null,
        ];

        try {
            $_SERVER['CORS_ALLOWED_ORIGINS'] = ' https://a.example.uz/ , https://b.example.uz ';
            $config = require config_path('cors.php');
            $this->assertSame(['https://a.example.uz', 'https://b.example.uz'], $config['allowed_origins']);

            $_SERVER['CORS_ALLOWED_ORIGINS'] = '';
            $_SERVER['TELEGRAM_WEBAPP_URL'] = 'https://food-order.example.uz/app/';
            $_SERVER['APP_URL'] = 'https://api.example.uz:8443';
            $config = require config_path('cors.php');
            $this->assertSame(['https://food-order.example.uz', 'https://api.example.uz:8443'], $config['allowed_origins']);
            $this->assertNotContains('*', $config['allowed_origins']);
        } finally {
            foreach ($original as $key => $value) {
                if ($value === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
}
