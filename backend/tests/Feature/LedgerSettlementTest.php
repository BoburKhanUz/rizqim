<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreTransactionType;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Models\User;
use App\Services\Finance\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected Store $store;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::create([
            'name' => 'Ledger Cafe',
            'slug' => 'ledger-cafe',
            'status' => 'active',
            'commission_rate' => 10,
            'credit_limit' => -1000000,
        ]);

        $this->customer = User::create([
            'name' => 'Customer',
            'phone' => '+998903330000',
            'password' => bcrypt('password'),
        ]);
    }

    private function deliveredOrder(array $attributes): Order
    {
        $order = Order::create(array_merge([
            'store_id' => $this->store->id,
            'user_id' => $this->customer->id,
            'total_price' => 100000,
            'status' => OrderStatus::DELIVERED->value,
            'payment_method' => 'click',
            'payment_owner' => 'PLATFORM',
            'payment_status' => PaymentStatus::PAID->value,
        ], $attributes));

        return Order::findOrFail($order->id);
    }

    public function test_settlement_charges_the_commission_snapshotted_at_order_time(): void
    {
        // Ordered at 7%; the store's rate has since been raised to 10%.
        $order = $this->deliveredOrder(['commission_amount' => 7000]);

        app(LedgerService::class)->processOrderDelivered($order);

        $this->assertSame(
            [7000.0],
            StoreTransaction::where('order_id', $order->id)
                ->where('type', StoreTransactionType::COMMISSION->value)
                ->pluck('amount')->map(fn ($amount) => (float) $amount)->all()
        );
        $this->assertEquals(7000, (float) $this->store->fresh()->debt_balance);
        $this->assertEquals(100000, (float) $this->store->fresh()->wallet_balance);
    }

    public function test_zero_commission_snapshot_is_charged_as_zero(): void
    {
        // Ordered while the store's rate was 0%.
        $order = $this->deliveredOrder(['commission_amount' => 0]);

        app(LedgerService::class)->processOrderDelivered($order);

        $this->assertSame(0, StoreTransaction::where('order_id', $order->id)
            ->where('type', StoreTransactionType::COMMISSION->value)->count());
        $this->assertEquals(0, (float) $this->store->fresh()->debt_balance);
        $this->assertEquals(100000, (float) $this->store->fresh()->wallet_balance);
    }

    public function test_unpaid_platform_order_marked_delivered_is_not_settled_into_the_wallet(): void
    {
        $order = $this->deliveredOrder([
            'commission_amount' => 10000,
            'payment_status' => PaymentStatus::PENDING->value,
        ]);

        app(LedgerService::class)->processOrderDelivered($order);

        $this->assertEquals(0, (float) $this->store->fresh()->wallet_balance);
        $this->assertSame(0, StoreWalletTransaction::where('order_id', $order->id)
            ->where('type', StoreTransactionType::SETTLEMENT->value)->count());
        // Commission is still charged for the delivered order.
        $this->assertEquals(10000, (float) $this->store->fresh()->debt_balance);
    }
}
