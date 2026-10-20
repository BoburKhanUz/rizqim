<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staging runs the production image with APP_ENV=staging: it passes app:check-production but keeps every other
 * safety rule (the only production-only rule is the APP_ENV value).
 */
class CheckProductionStagingTest extends TestCase
{
    use RefreshDatabase;

    private function safeConfig(string $env): void
    {
        config(['app.env' => $env, 'app.debug' => false, 'telegram.allow_mock_auth' => false, 'services.telegram.webhook_secret' => 'valid_secret-1',
            'services.telegram.webapp_url' => 'https://staging.rizqim.example/', 'app.key' => 'base64:' . base64_encode(str_repeat('k', 32))]);
    }

    public function test_staging_environment_passes_the_check(): void
    {
        $this->safeConfig('staging');

        $this->artisan('app:check-production')->expectsOutputToContain('Staging')->assertSuccessful();
    }

    public function test_other_environments_are_still_refused(): void
    {
        foreach (['local', 'testing', 'development'] as $env) {
            $this->safeConfig($env);
            $this->artisan('app:check-production')->expectsOutputToContain('APP_ENV')->assertFailed();
        }
    }

    public function test_staging_keeps_the_shared_safety_rules(): void
    {
        $this->safeConfig('staging');
        config(['app.debug' => true]);
        $this->artisan('app:check-production')->expectsOutputToContain('APP_DEBUG')->assertFailed();

        $this->safeConfig('staging');
        config(['telegram.allow_mock_auth' => true]);
        $this->artisan('app:check-production')->expectsOutputToContain('TELEGRAM_ALLOW_MOCK_AUTH')->assertFailed();

        $this->safeConfig('staging');
        config(['services.telegram.webapp_url' => 'http://staging.rizqim.example/']);
        $this->artisan('app:check-production')->expectsOutputToContain('TELEGRAM_WEBAPP_URL')->assertFailed();

        $this->safeConfig('staging');
        config(['app.key' => '']);
        $this->artisan('app:check-production')->expectsOutputToContain('APP_KEY')->assertFailed();
    }

    public function test_public_api_docs_warn_on_production_only(): void
    {
        $this->safeConfig('production');
        config(['scramble.access.public' => true]);
        $this->artisan('app:check-production')->expectsOutputToContain('API_DOCS_PUBLIC')->assertSuccessful();

        // Staging may expose the docs on purpose
        $this->safeConfig('staging');
        $this->artisan('app:check-production')->doesntExpectOutputToContain('API_DOCS_PUBLIC')->assertSuccessful();

        $this->safeConfig('production');
        config(['scramble.access.public' => false]);
        $this->artisan('app:check-production')->doesntExpectOutputToContain('API_DOCS_PUBLIC')->assertSuccessful();
    }
}
