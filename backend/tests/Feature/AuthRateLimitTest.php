<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => '123456:TEST-bot-token',
            'services.telegram.bot_username' => 'rizqim_test_bot',
        ]);

        $this->customer = User::create(['name' => 'Throttle User', 'telegram_id' => '9090909', 'role' => UserRole::CUSTOMER]);
    }

    private const LOGINS_PER_IP = 300;
    private const LOGINS_PER_ACCOUNT = 10;

    private function telegramLogin(array $server, ?int $telegramId = null)
    {
        $user = $telegramId === null ? '' : '&user=' . rawurlencode(json_encode(['id' => $telegramId, 'first_name' => 'X']));

        return $this->withServerVariables($server)->postJson('/api/v1/auth/telegram', [
            'initData' => 'auth_date=' . time() . $user . '&hash=invalid',
            'bot_username' => 'rizqim_test_bot',
        ]);
    }

    /**
     * Sends $allowed requests (any status) and asserts the next one is throttled with an Uzbek message.
     */
    private function assertThrottledAfter(int $allowed, callable $request): void
    {
        for ($i = 0; $i < $allowed; $i++) {
            $this->assertNotSame(429, $request()->status(), "Request #" . ($i + 1) . " was throttled too early");
        }

        $response = $request()->assertStatus(429)->assertJsonPath('success', false);
        $this->assertStringContainsString("Juda ko'p urinish", (string) $response->json('message'));
        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    public function test_telegram_login_is_throttled_per_client_ip(): void
    {
        $clientA = ['REMOTE_ADDR' => '203.0.113.10'];

        $this->assertThrottledAfter(self::LOGINS_PER_IP, fn () => $this->telegramLogin($clientA));

        $this->telegramLogin(['REMOTE_ADDR' => '203.0.113.11'])->assertUnauthorized();
    }

    public function test_telegram_login_is_limited_per_account_without_blocking_others_on_a_shared_ip(): void
    {
        $sharedIp = ['REMOTE_ADDR' => '203.0.113.30'];

        $this->assertThrottledAfter(self::LOGINS_PER_ACCOUNT, fn () => $this->telegramLogin($sharedIp, 111111));

        // Other users behind the same address are unaffected, and the bucket cannot be filled from elsewhere
        $this->telegramLogin($sharedIp, 222222)->assertUnauthorized();
        $this->telegramLogin(['REMOTE_ADDR' => '203.0.113.31'], 111111)->assertUnauthorized();
    }

    public function test_clients_behind_the_mini_app_proxy_are_limited_individually(): void
    {
        $viaProxy = fn (string $clientIp) => ['REMOTE_ADDR' => '172.18.0.5', 'HTTP_X_FORWARDED_FOR' => $clientIp];

        $this->assertThrottledAfter(self::LOGINS_PER_IP, fn () => $this->telegramLogin($viaProxy('198.51.100.7')));

        $this->telegramLogin($viaProxy('198.51.100.8'))->assertUnauthorized();
    }

    public function test_untrusted_clients_cannot_dodge_the_limit_with_forwarded_headers(): void
    {
        $counter = 0;
        $spoofing = function () use (&$counter) {
            $counter++;

            return $this->telegramLogin(['REMOTE_ADDR' => '203.0.113.20', 'HTTP_X_FORWARDED_FOR' => "192.0.2.{$counter}"]);
        };

        $this->assertThrottledAfter(self::LOGINS_PER_IP, $spoofing);
    }

    public function test_order_creation_is_throttled_per_user(): void
    {
        $this->assertThrottledAfter(10, fn () => $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', []));
    }

    public function test_promo_validation_is_throttled_per_user(): void
    {
        $this->assertThrottledAfter(20, fn () => $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/promo/validate', []));
    }

    public function test_store_rating_is_throttled_per_user(): void
    {
        $store = Store::create(['name' => 'Rated Store', 'slug' => 'rated-store', 'commission_rate' => 10, 'status' => 'active']);

        $this->assertThrottledAfter(10, fn () => $this->actingAs($this->customer, 'sanctum')->postJson("/api/v1/stores/{$store->id}/rate", []));
    }

    public function test_complaints_are_throttled_per_user(): void
    {
        $this->assertThrottledAfter(5, fn () => $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/complaints', []));
    }

    public function test_limits_are_per_user(): void
    {
        $this->assertThrottledAfter(10, fn () => $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', []));

        $other = User::create(['name' => 'Other Throttle User', 'telegram_id' => '9090910', 'role' => UserRole::CUSTOMER]);
        $this->assertNotSame(429, $this->actingAs($other, 'sanctum')->postJson('/api/v1/orders', [])->status());
    }

    public function test_payment_and_telegram_webhooks_are_not_throttled(): void
    {
        config(['services.telegram.webhook_secret' => 'webhook-secret']);

        for ($i = 0; $i < 70; $i++) {
            $this->assertNotSame(429, $this->postJson('/api/v1/payments/click/webhook', [])->status());
            $this->assertNotSame(429, $this->postJson('/api/v1/payments/payme/webhook', [])->status());
            $this->postJson('/api/v1/telegram/webhook', ['update_id' => $i], ['X-Telegram-Bot-Api-Secret-Token' => 'webhook-secret'])
                ->assertOk();
        }
    }
}
