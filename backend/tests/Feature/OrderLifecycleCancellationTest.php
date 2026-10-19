<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Services\Order\OrderStatusService;
use App\Services\Order\OrderTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLifecycleCancellationTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    public function test_cancel_restores_stock_exactly_once(): void
    {
        $order = $this->checkout($this->customer, 3);
        $this->assertSame(7, (int) $this->variant->fresh()->stock);

        $this->setStatus($order, $this->manager, 'canceled')->assertOk();
        $this->assertSame(10, (int) $this->variant->fresh()->stock);

        $this->setStatus($order, $this->manager, 'canceled')->assertOk();
        $this->setStatus($order, $this->owner, 'rejected')->assertStatus(422);

        $this->assertSame(10, (int) $this->variant->fresh()->stock);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->where('status', 'canceled')->count());
    }

    public function test_reject_restores_stock(): void
    {
        $order = $this->checkout($this->customer, 4, 'cash', 'delivery');

        $this->setStatus($order, $this->manager, 'rejected')->assertOk();

        $this->assertSame(10, (int) $this->variant->fresh()->stock);
    }

    public function test_keshbek_order_is_refunded_to_the_balance_exactly_once(): void
    {
        $customer = $this->makeCustomer(150000);
        $order = $this->checkout($customer, 2, 'keshbek');
        $this->assertEquals(50000, (float) $customer->fresh()->balance);

        $this->setStatus($order, $this->manager, 'rejected')->assertOk();
        $this->assertEquals(150000, (float) $customer->fresh()->balance);

        $this->setStatus($order, $this->manager, 'rejected')->assertOk();
        $this->setStatus($order, $this->manager, 'canceled')->assertStatus(422);

        $this->assertEquals(150000, (float) $customer->fresh()->balance);
        $this->assertSame(10, (int) $this->variant->fresh()->stock);

        $payment = Payment::where('order_id', $order->id)->sole();
        $this->assertSame(PaymentStatus::REFUNDED, $payment->status);
        $this->assertNotNull($payment->cancelled_at);
        $this->assertEquals(100000, $payment->payload['refunded_to_balance']);
        $this->assertSame(PaymentStatus::REFUNDED, $order->fresh()->payment_status);
    }

    public function test_concurrent_style_double_cancel_through_the_service_has_a_single_effect(): void
    {
        $customer = $this->makeCustomer(100000);
        $order = $this->checkout($customer, 1, 'keshbek');
        $service = app(OrderStatusService::class);

        // Two stale copies of the same order (two tabs / a retried job) both ask for the cancellation
        $first = $service->transition($order, OrderStatus::CANCELED, $this->owner, ['cancel_reason' => 'out_of_stock']);
        $second = $service->transition($order, OrderStatus::CANCELED, $this->owner, ['cancel_reason' => 'out_of_stock']);

        $this->assertTrue($first->changed);
        $this->assertFalse($second->changed);
        $this->assertEquals(100000, (float) $customer->fresh()->balance);
        $this->assertSame(10, (int) $this->variant->fresh()->stock);
    }

    public function test_paid_click_order_is_flagged_for_manual_refund_without_faking_it(): void
    {
        $order = $this->checkout($this->customer, 1, 'click');
        $payment = Payment::where('order_id', $order->id)->sole();
        $payment->update(['status' => PaymentStatus::PAID, 'paid_at' => now(), 'payload' => ['click_trans_id' => '555']]);
        $order->update(['payment_status' => PaymentStatus::PAID->value, 'status' => OrderStatus::ACCEPTED->value]);

        $this->setStatus($order, $this->owner, 'canceled')->assertOk();

        $payment->refresh();
        $this->assertSame(PaymentStatus::PAID, $payment->status);
        $this->assertTrue($payment->payload['manual_refund_required']);
        $this->assertSame('555', $payment->payload['click_trans_id']);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);

        $note = OrderStatusHistory::where('order_id', $order->id)->where('status', 'canceled')->value('notes');
        $this->assertStringContainsString("qo'lda qaytarilishi kerak", $note);
        $this->assertSame(10, (int) $this->variant->fresh()->stock);
    }

    public function test_canceled_or_rejected_orders_never_settle(): void
    {
        $order = $this->makeOrder(OrderStatus::ON_THE_WAY, ['courier_id' => $this->courier->id]);

        $this->setStatus($order, $this->manager, 'canceled')->assertOk();
        $this->actingAs($this->courier, 'sanctum')->postJson("/api/v1/orders/{$order->uuid}/complete")->assertForbidden();
        $this->setStatus($order, $this->owner, 'delivered')->assertStatus(422);

        $this->expectException(OrderTransitionException::class);
        try {
            app(OrderStatusService::class)->transition($order, OrderStatus::DELIVERED, null);
        } finally {
            $this->assertSame(OrderStatus::CANCELED, $order->fresh()->status);
            $this->assertSame(0, StoreTransaction::count());
            $this->assertSame(0, StoreWalletTransaction::count());
        }
    }
}
