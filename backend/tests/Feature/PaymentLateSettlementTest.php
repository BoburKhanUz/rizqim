<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreTransactionType;
use App\Enums\UserRole;
use App\Events\PaymentPaid;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Models\User;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PaymentLateSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected Store $store;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.click.secret_key' => 'platform-click-key', 'services.payme.secret_key' => 'platform-payme-key']);

        $this->store = Store::forceCreate([
            'name' => 'Late Pay Cafe',
            'status' => 'active',
            'payment_account_type' => 'platform_account',
            'credit_limit' => -1000000,
        ]);
        $this->customer = User::create(['name' => 'Customer', 'phone' => '+998909990001', 'password' => bcrypt('password')]);
    }

    private function order(string $method, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'store_id' => $this->store->id,
            'user_id' => $this->customer->id,
            'total_price' => 50000,
            'commission_amount' => 5000,
            'status' => OrderStatus::NEW->value,
            'order_type' => 'pickup',
            'payment_method' => $method,
            'payment_owner' => 'PLATFORM',
            'payment_status' => PaymentStatus::PENDING->value,
        ], $attributes));
    }

    private function deliverUnpaid(Order $order): void
    {
        $service = app(OrderStatusService::class);
        foreach ([OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::DELIVERED] as $status) {
            $service->transition($order->fresh(), $status, null);
        }
    }

    private function payme(string $method, array $params, string $key = 'platform-payme-key')
    {
        return $this->postJson('/api/v1/payments/payme/webhook', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], [
            'Authorization' => 'Basic ' . base64_encode('Paycom:' . $key),
        ]);
    }

    private function settlements(Order $order): int
    {
        return StoreWalletTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::SETTLEMENT->value)->count();
    }

    public function test_click_payment_arriving_after_delivery_settles_to_the_wallet_once(): void
    {
        $order = $this->order('click');
        $this->deliverUnpaid($order);

        // Delivered unpaid: commission charged, settlement skipped
        $this->assertEquals(0, (float) $this->store->fresh()->wallet_balance);
        $this->assertEquals(5000, (float) $this->store->fresh()->debt_balance);

        $this->postJson('/api/v1/payments/click/webhook', [
            'click_trans_id' => '42', 'service_id' => '1', 'click_paydoc_id' => '1',
            'merchant_trans_id' => $order->uuid, 'merchant_prepare_id' => $order->id,
            'amount' => '50000', 'action' => 1, 'error' => 0, 'sign_time' => '2026-10-18 12:00:00',
            'sign_string' => md5('42' . '1' . 'platform-click-key' . $order->uuid . $order->id . '50000' . '1' . '2026-10-18 12:00:00'),
        ])->assertJson(['error' => 0]);

        $this->assertEquals(50000, (float) $this->store->fresh()->wallet_balance);
        $this->assertEquals(5000, (float) $this->store->fresh()->debt_balance);
        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);

        event(new PaymentPaid(Payment::where('provider', 'click')->firstOrFail()));
        $this->assertSame(1, $this->settlements($order));
        $this->assertEquals(50000, (float) $this->store->fresh()->wallet_balance);
    }

    public function test_payme_late_payment_settles_and_is_then_refused_a_refund(): void
    {
        $order = $this->order('payme');
        $this->deliverUnpaid($order);

        $this->payme('CreateTransaction', ['id' => 'late-1', 'time' => 1760000000000, 'amount' => 5000000, 'account' => ['order_id' => $order->uuid]])
            ->assertJsonPath('result.state', 1);
        $this->payme('PerformTransaction', ['id' => 'late-1'])->assertJsonPath('result.state', 2);

        $this->assertSame(1, $this->settlements($order));
        $this->assertEquals(50000, (float) $this->store->fresh()->wallet_balance);

        // The store has been credited, so the platform cannot hand the money back through Payme.
        $this->payme('CancelTransaction', ['id' => 'late-1', 'reason' => 5])->assertJsonPath('error.code', -31007);
    }

    public function test_paid_platform_order_without_settlement_can_still_be_refunded(): void
    {
        $order = $this->order('payme');
        $this->payme('CreateTransaction', ['id' => 'early-1', 'time' => 1760000000000, 'amount' => 5000000, 'account' => ['order_id' => $order->uuid]]);
        $this->payme('PerformTransaction', ['id' => 'early-1'])->assertJsonPath('result.state', 2);

        $this->payme('CancelTransaction', ['id' => 'early-1', 'reason' => 5])->assertJsonPath('result.state', -2);
        $this->assertSame(PaymentStatus::REFUNDED, Payment::where('provider_transaction_id', 'early-1')->first()->status);
    }

    public function test_payme_refund_after_delivery_refunds_a_store_owned_order_and_reverses_its_commission(): void
    {
        $this->store->update([
            'payment_account_type' => 'own_account',
            'payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'own-cashbox', 'secret_key' => 'store-payme-key']],
        ]);
        $order = $this->order('payme', ['payment_owner' => 'STORE']);
        $this->deliverUnpaid($order);

        $this->payme('CreateTransaction', ['id' => 'own-1', 'time' => 1760000000000, 'amount' => 5000000, 'account' => ['order_id' => $order->uuid]], 'store-payme-key')
            ->assertJsonPath('result.state', 1);
        $this->payme('PerformTransaction', ['id' => 'own-1'], 'store-payme-key')->assertJsonPath('result.state', 2);

        $this->assertEquals(5000, (float) $this->store->fresh()->debt_balance);

        Log::spy();
        $this->payme('CancelTransaction', ['id' => 'own-1', 'reason' => 5], 'store-payme-key')->assertJsonPath('result.state', -2);
        $this->payme('CancelTransaction', ['id' => 'own-1', 'reason' => 5], 'store-payme-key')->assertJsonPath('result.state', -2);

        $payment = Payment::where('provider_transaction_id', 'own-1')->first();
        $this->assertSame(PaymentStatus::REFUNDED, $payment->status);
        $this->assertTrue($payment->payload['refunded_after_delivery']);
        // Payme has already returned the money: nothing is left for a manual refund
        $this->assertArrayNotHasKey('manual_refund_required', $payment->payload);
        $this->assertSame(OrderStatus::REFUNDED, $order->fresh()->status);
        $this->assertSame(PaymentStatus::REFUNDED, $order->fresh()->payment_status);
        $this->assertEquals(0, (float) $this->store->fresh()->debt_balance);
        $this->assertSame(1, StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::REFUND->value)->count());
        $this->assertSame(0, $this->settlements($order));
        $history = OrderStatusHistory::where('order_id', $order->id)->where('status', 'refunded')->sole();
        $this->assertNull($history->user_id);
        $this->assertStringContainsString('Payme', $history->notes);
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'refund after delivery'));
    }

    public function test_payme_can_return_a_platform_orders_money_once_the_admin_refund_took_it_back_from_the_store(): void
    {
        $admin = User::create(['name' => 'Super Admin', 'phone' => '+998909990002', 'role' => UserRole::SUPER_ADMIN->value, 'password' => bcrypt('password')]);
        $order = $this->order('payme');
        $this->payme('CreateTransaction', ['id' => 'adm-1', 'time' => 1760000000000, 'amount' => 5000000, 'account' => ['order_id' => $order->uuid]])
            ->assertJsonPath('result.state', 1);
        $this->payme('PerformTransaction', ['id' => 'adm-1'])->assertJsonPath('result.state', 2);
        $this->deliverUnpaid($order);
        $this->assertSame(1, $this->settlements($order));

        // While the store holds the money, Payme cannot take it back
        $this->payme('CancelTransaction', ['id' => 'adm-1', 'reason' => 5])->assertJsonPath('error.code', -31007);

        app(OrderStatusService::class)->refund($order, $admin, 'Mijoz shikoyati');
        $this->assertEquals(0, (float) $this->store->fresh()->wallet_balance);
        $this->assertEquals(0, (float) $this->store->fresh()->debt_balance);
        $ledgerRows = StoreTransaction::count() + StoreWalletTransaction::count();

        $this->payme('CancelTransaction', ['id' => 'adm-1', 'reason' => 5])->assertJsonPath('result.state', -2);

        $this->assertSame(PaymentStatus::REFUNDED, Payment::where('provider_transaction_id', 'adm-1')->first()->status);
        $this->assertSame(OrderStatus::REFUNDED, $order->fresh()->status);
        $this->assertSame(PaymentStatus::REFUNDED, $order->fresh()->payment_status);
        $this->assertSame($ledgerRows, StoreTransaction::count() + StoreWalletTransaction::count());
    }
}
