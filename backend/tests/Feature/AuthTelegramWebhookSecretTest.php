<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTelegramWebhookSecretTest extends TestCase
{
    use RefreshDatabase;

    private function postUpdate(?string $secret = null)
    {
        $headers = $secret === null ? [] : ['X-Telegram-Bot-Api-Secret-Token' => $secret];

        return $this->postJson('/api/v1/telegram/webhook', ['update_id' => 1], $headers);
    }

    public function test_webhook_fails_closed_when_no_secret_is_configured(): void
    {
        config(['services.telegram.webhook_secret' => null]);

        $this->postUpdate()->assertForbidden();
        $this->postUpdate('anything')->assertForbidden();
    }

    public function test_webhook_rejects_missing_or_wrong_secret(): void
    {
        config(['services.telegram.webhook_secret' => 'correct-horse-battery-staple']);

        $this->postUpdate()->assertForbidden();
        $this->postUpdate('wrong-secret')->assertForbidden();
    }

    public function test_webhook_accepts_the_configured_secret(): void
    {
        config(['services.telegram.webhook_secret' => 'correct-horse-battery-staple']);

        $this->postUpdate('correct-horse-battery-staple')
            ->assertOk()
            ->assertJsonPath('status', 'ignored');
    }
}
