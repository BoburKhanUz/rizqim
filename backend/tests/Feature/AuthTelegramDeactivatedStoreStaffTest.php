<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Review 15: staff of a deactivated store (stores.is_active false) stay refused on Telegram sign-in (404), whichever
 * store the sign-in came through. The refusal is decided on the account the sign-in actually returns.
 */
class AuthTelegramDeactivatedStoreStaffTest extends TestCase
{
    use RefreshDatabase;

    private const GLOBAL_TOKEN = '123456:TEST-global-bot-token';
    private const GLOBAL_BOT = 'rizqim_global_bot';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.bot_token' => self::GLOBAL_TOKEN, 'services.telegram.bot_username' => self::GLOBAL_BOT]);
    }

    private function login(int $telegramId, ?string $startParam = null, string $botUsername = self::GLOBAL_BOT)
    {
        $fields = ['auth_date' => (string) time(), 'user' => json_encode(['id' => $telegramId, 'first_name' => 'Aziz'])];
        ksort($fields);
        $dataCheckString = collect($fields)->map(fn ($value, $key) => "{$key}={$value}")->implode("\n");
        $fields['hash'] = hash_hmac('sha256', $dataCheckString, hash_hmac('sha256', self::GLOBAL_TOKEN, 'WebAppData', true));

        return $this->postJson('/api/v1/auth/telegram', [
            'initData' => http_build_query($fields),
            'bot_username' => $botUsername,
            'start_param' => $startParam,
        ]);
    }

    private function makeStore(string $slug, bool $active, array $extra = []): Store
    {
        return Store::create(['name' => ucfirst($slug), 'slug' => $slug, 'commission_rate' => 10, 'status' => $active ? 'active' : 'suspended', 'is_active' => $active] + $extra);
    }

    public function test_deactivated_store_owner_is_refused_even_with_a_public_active_store_link(): void
    {
        $closed = $this->makeStore('closed-shop', active: false);
        $open = $this->makeStore('open-shop', active: true);
        User::create(['name' => 'Ega', 'telegram_id' => '7200001', 'store_id' => $closed->id, 'role' => UserRole::OWNER]);

        $this->login(7200001)->assertNotFound();
        $this->login(7200001, 'store_' . $open->uuid)->assertNotFound();
        $this->assertSame(0, \Laravel\Sanctum\PersonalAccessToken::count());
    }

    public function test_deactivated_store_owner_who_is_also_a_customer_is_refused_with_zero_active_stores(): void
    {
        // The customer row comes first (a customer later invited as staff of a store that was then deactivated)
        User::create(['name' => 'Mijoz', 'telegram_id' => '7200002', 'store_id' => null, 'role' => UserRole::CUSTOMER]);
        $closed = $this->makeStore('closed-shop2', active: false);
        User::create(['name' => 'Ega', 'telegram_id' => '7200002', 'store_id' => $closed->id, 'role' => UserRole::OWNER]);

        $this->login(7200002)->assertNotFound();
        $this->assertSame(0, \Laravel\Sanctum\PersonalAccessToken::count());
    }

    public function test_deactivated_store_owner_is_refused_on_another_stores_bot_without_its_own_token(): void
    {
        $closed = $this->makeStore('closed-shop3', active: false);
        // A store bot that runs on the platform token (no own token): any staff row may sign in through it
        $this->makeStore('open-shop3', active: true, extra: ['telegram_bot_username' => 'open_shop3_bot']);
        User::create(['name' => 'Ega', 'telegram_id' => '7200003', 'store_id' => $closed->id, 'role' => UserRole::OWNER]);

        $this->login(7200003, null, 'open_shop3_bot')->assertNotFound();
    }

    public function test_staff_of_an_active_store_and_customers_still_sign_in(): void
    {
        $open = $this->makeStore('open-shop4', active: true);
        $closed = $this->makeStore('closed-shop4', active: false);
        User::create(['name' => 'Ega', 'telegram_id' => '7200004', 'store_id' => $open->id, 'role' => UserRole::OWNER]);

        $this->login(7200004)->assertOk()
            ->assertJsonPath('data.user.role', UserRole::OWNER->value)
            ->assertJsonPath('data.store.id', $open->id);

        // A plain customer on a deactivated store's link signs in without a store
        $this->login(7200005, 'store_' . $closed->uuid)->assertOk()
            ->assertJsonPath('data.user.role', UserRole::CUSTOMER->value)
            ->assertJsonPath('data.store', null);
    }
}
