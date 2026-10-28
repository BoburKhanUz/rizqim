<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentStorePublicDataTest extends TestCase
{
    use RefreshDatabase;

    private const INTERNAL = [
        'debt_balance', 'wallet_balance', 'credit_limit', 'low_balance_threshold', 'min_payout_reserve', 'commission_rate',
        'payment_account_type', 'settlement_type', 'legal_name', 'tax_number', 'bank_account', 'moderation_note',
        'telegram_admin_chat_id', 'telegram_bot_token',
    ];

    protected Store $open;
    protected Store $overLimit;

    protected function setUp(): void
    {
        parent::setUp();

        $internal = [
            'is_active' => true,
            'status' => 'active',
            'is_open' => true,
            'min_order_amount' => 0,
            'open_time' => '00:00:00',
            'close_time' => '00:00:00',
            'credit_limit' => -100000,
            'commission_rate' => 12.5,
            'payment_account_type' => 'platform_account',
            'settlement_type' => 'manual',
            'legal_name' => 'LEGAL-NAME-1',
            'tax_number' => 'TAX-NUMBER-1',
            'bank_account' => 'BANK-ACCOUNT-1',
            'moderation_note' => 'MODERATION-NOTE-1',
            'telegram_admin_chat_id' => '-100777',
        ];

        $this->open = Store::forceCreate(['name' => 'Open Cafe', 'debt_balance' => 0] + $internal);
        $this->overLimit = Store::forceCreate(['name' => 'Indebted Cafe', 'debt_balance' => 150000] + $internal);
    }

    private function member(UserRole $role, Store $store, string $phone): User
    {
        return User::create([
            'name' => $role->value,
            'phone' => $phone,
            'role' => $role->value,
            'store_id' => $role === UserRole::CUSTOMER || $role === UserRole::SUPER_ADMIN ? null : $store->id,
            'password' => bcrypt('password'),
        ]);
    }

    private function assertPublicStore(array $store): void
    {
        foreach (self::INTERNAL as $key) {
            $this->assertArrayNotHasKey($key, $store);
        }
        $this->assertIsBool($store['is_order_locked']);
    }

    public function test_customer_facing_store_json_drops_internal_fields_and_exposes_is_order_locked(): void
    {
        $customer = $this->member(UserRole::CUSTOMER, $this->open, '+998902020001');

        $list = $this->actingAs($customer)->getJson('/api/v1/stores')->assertOk();
        $stores = collect($list->json('data'))->keyBy('uuid');
        $this->assertCount(2, $stores);
        $stores->each(fn (array $store) => $this->assertPublicStore($store));
        $this->assertFalse($stores[$this->open->uuid]['is_order_locked']);
        $this->assertTrue($stores[$this->overLimit->uuid]['is_order_locked']);

        $catalog = $this->actingAs($customer)->getJson('/api/v1/catalog?store_id=' . $this->open->uuid)->assertOk();
        $this->assertPublicStore($catalog->json('data.store'));

        foreach ([$list, $catalog] as $response) {
            foreach (['LEGAL-NAME-1', 'TAX-NUMBER-1', 'BANK-ACCOUNT-1', 'MODERATION-NOTE-1', '-100777'] as $value) {
                $this->assertStringNotContainsString($value, $response->getContent());
            }
        }
    }

    public function test_staff_user_payload_drops_internal_fields(): void
    {
        $manager = $this->member(UserRole::MANAGER, $this->open, '+998902020002');

        $response = $this->actingAs($manager)->getJson('/api/v1/user')->assertOk();

        $this->assertPublicStore($response->json('data.store'));
        $this->assertPublicStore($response->json('data.user.store'));
    }

    public function test_owner_endpoints_still_return_what_the_owner_screens_read(): void
    {
        $owner = $this->member(UserRole::OWNER, $this->overLimit, '+998902020003');

        $this->actingAs($owner)->getJson('/api/v1/owner/finance')->assertOk()
            ->assertJsonPath('data.debt_balance', 150000)
            ->assertJsonPath('data.credit_limit', -100000)
            ->assertJsonPath('data.commission_rate', 12.5)
            ->assertJsonPath('data.payment_account_type', 'platform_account')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_order_locked', true);

        $this->actingAs($owner)->getJson('/api/v1/owner/settings')->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_order_locked', true)
            ->assertJsonPath('data.payment_account_type', 'platform_account');
    }
}
