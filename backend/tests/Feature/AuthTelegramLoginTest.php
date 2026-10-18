<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthTelegramLoginTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = '123456:TEST-bot-token';
    private const BOT_USERNAME = 'rizqim_test_bot';

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => self::BOT_TOKEN,
            'services.telegram.bot_username' => self::BOT_USERNAME,
        ]);

        $this->store = Store::create([
            'name' => 'Login Test Store',
            'slug' => 'login-test-store',
            'commission_rate' => 10,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function signedInitData(array $user, ?int $authDate = null): string
    {
        $fields = [
            'auth_date' => (string) ($authDate ?? time()),
            'query_id' => 'AAHdF6IQAAAAAN0XohDhrOrc',
            'user' => json_encode($user),
        ];

        ksort($fields);
        $dataCheckString = collect($fields)->map(fn ($value, $key) => "{$key}={$value}")->implode("\n");
        $secretKey = hash_hmac('sha256', self::BOT_TOKEN, 'WebAppData', true);
        $fields['hash'] = hash_hmac('sha256', $dataCheckString, $secretKey);

        return http_build_query($fields);
    }

    private function login(string $initData, ?string $startParam = null)
    {
        return $this->postJson('/api/v1/auth/telegram', [
            'initData' => $initData,
            'bot_username' => self::BOT_USERNAME,
            'start_param' => $startParam,
        ]);
    }

    private function mockInitData(int $telegramId): string
    {
        return 'mock_data=true&user=' . rawurlencode(json_encode(['id' => $telegramId, 'first_name' => 'Mock'])) . '&auth_date=' . time() . '&hash=mock';
    }

    private function useLocalEnvironment(bool $allowMock): void
    {
        $this->app['env'] = 'local';
        config(['app.env' => 'local', 'telegram.allow_mock_auth' => $allowMock]);
    }

    public function test_new_user_with_former_backdoor_telegram_id_gets_customer_role(): void
    {
        $response = $this->login($this->signedInitData(['id' => 1328662483, 'first_name' => 'Bobur']));

        $response->assertOk()->assertJsonPath('data.user.role', UserRole::CUSTOMER->value);
        $this->assertDatabaseHas('users', ['telegram_id' => '1328662483', 'role' => UserRole::CUSTOMER->value]);
    }

    public function test_new_users_with_former_mock_staff_ids_get_customer_role(): void
    {
        foreach ([999999999, 777777777] as $telegramId) {
            $this->login($this->signedInitData(['id' => $telegramId, 'first_name' => 'Test']))
                ->assertOk()
                ->assertJsonPath('data.user.role', UserRole::CUSTOMER->value);
        }
    }

    public function test_login_never_changes_a_stored_role(): void
    {
        $superAdmin = User::create(['name' => 'Platform Admin', 'telegram_id' => '1328662483', 'role' => UserRole::SUPER_ADMIN]);
        $customer = User::create(['name' => 'Regular Customer', 'telegram_id' => '777777777', 'role' => UserRole::CUSTOMER]);
        $owner = User::create(['name' => 'Store Owner', 'telegram_id' => '5550001', 'store_id' => $this->store->id, 'role' => UserRole::OWNER]);

        $this->login($this->signedInitData(['id' => 1328662483, 'first_name' => 'Admin']))
            ->assertOk()->assertJsonPath('data.user.role', UserRole::SUPER_ADMIN->value);
        $this->login($this->signedInitData(['id' => 777777777, 'first_name' => 'Customer']))
            ->assertOk()->assertJsonPath('data.user.role', UserRole::CUSTOMER->value);
        $this->login($this->signedInitData(['id' => 5550001, 'first_name' => 'Owner']))
            ->assertOk()->assertJsonPath('data.user.role', UserRole::OWNER->value);

        $this->assertSame(UserRole::SUPER_ADMIN, $superAdmin->fresh()->role);
        $this->assertSame(UserRole::CUSTOMER, $customer->fresh()->role);
        $this->assertSame(UserRole::OWNER, $owner->fresh()->role);
    }

    public function test_valid_signature_is_accepted_and_tampered_or_expired_data_is_rejected(): void
    {
        $this->login($this->signedInitData(['id' => 4242001, 'first_name' => 'Valid']))->assertOk();

        $tampered = str_replace('Tampered', 'Hacker', $this->signedInitData(['id' => 4242002, 'first_name' => 'Tampered']));
        $this->login($tampered)->assertUnauthorized();

        $this->login($this->signedInitData(['id' => 4242003, 'first_name' => 'Old'], time() - 601))->assertUnauthorized();

        $this->assertDatabaseMissing('users', ['telegram_id' => '4242002']);
        $this->assertDatabaseMissing('users', ['telegram_id' => '4242003']);
    }

    public function test_mock_init_data_is_rejected_in_local_env_without_explicit_flag(): void
    {
        $this->useLocalEnvironment(allowMock: false);

        $this->login($this->mockInitData(31337))->assertUnauthorized();

        $this->assertDatabaseMissing('users', ['telegram_id' => '31337']);
    }

    public function test_mock_init_data_is_rejected_outside_local_env_even_with_flag(): void
    {
        config(['telegram.allow_mock_auth' => true]);

        $this->login($this->mockInitData(31338))->assertUnauthorized();

        $this->assertDatabaseMissing('users', ['telegram_id' => '31338']);
    }

    public function test_mock_init_data_is_accepted_with_local_env_and_explicit_flag(): void
    {
        $this->useLocalEnvironment(allowMock: true);

        $this->login($this->mockInitData(31339))
            ->assertOk()
            ->assertJsonPath('data.user.role', UserRole::CUSTOMER->value);
    }

    public function test_new_user_registered_via_referral_link_gets_referred_by(): void
    {
        $referrer = User::create(['name' => 'Referrer', 'telegram_id' => '1010101', 'role' => UserRole::CUSTOMER]);

        $response = $this->login($this->signedInitData(['id' => 2020202, 'first_name' => 'Friend']), "rizqim_{$referrer->id}")
            ->assertOk();

        $this->assertSame($referrer->id, User::where('telegram_id', '2020202')->first()->referred_by);
        $this->assertSame($referrer->id, $response->json('data.user.referred_by'));
    }

    public function test_referral_is_only_set_once_and_ignores_invalid_referrers(): void
    {
        $referrer = User::create(['name' => 'Referrer', 'telegram_id' => '1010101', 'role' => UserRole::CUSTOMER]);
        $existing = User::create(['name' => 'Existing', 'telegram_id' => '3030303', 'role' => UserRole::CUSTOMER]);

        // Existing users (including the referrer opening his own link) are never re-attributed
        $this->login($this->signedInitData(['id' => 3030303, 'first_name' => 'Existing']), "ref_{$referrer->id}")->assertOk();
        $this->login($this->signedInitData(['id' => 1010101, 'first_name' => 'Referrer']), "rizqim_{$referrer->id}")->assertOk();
        $this->assertNull($existing->fresh()->referred_by);
        $this->assertNull($referrer->fresh()->referred_by);

        foreach (['rizqim_999999', 'ref_1.5', 'ref_1e1', 'rizqim_'] as $i => $startParam) {
            $telegramId = 4040400 + $i;
            $this->login($this->signedInitData(['id' => $telegramId, 'first_name' => 'New']), $startParam)->assertOk();
            $this->assertNull(User::where('telegram_id', (string) $telegramId)->first()->referred_by, $startParam);
        }
    }

    public function test_pending_referral_parked_by_bot_start_is_applied_to_a_new_user_once(): void
    {
        $referrer = User::create(['name' => 'Referrer', 'telegram_id' => '1010101', 'role' => UserRole::CUSTOMER]);
        Cache::put('referral:pending:6060601', $referrer->id, now()->addDays(30));

        $this->login($this->signedInitData(['id' => 6060601, 'first_name' => 'Friend']))->assertOk();

        $this->assertSame($referrer->id, User::where('telegram_id', '6060601')->first()->referred_by);
        $this->assertFalse(Cache::has('referral:pending:6060601'));
    }

    public function test_start_param_referral_wins_and_invalid_pending_referrals_are_dropped(): void
    {
        $fromLink = User::create(['name' => 'Link Referrer', 'telegram_id' => '1010101', 'role' => UserRole::CUSTOMER]);
        $fromCache = User::create(['name' => 'Cache Referrer', 'telegram_id' => '1010102', 'role' => UserRole::CUSTOMER]);

        Cache::put('referral:pending:6060602', $fromCache->id, now()->addDays(30));
        $this->login($this->signedInitData(['id' => 6060602, 'first_name' => 'A']), "rizqim_{$fromLink->id}")->assertOk();
        $this->assertSame($fromLink->id, User::where('telegram_id', '6060602')->first()->referred_by);
        $this->assertFalse(Cache::has('referral:pending:6060602'));

        foreach ([999999, '12abc', ['id' => 1]] as $i => $bogus) {
            $telegramId = 6060610 + $i;
            Cache::put("referral:pending:{$telegramId}", $bogus, now()->addDays(30));
            $this->login($this->signedInitData(['id' => $telegramId, 'first_name' => 'B']))->assertOk();
            $this->assertNull(User::where('telegram_id', (string) $telegramId)->first()->referred_by);
        }
    }

    public function test_pending_referral_never_changes_existing_users_or_self_refers(): void
    {
        $referrer = User::create(['name' => 'Referrer', 'telegram_id' => '1010101', 'role' => UserRole::CUSTOMER]);
        $original = User::create(['name' => 'Original Referrer', 'telegram_id' => '1010103', 'role' => UserRole::CUSTOMER]);

        $existing = User::create(['name' => 'Existing', 'telegram_id' => '6060603', 'role' => UserRole::CUSTOMER]);
        $existing->referred_by = $original->id;
        $existing->save();

        Cache::put('referral:pending:6060603', $referrer->id, now()->addDays(30));
        Cache::put('referral:pending:1010101', $referrer->id, now()->addDays(30));

        $this->login($this->signedInitData(['id' => 6060603, 'first_name' => 'Existing']))->assertOk();
        $this->login($this->signedInitData(['id' => 1010101, 'first_name' => 'Referrer']))->assertOk();

        $this->assertSame($original->id, $existing->fresh()->referred_by);
        $this->assertNull($referrer->fresh()->referred_by);
    }

    public function test_accepting_employee_invite_does_not_unlink_customer_account(): void
    {
        $otherStore = Store::create(['name' => 'Other Store', 'slug' => 'other-store', 'commission_rate' => 10, 'status' => 'active']);

        $customer = User::create(['name' => 'Customer Self', 'telegram_id' => '8080808', 'role' => UserRole::CUSTOMER]);
        $otherStoreCourier = User::create([
            'name' => 'Courier Elsewhere',
            'telegram_id' => '8080808',
            'store_id' => $otherStore->id,
            'role' => UserRole::COURIER,
        ]);
        $invite = User::create([
            'name' => 'Invited Manager',
            'telegram_id' => 'temp_' . uniqid(),
            'store_id' => $this->store->id,
            'role' => UserRole::MANAGER,
            'telegram_invite_token' => 'emp_invitetoken123',
        ]);

        $this->login($this->signedInitData(['id' => 8080808, 'first_name' => 'Ali']), 'invite_emp_invitetoken123')
            ->assertOk()
            ->assertJsonPath('data.user.id', $invite->id)
            ->assertJsonPath('data.user.role', UserRole::MANAGER->value);

        // store_id IS NULL never satisfies "store_id != X" in SQL, so the global customer account stays linked
        $this->assertSame('8080808', $customer->fresh()->telegram_id);
        $this->assertSame('8080808', $invite->fresh()->telegram_id);
        $this->assertNull($invite->fresh()->telegram_invite_token);
        // Documented behaviour: a staff seat in another store is released
        $this->assertNull($otherStoreCourier->fresh()->telegram_id);
    }
}
