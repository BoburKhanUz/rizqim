<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreStatus;
use App\Enums\StoreTransactionType;
use App\Enums\UserRole;
use App\Events\StoreBalanceLow;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PayoutRequest;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Models\User;
use App\Services\Finance\LedgerService;
use App\Services\Order\OrderStatusService;
use App\Services\Order\OrderTransitionException;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderLifecycleRefundTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->admin = User::create([
            'name' => 'Super Admin',
            'phone' => '+998909990099',
            'role' => UserRole::SUPER_ADMIN->value,
            'password' => bcrypt('password'),
        ]);
    }

    private function deliver(Order $order): Order
    {
        $service = app(OrderStatusService::class);
        foreach ([OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::DELIVERED] as $status) {
            $service->transition($order->fresh(), $status, null);
        }

        return $order->fresh();
    }

    private function paidPlatformClickOrder(): Order
    {
        $order = $this->checkout($this->customer, 1, 'click');
        $this->assertSame('PLATFORM', $order->payment_owner);
        $order->payments()->update(['status' => PaymentStatus::PAID->value, 'paid_at' => now()]);
        $order->update(['payment_status' => PaymentStatus::PAID->value]);

        return $this->deliver($order);
    }

    private function refundRows(Order $order): array
    {
        $rows = fn ($ledger) => $ledger::where('order_id', $order->id)->where('type', StoreTransactionType::REFUND->value)
            ->orderBy('id')->pluck('amount')->map(fn ($amount) => Money::of($amount)->toString())->all();

        return ['debt' => $rows(StoreTransaction::class), 'wallet' => $rows(StoreWalletTransaction::class)];
    }

    private function storeBalances(): array
    {
        $store = $this->store->fresh();

        return [$store->debt_balance, $store->wallet_balance];
    }

    public function test_refund_reverses_the_commission_of_a_store_collected_order_without_restoring_stock(): void
    {
        $order = $this->deliver($this->checkout($this->customer, 1));
        $this->assertSame(['5000.00', '0.00'], $this->storeBalances());
        $this->assertSame(9, (int) $this->variant->fresh()->stock);

        $result = app(OrderStatusService::class)->refund($order, $this->admin, 'Taom sifatsiz edi');

        $this->assertTrue($result->changed);
        $this->assertSame(OrderStatus::REFUNDED, $order->fresh()->status);
        $this->assertSame(['0.00', '0.00'], $this->storeBalances());
        $this->assertSame(['debt' => ['-5000.00'], 'wallet' => []], $this->refundRows($order));
        $this->assertDatabaseHas('store_transactions', [
            'order_id' => $order->id,
            'type' => StoreTransactionType::REFUND->value,
            'reference_id' => $order->uuid,
            'performed_by' => $this->admin->id,
            'balance_before' => 5000,
            'balance_after' => 0,
        ]);
        $this->assertSame(9, (int) $this->variant->fresh()->stock);

        $history = OrderStatusHistory::where('order_id', $order->id)->where('status', 'refunded')->sole();
        $this->assertSame($this->admin->id, $history->user_id);
        $this->assertStringContainsString('Taom sifatsiz edi', $history->notes);
        $this->assertStringContainsString('komissiya 5 000 UZS qaytarildi', $history->notes);
    }

    public function test_refund_takes_a_platform_settlement_back_and_flags_the_click_payment_for_a_manual_refund(): void
    {
        $order = $this->paidPlatformClickOrder();
        // P4-23 (netting): the 5k commission (debt) was paid from the 50k collected, 45k reached the wallet
        $this->assertSame(['0.00', '45000.00'], $this->storeBalances());

        $result = app(OrderStatusService::class)->refund($order, $this->admin, 'Mijoz shikoyati');

        $this->assertTrue($result->manualRefundRequired);
        $this->assertSame(['0.00', '0.00'], $this->storeBalances());
        $this->assertSame(['debt' => ['-5000.00', '5000.00'], 'wallet' => ['-45000.00']], $this->refundRows($order));

        // The money is with the platform until someone returns it through Click
        $payment = $order->payments()->sole();
        $this->assertSame(PaymentStatus::PAID, $payment->status);
        $this->assertTrue($payment->payload['manual_refund_required']);
        $this->assertSame('order_refunded', $payment->payload['manual_refund_reason']);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
        $this->assertStringContainsString("qo'lda qaytarilishi kerak", OrderStatusHistory::where('order_id', $order->id)->where('status', 'refunded')->value('notes'));
    }

    public function test_settlement_the_wallet_no_longer_holds_is_added_to_the_debt_and_can_block_the_store(): void
    {
        Event::fake([StoreBalanceLow::class]);
        $order = $this->paidPlatformClickOrder();
        PayoutRequest::create(['store_id' => $this->store->id, 'amount' => 30000, 'card_number' => '8600123456789012', 'status' => 'pending'])
            ->update(['status' => 'completed']);
        $this->store->forceFill(['credit_limit' => -25000])->save();
        // P4-23 (netting): 45k reached the wallet (the 5k commission was paid from the settlement), 30k paid out
        $this->assertSame(['0.00', '15000.00'], $this->storeBalances());

        app(OrderStatusService::class)->refund($order, $this->admin, 'Buyurtma yetib kelmadi');

        // 15k comes out of the wallet, the 30k already paid out becomes debt; the commission and its payoff cancel out
        $this->assertSame(['30000.00', '0.00'], $this->storeBalances());
        $this->assertSame(['debt' => ['-5000.00', '5000.00', '30000.00'], 'wallet' => ['-15000.00']], $this->refundRows($order));
        $this->assertSame(StoreStatus::PAYMENT_REQUIRED, $this->store->fresh()->status);
        Event::assertDispatchedTimes(StoreBalanceLow::class, 1);
    }

    public function test_keshbek_refund_returns_the_keshbek_and_puts_the_paid_off_debt_back(): void
    {
        $this->store->forceFill(['debt_balance' => 30000])->save();
        $customer = $this->makeCustomer(100000);
        $order = $this->deliver($this->checkout($customer, 2, 'keshbek'));

        // Delivery: +10k commission, then the 100k keshbek pays off the 40k debt and the 60k rest goes to the wallet
        $this->assertSame(['0.00', '60000.00'], $this->storeBalances());
        $this->assertEquals(0, (float) $customer->fresh()->balance);

        $result = app(OrderStatusService::class)->refund($order, $this->admin, 'Mijoz shikoyati');

        $this->assertFalse($result->manualRefundRequired);
        $this->assertSame(['30000.00', '0.00'], $this->storeBalances());
        $this->assertSame(['debt' => ['-10000.00', '40000.00'], 'wallet' => ['-60000.00']], $this->refundRows($order));
        $this->assertEquals(100000, (float) $customer->fresh()->balance);
        $this->assertSame(PaymentStatus::REFUNDED, $order->fresh()->payment_status);
        $this->assertSame(PaymentStatus::REFUNDED, $order->payments()->sole()->status);
        $this->assertSame(8, (int) $this->variant->fresh()->stock);
    }

    public function test_refunding_twice_has_a_single_effect(): void
    {
        $customer = $this->makeCustomer(50000);
        $order = $this->deliver($this->checkout($customer, 1, 'keshbek'));
        $service = app(OrderStatusService::class);

        $this->assertTrue($service->refund($order, $this->admin, 'Birinchi')->changed);
        $balances = $this->storeBalances();
        $rows = $this->refundRows($order);

        $this->assertFalse($service->refund($order, $this->admin, 'Ikkinchi')->changed);
        $this->assertSame(['commission', 'settlement', 'added_to_debt'], array_keys(app(LedgerService::class)->reverseDeliveredOrder($order->fresh())));
        $this->assertTrue(collect(app(LedgerService::class)->reverseDeliveredOrder($order->fresh()))->every->isZero());

        $this->assertSame($balances, $this->storeBalances());
        $this->assertSame($rows, $this->refundRows($order));
        $this->assertEquals(50000, (float) $customer->fresh()->balance);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->where('status', 'refunded')->count());
    }

    public function test_only_a_super_admin_can_refund_and_only_a_delivered_order(): void
    {
        $order = $this->deliver($this->checkout($this->customer, 1));
        $service = app(OrderStatusService::class);

        foreach ([$this->owner, $this->manager, $this->courier] as $actor) {
            try {
                $service->refund($order, $actor, 'Qaytarish');
                $this->fail("{$actor->role->value} must not refund.");
            } catch (OrderTransitionException $e) {
                $this->assertSame(403, $e->status);
            }
        }

        try {
            $service->refund($order, $this->admin, '   ');
            $this->fail('A refund needs a reason.');
        } catch (OrderTransitionException $e) {
            $this->assertSame(422, $e->status);
        }

        try {
            $service->refund($this->checkout($this->customer, 1), $this->admin, 'Hali yetkazilmagan');
            $this->fail('Only a delivered order can be refunded.');
        } catch (OrderTransitionException $e) {
            $this->assertSame(422, $e->status);
        }

        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertSame(['5000.00', '0.00'], $this->storeBalances());
        $this->assertSame(['debt' => [], 'wallet' => []], $this->refundRows($order));
    }

    public function test_a_refunded_first_order_keeps_its_referral_reward_and_the_next_order_earns_none(): void
    {
        $referrer = $this->makeCustomer();
        $customer = $this->makeCustomer();
        $customer->referred_by = $referrer->id;
        $customer->save();

        $first = $this->deliver($this->checkout($customer, 1));
        $this->assertEquals(5000, (float) $referrer->fresh()->balance);
        $this->assertSame(['10000.00', '0.00'], $this->storeBalances());

        app(OrderStatusService::class)->refund($first, $this->admin, 'Mijoz shikoyati');

        // Only the commission is reversed; the referral reward and its cost stay
        $this->assertEquals(5000, (float) $referrer->fresh()->balance);
        $this->assertSame(['5000.00', '0.00'], $this->storeBalances());

        $this->deliver($this->checkout($customer, 1));

        $this->assertEquals(5000, (float) $referrer->fresh()->balance);
        $this->assertSame(['10000.00', '0.00'], $this->storeBalances());
    }
}
