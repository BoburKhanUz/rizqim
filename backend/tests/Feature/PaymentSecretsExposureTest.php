<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSecretsExposureTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETS = ['click-SECRET-1', 'payme-SECRET-2', 'uzum-SECRET-3', 'uzum-SECRET-4', 'bot-token-SECRET'];

    protected Store $store;
    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.click.secret_key' => 'platform-click-key']);

        $this->store = Store::forceCreate([
            'name' => 'Secret Cafe',
            'status' => 'active',
            'is_active' => true,
            'payment_account_type' => 'own_account',
            'min_order_amount' => 0,
            'open_time' => '09:00:00',
            'close_time' => '22:00:00',
            'telegram_bot_token' => '111:bot-token-SECRET',
            'payment_settings' => [
                'cash' => ['enabled' => true],
                'click' => ['enabled' => true, 'merchant_id' => 'm-1', 'service_id' => 's-1', 'secret_key' => 'click-SECRET-1'],
                'payme' => ['enabled' => true, 'merchant_id' => 'pm-1', 'secret_key' => 'payme-SECRET-2'],
                'uzum' => ['enabled' => false, 'gateway' => ['api_key' => 'uzum-SECRET-3', 'password' => 'uzum-SECRET-4']],
            ],
        ]);

        $this->owner = $this->member(UserRole::OWNER, '+998906660001');
    }

    private function member(UserRole $role, string $phone): User
    {
        return User::create([
            'name' => $role->value,
            'phone' => $phone,
            'role' => $role->value,
            'store_id' => $role === UserRole::CUSTOMER ? null : $this->store->id,
            'password' => bcrypt('password'),
        ]);
    }

    private function assertNoSecrets($response, array $secrets = self::SECRETS): void
    {
        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
    }

    private function storedSettings(): array
    {
        return $this->store->fresh()->payment_settings;
    }

    public function test_customer_store_list_and_catalog_never_expose_secrets(): void
    {
        $customer = $this->member(UserRole::CUSTOMER, '+998906660002');

        $list = $this->actingAs($customer)->getJson('/api/v1/stores')
            ->assertOk()
            ->assertJsonPath('data.0.payment_settings.click.enabled', true)
            ->assertJsonPath('data.0.payment_settings.click.merchant_id', 'm-1');
        $this->assertNoSecrets($list);

        $catalog = $this->actingAs($customer)->getJson('/api/v1/catalog?store_id=' . $this->store->uuid)->assertOk();
        $this->assertNoSecrets($catalog);
    }

    public function test_staff_user_endpoint_never_exposes_secrets(): void
    {
        $manager = $this->member(UserRole::MANAGER, '+998906660003');

        $response = $this->actingAs($manager)->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.store.payment_settings.payme.merchant_id', 'pm-1');

        $this->assertNoSecrets($response);
    }

    public function test_internal_reads_still_see_the_secrets(): void
    {
        $store = $this->store->fresh();

        $this->assertSame('click-SECRET-1', $store->payment_settings['click']['secret_key']);
        $this->assertSame('uzum-SECRET-3', $store->payment_settings['uzum']['gateway']['api_key']);
        $this->assertSame('111:bot-token-SECRET', $store->telegram_bot_token);
    }

    public function test_owner_settings_round_trip_keeps_secrets_and_only_a_new_value_replaces_them(): void
    {
        $shown = $this->actingAs($this->owner)->getJson('/api/v1/owner/settings')
            ->assertOk()
            ->assertJsonPath('data.payment_settings.click.has_secret_key', true)
            ->assertJsonPath('data.payment_settings.click.merchant_id', 'm-1')
            ->assertJsonMissingPath('data.payment_settings.click.secret_key');
        $this->assertNoSecrets($shown);

        // The mini-app's own payload (toggles only), then blank and masked secrets: nothing is wiped.
        $payload = fn (array $click) => [
            'is_open' => true,
            'payment_settings' => ['delivery_fee' => 10000, 'click' => $click, 'payme' => ['enabled' => true, 'secret_key' => '********']],
        ];
        $this->actingAs($this->owner)->postJson('/api/v1/owner/settings', $payload(['enabled' => true]))->assertOk();
        $saved = $this->actingAs($this->owner)->postJson('/api/v1/owner/settings', $payload(['enabled' => true, 'secret_key' => '']))->assertOk();
        $this->assertNoSecrets($saved);

        $this->assertSame('click-SECRET-1', $this->storedSettings()['click']['secret_key']);
        $this->assertSame('payme-SECRET-2', $this->storedSettings()['payme']['secret_key']);
        $this->assertSame('uzum-SECRET-3', $this->storedSettings()['uzum']['gateway']['api_key']);

        $this->actingAs($this->owner)->postJson('/api/v1/owner/settings', $payload(['enabled' => true, 'secret_key' => 'click-NEW-5']))->assertOk();
        $this->assertSame('click-NEW-5', $this->storedSettings()['click']['secret_key']);

        // The webhook verifies the store's own orders with the stored (new) key.
        $order = Order::create([
            'store_id' => $this->store->id,
            'user_id' => $this->member(UserRole::CUSTOMER, '+998906660004')->id,
            'total_price' => 50000,
            'status' => OrderStatus::NEW->value,
            'payment_method' => 'click',
            'payment_owner' => 'STORE',
            'payment_status' => PaymentStatus::PENDING->value,
        ]);

        $complete = fn (string $key) => $this->postJson('/api/v1/payments/click/webhook', [
            'click_trans_id' => '77', 'service_id' => 's-1', 'click_paydoc_id' => '1',
            'merchant_trans_id' => $order->uuid, 'merchant_prepare_id' => $order->id,
            'amount' => '50000', 'action' => 1, 'error' => 0, 'sign_time' => '2026-10-18 12:00:00',
            'sign_string' => md5('77' . 's-1' . $key . $order->uuid . $order->id . '50000' . '1' . '2026-10-18 12:00:00'),
        ]);

        $complete('click-SECRET-1')->assertJson(['error' => -1]);
        $complete('click-NEW-5')->assertJson(['error' => 0]);
    }
}
