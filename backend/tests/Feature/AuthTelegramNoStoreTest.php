<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Wave P: a customer signs in to the global marketplace bot without any store (a fresh production with zero active
 * stores answered 404 "Store not found or bot not configured."). Store bots still need their active store.
 */
class AuthTelegramNoStoreTest extends TestCase
{
    use RefreshDatabase;

    private const GLOBAL_TOKEN = '123456:TEST-global-bot-token';
    private const GLOBAL_BOT = 'rizqim_global_bot';
    private const STORE_TOKEN = '654321:TEST-store-own-bot-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.bot_token' => self::GLOBAL_TOKEN, 'services.telegram.bot_username' => self::GLOBAL_BOT]);
    }

    private function signedInitData(int $telegramId, string $botToken = self::GLOBAL_TOKEN): string
    {
        $fields = ['auth_date' => (string) time(), 'user' => json_encode(['id' => $telegramId, 'first_name' => 'Aziz'])];
        ksort($fields);
        $dataCheckString = collect($fields)->map(fn ($value, $key) => "{$key}={$value}")->implode("\n");
        $fields['hash'] = hash_hmac('sha256', $dataCheckString, hash_hmac('sha256', $botToken, 'WebAppData', true));

        return http_build_query($fields);
    }

    private function login(int $telegramId, ?string $startParam = null, string $botUsername = self::GLOBAL_BOT, string $botToken = self::GLOBAL_TOKEN)
    {
        return $this->postJson('/api/v1/auth/telegram', [
            'initData' => $this->signedInitData($telegramId, $botToken),
            'bot_username' => $botUsername,
            'start_param' => $startParam,
        ]);
    }

    private function makeStore(string $slug, bool $active): Store
    {
        return Store::create(['name' => ucfirst($slug), 'slug' => $slug, 'commission_rate' => 10, 'status' => $active ? 'active' : 'suspended', 'is_active' => $active]);
    }

    public function test_customer_signs_in_on_the_global_bot_with_zero_stores(): void
    {
        $this->assertSame(0, Store::count());

        $this->login(7100001)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.store', null)
            ->assertJsonPath('data.user.role', UserRole::CUSTOMER->value)
            ->assertJsonPath('data.user.store_id', null)
            ->assertJsonStructure(['data' => ['token', 'user', 'store', 'sms_verification_required']]);

        $this->assertDatabaseHas('users', ['telegram_id' => '7100001', 'store_id' => null, 'role' => UserRole::CUSTOMER->value]);

        // A returning customer signs in again, still without a store
        $this->login(7100001)->assertOk()->assertJsonPath('data.store', null);
        $this->assertSame(1, User::where('telegram_id', '7100001')->count());
    }

    public function test_the_signed_in_customer_reads_an_empty_marketplace(): void
    {
        $token = $this->login(7100015)->assertOk()->json('data.token');
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/user')->assertOk()->assertJsonPath('data.store', null);
        $this->withToken($token)->getJson('/api/v1/stores?page=1&per_page=10')->assertOk()->assertJsonPath('data', []);
        $this->withToken($token)->getJson('/api/v1/catalog?view=cards&limit=40')->assertOk()->assertJsonPath('data.products', []);
    }

    public function test_customer_signs_in_when_only_inactive_stores_exist(): void
    {
        $this->makeStore('closed-shop', active: false);

        $this->login(7100002)->assertOk()->assertJsonPath('data.store', null)->assertJsonPath('data.user.store_id', null);
    }

    public function test_inactive_or_unknown_store_link_signs_in_without_a_store_and_does_not_leak_which(): void
    {
        $inactive = $this->makeStore('sleeping-shop', active: false);

        $viaInactive = $this->login(7100003, 'store_' . $inactive->uuid)->assertOk()->assertJsonPath('data.store', null);
        $viaUnknown = $this->login(7100004, 'store_' . Str::uuid())->assertOk()->assertJsonPath('data.store', null);

        // Same answer whether the store exists or not
        $this->assertSame($viaInactive->json('message'), $viaUnknown->json('message'));
        $this->assertSame(array_keys($viaInactive->json('data')), array_keys($viaUnknown->json('data')));
        $this->assertNull(User::where('telegram_id', '7100003')->value('store_id'));
        $this->assertNull(User::where('telegram_id', '7100004')->value('store_id'));
    }

    public function test_global_bot_no_longer_answers_with_an_arbitrary_first_active_store(): void
    {
        $this->makeStore('first-shop', active: true);
        $this->makeStore('second-shop', active: true);

        $this->login(7100005)->assertOk()->assertJsonPath('data.store', null)->assertJsonPath('data.user.store_id', null);
        $this->assertNull(User::where('telegram_id', '7100005')->value('store_id'), 'never attached to a store as a side effect');
    }

    public function test_active_store_link_still_returns_that_store_without_attaching_the_customer(): void
    {
        $this->makeStore('first-shop', active: true);
        $linked = $this->makeStore('linked-shop', active: true);

        $this->login(7100006, 'store_' . $linked->uuid)
            ->assertOk()
            ->assertJsonPath('data.store.id', $linked->id)
            ->assertJsonPath('data.user.store_id', null);
    }

    public function test_staff_still_get_their_store_on_the_global_bot(): void
    {
        $this->makeStore('first-shop', active: true);
        $own = $this->makeStore('own-shop', active: true);
        User::create(['name' => 'Ega', 'telegram_id' => '7100007', 'store_id' => $own->id, 'role' => UserRole::OWNER]);

        $this->login(7100007)
            ->assertOk()
            ->assertJsonPath('data.user.role', UserRole::OWNER->value)
            ->assertJsonPath('data.store.id', $own->id);
    }

    public function test_staff_of_a_deactivated_store_are_still_refused_on_the_global_bot(): void
    {
        $closed = $this->makeStore('closed-shop', active: false);
        User::create(['name' => 'Ega', 'telegram_id' => '7100008', 'store_id' => $closed->id, 'role' => UserRole::OWNER]);

        $this->login(7100008)->assertNotFound();
    }

    public function test_invite_link_still_works_with_the_global_bot(): void
    {
        $store = $this->makeStore('invite-shop', active: true);
        $invite = User::create([
            'name' => 'Yangi kassir', 'telegram_id' => 'temp_' . uniqid(), 'store_id' => $store->id,
            'role' => UserRole::CASHIER, 'telegram_invite_token' => 'emp_nostoretoken1',
        ]);

        $this->login(7100009, 'invite_emp_nostoretoken1')
            ->assertOk()
            ->assertJsonPath('data.user.id', $invite->id)
            ->assertJsonPath('data.store.id', $store->id);
    }

    public function test_platform_and_blocked_accounts_are_still_refused_with_zero_stores(): void
    {
        User::create(['name' => 'Admin', 'telegram_id' => '7100010', 'role' => UserRole::SUPER_ADMIN]);
        $this->login(7100010)->assertForbidden()->assertJsonPath('data.reason', 'platform_account');

        $blocked = User::create(['name' => 'Blocked', 'telegram_id' => '7100011', 'role' => UserRole::CUSTOMER]);
        $blocked->forceFill(['blocked_at' => now()])->save();
        $this->login(7100011)->assertForbidden()->assertJsonPath('data.reason', 'account_blocked');
    }

    public function test_invalid_init_data_is_still_rejected_with_zero_stores(): void
    {
        $this->login(7100012, botToken: '999:forged')->assertUnauthorized();
        $this->assertDatabaseMissing('users', ['telegram_id' => '7100012']);
    }

    public function test_store_bot_still_needs_its_active_store(): void
    {
        $sleeping = $this->makeStore('sleeping-bot-shop', active: false);
        $sleeping->forceFill(['telegram_bot_username' => 'sleeping_shop_bot', 'telegram_bot_token' => self::STORE_TOKEN])->save();

        $this->login(7100013, botUsername: 'sleeping_shop_bot', botToken: self::STORE_TOKEN)->assertUnauthorized();
        $this->login(7100014, botUsername: 'sleeping_shop_bot')->assertNotFound();
        $this->assertDatabaseMissing('users', ['telegram_id' => '7100014']);
    }
}
