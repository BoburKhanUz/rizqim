<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreStatus;
use App\Events\StoreBalanceLow;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreTopupRequest;
use App\Models\User;
use App\Services\Finance\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LedgerStoreBlockingTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::create([
            'name' => 'Customer',
            'phone' => '+998905550000',
            'password' => bcrypt('password'),
        ]);
    }

    private function store(string $status, float $debt, float $creditLimit = -100000): Store
    {
        return Store::forceCreate([
            'name' => 'Blocking Cafe',
            'status' => $status,
            'balance' => $debt,
            'credit_limit' => $creditLimit,
            'commission_rate' => 10,
        ]);
    }

    private function order(Store $store, array $attributes = []): Order
    {
        $order = Order::create(array_merge([
            'store_id' => $store->id,
            'user_id' => $this->customer->id,
            'total_price' => 100000,
            'commission_amount' => 10000,
            'status' => OrderStatus::DELIVERED->value,
            'payment_method' => 'cash',
            'payment_owner' => 'STORE',
            'payment_status' => PaymentStatus::PAID->value,
        ], $attributes));

        return Order::findOrFail($order->id);
    }

    private function topup(Store $store, float $amount): StoreTopupRequest
    {
        return StoreTopupRequest::create([
            'store_id' => $store->id,
            'amount' => $amount,
            'provider' => 'payme',
            'status' => 'paid',
        ]);
    }

    public function test_topup_unblocks_once_debt_is_back_under_the_credit_limit(): void
    {
        $store = $this->store(StoreStatus::PAYMENT_REQUIRED->value, 120000);
        $ledger = app(LedgerService::class);

        // 120k -> 110k: still at/over the 100k limit.
        $ledger->creditTopup($this->topup($store, 10000), 'Payme');
        $this->assertSame(StoreStatus::PAYMENT_REQUIRED, $store->fresh()->status);

        // 110k -> 90k: under the limit, so unblocked without having to prepay a 100k surplus.
        $ledger->creditTopup($this->topup($store, 20000), 'Payme');
        $this->assertEquals(90000, (float) $store->fresh()->balance);
        $this->assertSame(StoreStatus::ACTIVE, $store->fresh()->status);
    }

    public function test_keshbek_settlement_paying_off_debt_unblocks_the_store(): void
    {
        $store = $this->store(StoreStatus::PAYMENT_REQUIRED->value, 160000, -150000);
        $order = $this->order($store, [
            'total_price' => 50000,
            'commission_amount' => 5000,
            'payment_method' => 'keshbek',
            'payment_owner' => 'PLATFORM',
        ]);

        app(LedgerService::class)->processOrderDelivered($order);

        // 160k + 5k commission - 50k keshbek payoff = 115k < 150k limit
        $this->assertEquals(115000, (float) $store->fresh()->balance);
        $this->assertSame(StoreStatus::ACTIVE, $store->fresh()->status);
    }

    public function test_commission_blocks_an_active_store_at_its_credit_limit(): void
    {
        Event::fake([StoreBalanceLow::class]);
        $store = $this->store(StoreStatus::ACTIVE->value, 95000);

        app(LedgerService::class)->processOrderDelivered($this->order($store));

        $this->assertSame(StoreStatus::PAYMENT_REQUIRED, $store->fresh()->status);
        Event::assertDispatchedTimes(StoreBalanceLow::class, 1);
    }

    public function test_commission_does_not_turn_a_finance_hold_into_a_payable_block(): void
    {
        Event::fake([StoreBalanceLow::class]);
        $store = $this->store(StoreStatus::FINANCE_HOLD->value, 95000);

        app(LedgerService::class)->processOrderDelivered($this->order($store));
        app(LedgerService::class)->creditTopup($this->topup($store, 100000), 'Payme');

        // Paying the debt must not lift a manual finance hold.
        $this->assertSame(StoreStatus::FINANCE_HOLD, $store->fresh()->status);
        Event::assertNotDispatched(StoreBalanceLow::class);
    }
}
