<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAccountMatchingTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.click.secret_key' => 'platform-click-key', 'services.payme.secret_key' => 'platform-payme-key']);
        $this->customer = User::create(['name' => 'Customer', 'phone' => '+998901010001', 'password' => bcrypt('password')]);
    }

    private function store(array $paymentSettings = [], string $accountType = 'own_account'): Store
    {
        return Store::forceCreate([
            'name' => 'Match Cafe',
            'status' => 'active',
            'payment_account_type' => $accountType,
            'payment_settings' => $paymentSettings,
        ]);
    }

    private function fullCredentials(): array
    {
        return [
            'click' => ['enabled' => true, 'service_id' => 's', 'merchant_id' => 'm', 'secret_key' => 'store-click-key'],
            'payme' => ['enabled' => true, 'merchant_id' => 'pm', 'secret_key' => 'store-payme-key'],
        ];
    }

    private function order(Store $store, string $method, string $owner = 'STORE', array $attributes = []): Order
    {
        return Order::create(array_merge([
            'store_id' => $store->id,
            'user_id' => $this->customer->id,
            'total_price' => 50000,
            'status' => OrderStatus::NEW->value,
            'payment_method' => $method,
            'payment_owner' => $owner,
            'payment_status' => PaymentStatus::PENDING->value,
        ], $attributes));
    }

    private function click(Order $order, string $key, int $action = 1)
    {
        $prepareId = $action === 1 ? (string) $order->id : '';

        return $this->postJson('/api/v1/payments/click/webhook', [
            'click_trans_id' => '7' . $order->id, 'service_id' => 's', 'click_paydoc_id' => '1',
            'merchant_trans_id' => $order->uuid, 'merchant_prepare_id' => $order->id,
            'amount' => '50000', 'action' => $action, 'error' => 0, 'sign_time' => '2026-10-18 12:00:00',
            'sign_string' => md5('7' . $order->id . 's' . $key . $order->uuid . $prepareId . '50000' . $action . '2026-10-18 12:00:00'),
        ]);
    }

    private function paymeCheck(Order $order, string $key)
    {
        return $this->postJson('/api/v1/payments/payme/webhook', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'CheckPerformTransaction',
            'params' => ['amount' => 5000000, 'account' => ['order_id' => $order->uuid]],
        ], ['Authorization' => 'Basic ' . base64_encode('Paycom:' . $key)]);
    }

    public function test_an_order_cannot_be_paid_through_another_provider(): void
    {
        $store = $this->store($this->fullCredentials());
        $cash = $this->order($store, 'cash');

        $this->click($cash, 'store-click-key')->assertJson(['error' => -5]);
        $this->paymeCheck($cash, 'store-payme-key')->assertJsonPath('error.code', -31050);
        $this->paymeCheck($this->order($store, 'click'), 'store-payme-key')->assertJsonPath('error.code', -31050);

        $this->assertSame(PaymentStatus::PENDING, $cash->fresh()->payment_status);
        $this->assertSame(OrderStatus::NEW, $cash->fresh()->status);
    }

    public function test_store_owned_orders_accept_only_the_stores_own_key(): void
    {
        // A STORE-owned order of a store without complete credentials: the platform key must not confirm it.
        $legacy = $this->store(['click' => ['enabled' => true], 'payme' => ['enabled' => true]]);
        $this->click($this->order($legacy, 'click'), 'platform-click-key')->assertJson(['error' => -1]);
        $this->paymeCheck($this->order($legacy, 'payme'), 'platform-payme-key')->assertJsonPath('error.code', -32504);

        $own = $this->store($this->fullCredentials());
        $this->click($this->order($own, 'click'), 'platform-click-key')->assertJson(['error' => -1]);
        $this->paymeCheck($this->order($own, 'payme'), 'platform-payme-key')->assertJsonPath('error.code', -32504);
        $this->click($this->order($own, 'click'), 'store-click-key')->assertJson(['error' => 0]);
        $this->paymeCheck($this->order($own, 'payme'), 'store-payme-key')->assertJsonPath('result.allow', true);
    }

    public function test_cancelled_and_rejected_orders_are_refused_by_both_providers(): void
    {
        $store = $this->store([], 'platform_account');

        foreach ([OrderStatus::CANCELED, OrderStatus::REJECTED] as $status) {
            $this->click($this->order($store, 'click', 'PLATFORM', ['status' => $status->value]), 'platform-click-key', 0)
                ->assertJson(['error' => -9]);
            $this->paymeCheck($this->order($store, 'payme', 'PLATFORM', ['status' => $status->value]), 'platform-payme-key')
                ->assertJsonPath('error.code', -31051);
        }
    }

    public function test_migration_makes_open_online_orders_platform_collected_when_the_store_cannot_collect_them(): void
    {
        $noCredentials = $this->store(['click' => ['enabled' => true], 'payme' => ['enabled' => true]]);
        $withCredentials = $this->store($this->fullCredentials());

        $openClick = $this->order($noCredentials, 'click');
        $openPayme = $this->order($noCredentials, 'payme', 'STORE', ['status' => OrderStatus::PREPARING->value]);
        $ownPayme = $this->order($withCredentials, 'payme');
        $delivered = $this->order($noCredentials, 'click', 'STORE', ['status' => OrderStatus::DELIVERED->value]);
        $cash = $this->order($noCredentials, 'cash');

        (require database_path('migrations/2026_10_18_091000_mark_open_online_orders_platform_collected.php'))->up();

        $this->assertSame('PLATFORM', $openClick->fresh()->payment_owner);
        $this->assertSame('PLATFORM', $openPayme->fresh()->payment_owner);
        $this->assertSame('STORE', $ownPayme->fresh()->payment_owner);
        $this->assertSame('STORE', $delivered->fresh()->payment_owner);
        $this->assertSame('STORE', $cash->fresh()->payment_owner);
    }
}
