<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Device;
use App\Models\RefreshToken;
use App\Models\Store;
use App\Models\User;
use App\Services\Sms\SmsServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Device-bound sessions for the native apps: opt-in at login, short-lived access tokens, rotating refresh tokens with
 * reuse detection, logout of one device or everywhere. The Mini App's login (no `device`) is unchanged.
 */
class AuthRefreshTokenTest extends TestCase
{
    use RefreshDatabase;
    use AccessFixtures;

    private const BOT_TOKEN = '123456:TEST-bot-token';
    private const BOT_USERNAME = 'rizqim_test_bot';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => self::BOT_TOKEN,
            'services.telegram.bot_username' => self::BOT_USERNAME,
        ]);

        Store::create(['name' => 'Refresh Store', 'slug' => 'refresh-store', 'commission_rate' => 10, 'status' => 'active', 'is_active' => true]);
    }

    private function initData(int $telegramId): string
    {
        $fields = [
            'auth_date' => (string) time(),
            'query_id' => 'AAHdF6IQAAAAAN0XohDhrOrc',
            'user' => json_encode(['id' => $telegramId, 'first_name' => 'Ali']),
        ];
        ksort($fields);
        $check = collect($fields)->map(fn ($value, $key) => "{$key}={$value}")->implode("\n");
        $fields['hash'] = hash_hmac('sha256', $check, hash_hmac('sha256', self::BOT_TOKEN, 'WebAppData', true));

        return http_build_query($fields);
    }

    private function telegramLogin(int $telegramId, ?array $device = null)
    {
        $this->withBearer(null);

        return $this->postJson('/api/v1/auth/telegram', array_filter([
            'initData' => $this->initData($telegramId),
            'bot_username' => self::BOT_USERNAME,
            'device' => $device,
        ]));
    }

    private function deviceLogin(int $telegramId = 5550001): array
    {
        return $this->telegramLogin($telegramId, ['type' => 'customer_mobile', 'name' => 'Pixel 8', 'platform' => 'android', 'app_version' => '1.0.0'])
            ->assertOk()
            ->json('data');
    }

    private function refresh(string $refreshToken, string $deviceUuid)
    {
        $this->withBearer(null);

        return $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refreshToken, 'device_uuid' => $deviceUuid]);
    }

    public function test_mini_app_login_without_a_device_keeps_its_exact_shape(): void
    {
        $data = $this->telegramLogin(5550000)->assertOk()->json('data');

        $this->assertSame(['token', 'user', 'store', 'sms_verification_required'], array_keys($data));
        $this->assertSame(0, Device::count());
        $this->assertSame(0, RefreshToken::count());
        $this->assertNull(PersonalAccessToken::first()->device_id);
        // A Mini App token still lives SANCTUM_TOKEN_EXPIRATION (30 days)
        $this->assertTrue(PersonalAccessToken::first()->expires_at->isAfter(now()->addDays(29)));
    }

    public function test_login_with_a_device_adds_a_short_lived_token_and_a_refresh_token(): void
    {
        $data = $this->deviceLogin();

        $this->assertSame(['token', 'user', 'store', 'sms_verification_required', 'token_expires_at', 'refresh_token', 'refresh_token_expires_at', 'device'], array_keys($data));
        $this->assertStringStartsWith('rt_', $data['refresh_token']);
        $this->assertSame('customer_mobile', $data['device']['type']);
        $this->assertArrayNotHasKey('push_token', $data['device']);

        // Stored hashed only
        $this->assertDatabaseMissing('refresh_tokens', ['token_hash' => $data['refresh_token']]);
        $this->assertDatabaseHas('refresh_tokens', ['token_hash' => hash('sha256', $data['refresh_token'])]);

        $this->bearerJson('GET', '/api/v1/user', $data['token'])->assertOk();

        $this->travel(16)->minutes();
        $this->bearerJson('GET', '/api/v1/user', $data['token'])->assertUnauthorized();
    }

    public function test_refresh_rotates_both_tokens(): void
    {
        $login = $this->deviceLogin();

        $next = $this->refresh($login['refresh_token'], $login['device']['uuid'])->assertOk()->json('data');

        $this->assertNotSame($login['refresh_token'], $next['refresh_token']);
        $this->assertNotSame($login['token'], $next['token']);
        $this->assertSame($login['device']['uuid'], $next['device']['uuid']);
        $this->bearerJson('GET', '/api/v1/user', $login['token'])->assertUnauthorized();
        $this->bearerJson('GET', '/api/v1/user', $next['token'])->assertOk();

        // The new refresh token works in turn, still in the same family
        $this->refresh($next['refresh_token'], $login['device']['uuid'])->assertOk();
        $this->assertSame(1, RefreshToken::distinct('family_id')->count('family_id'));
    }

    public function test_reusing_a_spent_refresh_token_revokes_the_whole_family(): void
    {
        $login = $this->deviceLogin();
        $next = $this->refresh($login['refresh_token'], $login['device']['uuid'])->assertOk()->json('data');

        $this->refresh($login['refresh_token'], $login['device']['uuid'])
            ->assertUnauthorized()
            ->assertJsonPath('data.reason', 'refresh_reused');

        // The thief's copy and the real app's newer pair are both dead now
        $this->refresh($next['refresh_token'], $login['device']['uuid'])->assertUnauthorized();
        $this->bearerJson('GET', '/api/v1/user', $next['token'])->assertUnauthorized();
        $this->assertSame(0, RefreshToken::whereNull('revoked_at')->count());
    }

    public function test_a_refresh_token_is_bound_to_its_device(): void
    {
        $login = $this->deviceLogin();
        $other = $this->deviceLogin(5550002);

        $this->refresh($login['refresh_token'], $other['device']['uuid'])->assertUnauthorized()->assertJsonPath('data.reason', 'refresh_invalid');
        // Presented from the wrong device it is treated as stolen: gone for the right device too
        $this->refresh($login['refresh_token'], $login['device']['uuid'])->assertUnauthorized();
        // The other user's session is untouched
        $this->refresh($other['refresh_token'], $other['device']['uuid'])->assertOk();
    }

    public function test_expired_unknown_and_revoked_device_refresh_tokens_are_refused(): void
    {
        $login = $this->deviceLogin();

        $this->refresh('rt_' . str_repeat('x', 64), $login['device']['uuid'])->assertUnauthorized()->assertJsonPath('data.reason', 'refresh_invalid');
        $this->refresh($login['refresh_token'], 'not-a-uuid')->assertUnprocessable();

        $this->travel(31)->days();
        $this->refresh($login['refresh_token'], $login['device']['uuid'])->assertUnauthorized()->assertJsonPath('data.reason', 'refresh_expired');
        $this->travelBack();

        $fresh = $this->deviceLogin(5550003);
        Device::where('uuid', $fresh['device']['uuid'])->update(['revoked_at' => now()]);
        $this->refresh($fresh['refresh_token'], $fresh['device']['uuid'])->assertUnauthorized();
        // A revoked device's tokens stop working at once
        $this->bearerJson('GET', '/api/v1/user', $fresh['token'])->assertUnauthorized();
    }

    public function test_logging_in_again_on_the_same_device_reuses_it(): void
    {
        $first = $this->deviceLogin();

        $this->withBearer(null);
        $second = $this->postJson('/api/v1/auth/telegram', [
            'initData' => $this->initData(5550001),
            'bot_username' => self::BOT_USERNAME,
            'device' => ['type' => 'customer_mobile', 'uuid' => $first['device']['uuid'], 'app_version' => '1.1.0'],
        ])->assertOk()->json('data');

        $this->assertSame($first['device']['uuid'], $second['device']['uuid']);
        $this->assertSame('1.1.0', $second['device']['app_version']);
        $this->assertSame(1, Device::count());
        // Only one live access token per device
        $this->bearerJson('GET', '/api/v1/user', $first['token'])->assertUnauthorized();
    }

    public function test_a_device_uuid_of_another_user_is_never_taken_over(): void
    {
        $victim = $this->deviceLogin(5550010);

        $this->withBearer(null);
        $attacker = $this->postJson('/api/v1/auth/telegram', [
            'initData' => $this->initData(5550011),
            'bot_username' => self::BOT_USERNAME,
            'device' => ['type' => 'customer_mobile', 'uuid' => $victim['device']['uuid']],
        ])->assertOk()->json('data');

        $this->assertNotSame($victim['device']['uuid'], $attacker['device']['uuid']);
        $this->refresh($victim['refresh_token'], $victim['device']['uuid'])->assertOk();
    }

    public function test_terminal_types_cannot_be_registered_at_login(): void
    {
        $this->telegramLogin(5550004, ['type' => 'pos'])->assertUnprocessable();
    }

    public function test_logout_ends_this_device_only(): void
    {
        $phone = $this->deviceLogin();
        $miniApp = $this->telegramLogin(5550001)->assertOk()->json('data.token');

        $this->bearerJson('POST', '/api/v1/auth/logout', $phone['token'])->assertOk();

        $this->bearerJson('GET', '/api/v1/user', $phone['token'])->assertUnauthorized();
        $this->refresh($phone['refresh_token'], $phone['device']['uuid'])->assertUnauthorized();
        $this->bearerJson('GET', '/api/v1/user', $miniApp)->assertOk();

        // A Mini App token logs out the same way
        $this->bearerJson('POST', '/api/v1/auth/logout', $miniApp)->assertOk();
        $this->bearerJson('GET', '/api/v1/user', $miniApp)->assertUnauthorized();
    }

    public function test_logout_all_ends_every_session(): void
    {
        $phone = $this->deviceLogin();
        $miniApp = $this->telegramLogin(5550001)->assertOk()->json('data.token');

        $this->bearerJson('POST', '/api/v1/auth/logout-all', $miniApp)->assertOk();

        $this->bearerJson('GET', '/api/v1/user', $miniApp)->assertUnauthorized();
        $this->bearerJson('GET', '/api/v1/user', $phone['token'])->assertUnauthorized();
        $this->refresh($phone['refresh_token'], $phone['device']['uuid'])->assertUnauthorized();
    }

    public function test_mini_app_logins_never_prune_device_bound_sessions(): void
    {
        $phone = $this->deviceLogin();

        for ($i = 0; $i < 7; $i++) {
            $this->telegramLogin(5550001)->assertOk();
        }

        $this->bearerJson('GET', '/api/v1/user', $phone['token'])->assertOk();
    }

    public function test_refresh_is_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->refresh('rt_nope', '7c7d0b5e-1b7e-4a51-9d9c-2f3b9d2c1a11')->assertUnauthorized();
        }

        $this->refresh('rt_nope', '7c7d0b5e-1b7e-4a51-9d9c-2f3b9d2c1a11')->assertStatus(429);
    }

    public function test_phone_login_with_a_device_gets_refresh_tokens_too(): void
    {
        $sent = [];
        $this->mock(SmsServiceInterface::class)->shouldReceive('send')->andReturnUsing(function ($phone, $message) use (&$sent) {
            preg_match('/\d{6}/', $message, $match);
            $sent[] = $match[0];

            return true;
        });

        $this->postJson('/api/v1/auth/phone/send-code', ['phone' => '+998901234567'])->assertOk();
        $data = $this->postJson('/api/v1/auth/phone/verify', [
            'phone' => '+998901234567',
            'code' => $sent[0],
            'device' => ['type' => 'customer_mobile'],
        ])->assertOk()->json('data');

        $this->assertNotNull($data['refresh_token']);
        $this->assertSame(UserRole::CUSTOMER->value, $data['user']['role']);
        $this->refresh($data['refresh_token'], $data['device']['uuid'])->assertOk();
        $this->assertSame(1, User::count());
    }
}
