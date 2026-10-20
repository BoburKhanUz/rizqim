<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthStoreBotLoginTest extends TestCase
{
    use RefreshDatabase;

    private const GLOBAL_TOKEN = '111111:GLOBAL-bot-token';
    private const STORE_TOKEN = '222222:STORE-A-own-bot-token';

    protected Store $storeA;
    protected Store $storeB;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.bot_token' => self::GLOBAL_TOKEN, 'services.telegram.bot_username' => 'rizqim_global_bot']);

        $this->storeA = Store::create(['name' => 'Store A', 'slug' => 'store-a', 'commission_rate' => 10, 'status' => 'active', 'is_active' => true]);
        $this->storeA->forceFill(['telegram_bot_username' => 'store_a_bot', 'telegram_bot_token' => self::STORE_TOKEN])->save();
        $this->storeB = Store::create(['name' => 'Store B', 'slug' => 'store-b', 'commission_rate' => 10, 'status' => 'active', 'is_active' => true]);
    }

    private function signedInitData(string $botToken, int $telegramId): string
    {
        $fields = ['auth_date' => (string) time(), 'user' => json_encode(['id' => $telegramId, 'first_name' => 'Forged'])];
        ksort($fields);
        $dataCheckString = collect($fields)->map(fn ($value, $key) => "{$key}={$value}")->implode("\n");
        $fields['hash'] = hash_hmac('sha256', $dataCheckString, hash_hmac('sha256', $botToken, 'WebAppData', true));

        return http_build_query($fields);
    }

    private function loginViaStoreBot(int $telegramId, ?string $startParam = null)
    {
        return $this->postJson('/api/v1/auth/telegram', [
            'initData' => $this->signedInitData(self::STORE_TOKEN, $telegramId),
            'bot_username' => 'store_a_bot',
            'start_param' => $startParam,
        ]);
    }

    public function test_store_bot_token_cannot_log_in_as_super_admin_or_other_stores_staff(): void
    {
        $superAdmin = User::create(['name' => 'Admin', 'telegram_id' => '8800001', 'role' => UserRole::SUPER_ADMIN]);
        User::create(['name' => 'B Manager', 'telegram_id' => '8800002', 'store_id' => $this->storeB->id, 'role' => UserRole::MANAGER]);

        $this->loginViaStoreBot(8800001)->assertForbidden();
        $this->loginViaStoreBot(8800002)->assertForbidden();

        $this->assertSame(1, User::where('telegram_id', '8800001')->count());
        $this->assertSame(UserRole::SUPER_ADMIN, $superAdmin->fresh()->role);
        $this->assertSame(1, User::where('telegram_id', '8800002')->count());
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_store_bot_logs_in_customers_and_its_own_staff(): void
    {
        User::create(['name' => 'A Courier', 'telegram_id' => '8800003', 'store_id' => $this->storeA->id, 'role' => UserRole::COURIER]);
        User::create(['name' => 'Customer', 'telegram_id' => '8800004', 'role' => UserRole::CUSTOMER]);

        // Staff of another store who also has a customer account gets the customer account, never the staff seat
        User::create(['name' => 'B Courier', 'telegram_id' => '8800005', 'store_id' => $this->storeB->id, 'role' => UserRole::COURIER]);
        $alsoCustomer = User::create(['name' => 'B Courier As Customer', 'telegram_id' => '8800005', 'role' => UserRole::CUSTOMER]);

        $this->loginViaStoreBot(8800003)->assertOk()->assertJsonPath('data.user.role', UserRole::COURIER->value);
        $this->loginViaStoreBot(8800004)->assertOk()->assertJsonPath('data.user.role', UserRole::CUSTOMER->value);
        $this->loginViaStoreBot(8800005)->assertOk()->assertJsonPath('data.user.id', $alsoCustomer->id);
        $this->loginViaStoreBot(8800006)->assertOk()->assertJsonPath('data.user.role', UserRole::CUSTOMER->value);
    }

    public function test_store_bot_cannot_claim_another_stores_invite_or_touch_other_stores_accounts(): void
    {
        $invite = User::create([
            'name' => 'Invited to B',
            'telegram_id' => 'temp_' . uniqid(),
            'store_id' => $this->storeB->id,
            'role' => UserRole::MANAGER,
            'telegram_invite_token' => 'emp_storeBinvite',
        ]);
        $seatInB = User::create(['name' => 'Seat in B', 'telegram_id' => '8800007', 'store_id' => $this->storeB->id, 'role' => UserRole::COURIER]);
        $inviteA = User::create([
            'name' => 'Invited to A',
            'telegram_id' => 'temp_' . uniqid(),
            'store_id' => $this->storeA->id,
            'role' => UserRole::COURIER,
            'telegram_invite_token' => 'emp_storeAinvite',
        ]);

        $this->loginViaStoreBot(8800008, 'invite_emp_storeBinvite')->assertOk()->assertJsonPath('data.user.role', UserRole::CUSTOMER->value);
        $this->assertSame('emp_storeBinvite', $invite->fresh()->telegram_invite_token);

        // Own store's invite works, but the person's seat in store B is left alone
        $this->loginViaStoreBot(8800007, 'invite_emp_storeAinvite')->assertOk()->assertJsonPath('data.user.id', $inviteA->id);
        $this->assertSame('8800007', $seatInB->fresh()->telegram_id);
    }

    public function test_global_bot_still_logs_in_super_admin(): void
    {
        User::create(['name' => 'Admin', 'telegram_id' => '8800009', 'role' => UserRole::SUPER_ADMIN]);

        $this->postJson('/api/v1/auth/telegram', [
            'initData' => $this->signedInitData(self::GLOBAL_TOKEN, 8800009),
            'bot_username' => 'rizqim_global_bot',
        ])->assertOk()->assertJsonPath('data.user.role', UserRole::SUPER_ADMIN->value);
    }
}
