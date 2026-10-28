<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use App\Services\Sms\SmsServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-25: a superadmin / platform account is used through the control center only. Telegram and phone sign-in never issue
 * it a token (the same Telegram id or phone with another account signs into that account), the non-admin tokens it had
 * stop working while admin tokens keep working, and the legacy /superadmin/* endpoints are gone.
 */
class AuthSuperadminControlCenterTest extends TestCase
{
    use RefreshDatabase;
    use AccessFixtures;
    use AdminFixtures;

    private const BOT_TOKEN = '123456:TEST-bot-token';
    private const BOT_USERNAME = 'rizqim_test_bot';
    private const PHONE = '+998901112299';

    /** @var list<string> */
    private array $codes = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.bot_token' => self::BOT_TOKEN, 'services.telegram.bot_username' => self::BOT_USERNAME]);
        $this->mock(SmsServiceInterface::class)->shouldReceive('send')->andReturnUsing(function (string $phone, string $message) {
            preg_match('/\d{6}/', $message, $match);
            $this->codes[] = $match[0];

            return true;
        });

        $this->setUpLifecycleStore();
    }

    private function telegramLogin(int $telegramId, ?array $device = null)
    {
        $fields = ['auth_date' => (string) time(), 'user' => json_encode(['id' => $telegramId, 'first_name' => 'Ali'])];
        ksort($fields);
        $check = collect($fields)->map(fn ($value, $key) => "{$key}={$value}")->implode("\n");
        $fields['hash'] = hash_hmac('sha256', $check, hash_hmac('sha256', self::BOT_TOKEN, 'WebAppData', true));

        return $this->withBearer(null)->postJson('/api/v1/auth/telegram', array_filter([
            'initData' => http_build_query($fields),
            'bot_username' => self::BOT_USERNAME,
            'device' => $device,
        ]));
    }

    private function phoneLogin(array $extra = [])
    {
        $this->travel(61)->seconds();
        $this->withBearer(null)->postJson('/api/v1/auth/phone/send-code', ['phone' => self::PHONE])->assertOk();

        return $this->withBearer(null)->postJson('/api/v1/auth/phone/verify', ['phone' => self::PHONE, 'code' => end($this->codes)] + $extra);
    }

    private function superadmin(array $attributes = []): User
    {
        return $this->makeMember(PlatformRole::SUPERADMIN, true, $attributes);
    }

    public function test_telegram_sign_in_of_a_superadmin_or_platform_member_is_refused_and_creates_nothing(): void
    {
        $this->superadmin(['telegram_id' => '7700001']);
        $this->makeMember(PlatformRole::OPERATOR, true, ['telegram_id' => '7700002']);
        $before = User::count();

        foreach ([7700001, 7700002] as $telegramId) {
            $this->telegramLogin($telegramId)
                ->assertForbidden()
                ->assertJsonPath('data.reason', 'platform_account')
                ->assertJsonPath('message', "Bu hisob uchun Telegram yoki telefon orqali kirish yopiq. Boshqaruv markazidan kiring.");
            // With a device: no refresh token either
            $this->telegramLogin($telegramId, ['type' => 'customer_mobile', 'name' => 'Pixel', 'platform' => 'android'])->assertForbidden();
        }

        $this->assertSame($before, User::count());
        $this->assertDatabaseCount('refresh_tokens', 0);
        $this->assertSame(0, \Laravel\Sanctum\PersonalAccessToken::whereIn('tokenable_id', User::whereNotNull('platform_role')->pluck('id'))->count());
    }

    public function test_a_telegram_id_that_also_holds_a_customer_or_store_account_signs_into_that_account(): void
    {
        $admin = $this->superadmin(['telegram_id' => '7700010']);
        $customer = User::create(['name' => 'Mijoz', 'telegram_id' => '7700010', 'role' => UserRole::CUSTOMER]);

        $this->telegramLogin(7700010)->assertOk()
            ->assertJsonPath('data.user.id', $customer->id)
            ->assertJsonPath('data.user.role', UserRole::CUSTOMER->value);

        // A store account wins over the customer one, as before; the superadmin never does
        $owner = User::create(['name' => 'Ega', 'telegram_id' => '7700010', 'store_id' => $this->store->id, 'role' => UserRole::OWNER]);
        $this->telegramLogin(7700010)->assertOk()->assertJsonPath('data.user.id', $owner->id);

        $this->assertSame(UserRole::SUPER_ADMIN, $admin->fresh()->role);
        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_phone_sign_in_of_a_superadmin_number_is_refused_and_creates_no_customer(): void
    {
        $this->superadmin(['phone' => self::PHONE, 'phone_verified_at' => now()]);
        $before = User::count();

        $this->phoneLogin()
            ->assertForbidden()
            ->assertJsonPath('data.reason', 'platform_account')
            ->assertJsonPath('message', "Bu hisob uchun Telegram yoki telefon orqali kirish yopiq. Boshqaruv markazidan kiring.");
        $this->phoneLogin(['account' => 'staff', 'device' => ['type' => 'web', 'name' => 'Panel']])
            ->assertForbidden()->assertJsonPath('data.reason', 'platform_account');

        $this->assertSame($before, User::count());
        $this->assertDatabaseCount('refresh_tokens', 0);
    }

    public function test_a_phone_that_also_holds_a_customer_or_staff_account_signs_into_that_account(): void
    {
        $this->superadmin(['phone' => self::PHONE, 'phone_verified_at' => now()]);
        $customer = User::create(['name' => 'Mijoz', 'role' => UserRole::CUSTOMER]);
        $customer->forceFill(['phone' => self::PHONE, 'phone_verified_at' => now(), 'phone_verified_via' => User::PHONE_VIA_SMS])->save();

        $this->phoneLogin()->assertOk()->assertJsonPath('data.user.id', $customer->id);

        app(\App\Services\Auth\StaffPhoneLoginService::class)->vouch($this->owner, self::PHONE);
        $this->phoneLogin(['account' => 'staff', 'device' => ['type' => 'web', 'name' => 'Panel']])
            ->assertOk()->assertJsonPath('data.user.id', $this->owner->id);
    }

    public function test_the_tokens_a_superadmin_or_platform_member_got_earlier_stop_working(): void
    {
        $admin = $this->superadmin();
        $operator = $this->makeMember(PlatformRole::SUPPORT);
        // As if issued while they were ordinary accounts, before the control center
        $old = [$admin->createToken('mini-app')->plainTextToken, $operator->createToken('mobile-api', ['*'])->plainTextToken];
        $customerToken = $this->customer->createToken('mini-app')->plainTextToken;

        foreach ($old as $token) {
            foreach (['/api/v1/user', '/api/v1/me/permissions', '/api/v1/orders'] as $uri) {
                $this->bearerJson('GET', $uri, $token)->assertUnauthorized();
            }
            $this->bearerJson('POST', '/api/v1/auth/logout', $token)->assertUnauthorized();
        }
        $this->bearerJson('GET', '/api/v1/user', $customerToken)->assertOk();

        // Admin tokens keep working
        $adminToken = $this->signIn($admin);
        $this->adminJson('GET', '/me', $adminToken)->assertOk();
        $this->adminJson('GET', '/me', $adminToken)->assertOk();
    }

    public function test_a_refresh_token_of_an_account_that_became_a_superadmin_mints_nothing(): void
    {
        $login = $this->telegramLogin(7700020, ['type' => 'customer_mobile', 'name' => 'Pixel', 'platform' => 'android'])->assertOk()->json('data');
        $user = User::findOrFail($login['user']['id']);
        $this->bearerJson('GET', '/api/v1/user', $login['token'])->assertOk();

        $user->forceFill(['role' => UserRole::SUPER_ADMIN])->save();

        // The access token is dead at once and the refresh token cannot get a new one
        $this->bearerJson('GET', '/api/v1/user', $login['token'])->assertUnauthorized();
        $this->withBearer(null)->postJson('/api/v1/auth/refresh', ['refresh_token' => $login['refresh_token'], 'device_uuid' => $login['device']['uuid']])
            ->assertUnauthorized();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_the_legacy_superadmin_endpoints_are_gone(): void
    {
        $admin = $this->superadmin();
        $adminToken = $this->signIn($admin);

        foreach ([['GET', 'dashboard'], ['GET', 'locations'], ['GET', 'stores'], ['POST', 'stores']] as [$method, $path]) {
            $uri = "/api/v1/superadmin/{$path}";
            $this->bearerJson($method, $uri, $adminToken)->assertNotFound();
            $this->bearerJson($method, $uri, $this->owner->createToken('x')->plainTextToken)->assertNotFound();
            $this->actingAs($admin, 'sanctum')->json($method, $uri)->assertNotFound();
        }

        $this->assertEmpty(array_filter(
            collect(app('router')->getRoutes()->getRoutes())->map->uri()->all(),
            fn (string $uri) => str_starts_with($uri, 'api/v1/superadmin'),
        ));
    }
}
