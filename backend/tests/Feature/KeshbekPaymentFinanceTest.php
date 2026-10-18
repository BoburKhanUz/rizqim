<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Store;
use App\Models\User;
use App\Models\Order;
use App\Enums\OrderStatus;
use App\Services\Finance\LedgerService;
use App\Enums\StoreTransactionType;

class KeshbekPaymentFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected Store $store;
    protected User $customer;
    protected LedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = app(LedgerService::class);

        $this->store = Store::forceCreate([
            'name' => 'Cashback Test Restaurant',
            'slug' => 'cashback-test-restaurant',
            'debt_balance' => 100000.00, // Owes 100,000 UZS
            'wallet_balance' => 0.00,
            'credit_limit' => -150000.00,
            'commission_rate' => 10,
            'status' => 'active',
        ]);

        $this->customer = User::create([
            'name' => 'Customer Name',
            'phone' => '+998909876543',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_keshbek_payment_covers_debt_completely_if_share_is_less_than_debt(): void
    {
        // Order total price = 50,000 UZS. Commission = 10% (5,000 UZS).
        // 1. Commission is deducted: Debt becomes 105,000 UZS.
        // 2. Settlement of full 50,000 UZS covers debt: Debt becomes 55,000 UZS.
        $order = Order::create([
            'uuid' => '00000000-0000-0000-0000-000000000001',
            'short_code' => 'ORD1',
            'store_id' => $this->store->id,
            'user_id' => $this->customer->id,
            'total_price' => 50000.00,
            'status' => OrderStatus::DELIVERED->value,
            'payment_method' => 'keshbek',
            'payment_owner' => 'PLATFORM',
            'payment_status' => 'paid',
        ]);

        $this->ledgerService->processOrderDelivered($order);

        $this->store->refresh();
        $this->assertEquals(55000.00, $this->store->debt_balance);
        $this->assertEquals(0.00, $this->store->wallet_balance);

        $this->assertDatabaseHas('store_transactions', [
            'store_id' => $this->store->id,
            'order_id' => $order->id,
            'type' => StoreTransactionType::COMMISSION->value,
            'amount' => 5000.00,
            'balance_after' => 105000.00,
        ]);

        $this->assertDatabaseHas('store_transactions', [
            'store_id' => $this->store->id,
            'order_id' => $order->id,
            'type' => StoreTransactionType::TOPUP->value,
            'amount' => -50000.00,
            'balance_after' => 55000.00,
        ]);
    }

    public function test_keshbek_payment_covers_debt_and_sends_remainder_to_wallet(): void
    {
        // Order total price = 150,000 UZS. Commission = 10% (15,000 UZS).
        // 1. Commission is deducted: Debt becomes 115,000 UZS.
        // 2. Settlement of full 150,000 UZS covers 115,000 UZS debt, remainder 35,000 UZS goes to wallet.
        $order = Order::create([
            'uuid' => '00000000-0000-0000-0000-000000000002',
            'short_code' => 'ORD2',
            'store_id' => $this->store->id,
            'user_id' => $this->customer->id,
            'total_price' => 150000.00,
            'status' => OrderStatus::DELIVERED->value,
            'payment_method' => 'keshbek',
            'payment_owner' => 'PLATFORM',
            'payment_status' => 'paid',
        ]);

        $this->ledgerService->processOrderDelivered($order);

        $this->store->refresh();
        $this->assertEquals(0.00, $this->store->debt_balance);
        $this->assertEquals(35000.00, $this->store->wallet_balance);

        $this->assertDatabaseHas('store_transactions', [
            'store_id' => $this->store->id,
            'order_id' => $order->id,
            'type' => StoreTransactionType::COMMISSION->value,
            'amount' => 15000.00,
            'balance_after' => 115000.00,
        ]);

        $this->assertDatabaseHas('store_transactions', [
            'store_id' => $this->store->id,
            'order_id' => $order->id,
            'type' => StoreTransactionType::TOPUP->value,
            'amount' => -115000.00,
            'balance_after' => 0.00,
        ]);

        $this->assertDatabaseHas('store_wallet_transactions', [
            'store_id' => $this->store->id,
            'order_id' => $order->id,
            'type' => StoreTransactionType::SETTLEMENT->value,
            'amount' => 35000.00,
            'balance_after' => 35000.00,
        ]);
    }

    public function test_keshbek_payment_goes_directly_to_wallet_if_no_debt(): void
    {
        // Store debt_balance is 0 (no debt)
        $this->store->forceFill(['debt_balance' => 0.00])->save();

        // Order total price = 50,000 UZS. Commission = 10% (5,000 UZS).
        // 1. Commission is deducted: Debt becomes 5,000 UZS.
        // 2. Settlement of full 50,000 UZS covers 5,000 UZS debt, remainder 45,000 UZS goes to wallet.
        $order = Order::create([
            'uuid' => '00000000-0000-0000-0000-000000000003',
            'short_code' => 'ORD3',
            'store_id' => $this->store->id,
            'user_id' => $this->customer->id,
            'total_price' => 50000.00,
            'status' => OrderStatus::DELIVERED->value,
            'payment_method' => 'keshbek',
            'payment_owner' => 'PLATFORM',
            'payment_status' => 'paid',
        ]);

        $this->ledgerService->processOrderDelivered($order);

        $this->store->refresh();
        $this->assertEquals(0.00, $this->store->debt_balance);
        $this->assertEquals(45000.00, $this->store->wallet_balance);

        $this->assertDatabaseHas('store_transactions', [
            'store_id' => $this->store->id,
            'order_id' => $order->id,
            'type' => StoreTransactionType::COMMISSION->value,
            'amount' => 5000.00,
            'balance_after' => 5000.00,
        ]);

        $this->assertDatabaseHas('store_transactions', [
            'store_id' => $this->store->id,
            'order_id' => $order->id,
            'type' => StoreTransactionType::TOPUP->value,
            'amount' => -5000.00,
            'balance_after' => 0.00,
        ]);

        $this->assertDatabaseHas('store_wallet_transactions', [
            'store_id' => $this->store->id,
            'order_id' => $order->id,
            'type' => StoreTransactionType::SETTLEMENT->value,
            'amount' => 45000.00,
            'balance_after' => 45000.00,
        ]);
    }

    public function test_commission_is_first_deducted_from_debit_wallet_then_credit_balance(): void
    {
        // Set store wallet_balance = 8,000 UZS, debt_balance = 0 UZS
        $this->store->forceFill([
            'debt_balance' => 0.00,
            'wallet_balance' => 8000.00,
        ])->save();

        $order = Order::create([
            'uuid' => '00000000-0000-0000-0000-000000000004',
            'short_code' => 'ORD4',
            'store_id' => $this->store->id,
            'user_id' => $this->customer->id,
            'total_price' => 100000.00,
            'status' => OrderStatus::NEW->value, // not delivered, just testing manual commission deduction
        ]);

        // Deduct 10,000 UZS commission
        $this->ledgerService->deductCommission($this->store, 10000.00, $order);

        $this->store->refresh();
        // 8,000 UZS from wallet (wallet_balance should be 0.00)
        // 2,000 UZS from credit (debt_balance should be 2,000.00)
        $this->assertEquals(0.00, $this->store->wallet_balance);
        $this->assertEquals(20000.00 / 10, $this->store->debt_balance); // 2000.00 UZS

        $this->assertDatabaseHas('store_wallet_transactions', [
            'store_id' => $this->store->id,
            'order_id' => $order->id,
            'type' => StoreTransactionType::COMMISSION->value,
            'amount' => -8000.00,
            'balance_after' => 0.00,
        ]);

        $this->assertDatabaseHas('store_transactions', [
            'store_id' => $this->store->id,
            'order_id' => $order->id,
            'type' => StoreTransactionType::COMMISSION->value,
            'amount' => 2000.00,
            'balance_after' => 2000.00,
        ]);
    }
}
