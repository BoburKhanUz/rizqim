<?php

namespace Tests\Feature;

use App\Enums\CancelReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentPaid;
use App\Jobs\CancelUnpaidOnlineOrdersJob;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Services\Order\OrderStatusService;
use App\Services\Order\OrderTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Cancel reasons: the customer's own cancel (POST /orders/{uuid}/cancel), the reason staff must give, the system's
 * payment_timeout, and the reason in the customer's message.
 */
class OrderCancelReasonTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.bot_token' => '123456:cancel-token']);
        Http::fake(['*' => Http::response(['ok' => true, 'result' => true])]);

        $this->setUpLifecycleStore();
        $this->manager->update(['telegram_id' => '5001']);
        $this->customer->update(['telegram_id' => '5009']);
    }

    protected function cancelAsCustomer(Order $order, array $body = [], $user = null)
    {
        return $this->actingAs($user ?? $this->customer, 'sanctum')->postJson("/api/v1/orders/{$order->uuid}/cancel", $body);
    }

    /**
     * @return list<string> texts sent to a chat so far
     */
    protected function messagesTo(string $chatId): array
    {
        return Http::recorded(fn ($request) => str_ends_with($request->url(), '/sendMessage') && (string) ($request['chat_id'] ?? '') === $chatId)
            ->map(fn ($pair) => $pair[0]['text'])
            ->values()
            ->all();
    }

    public function test_customer_cancels_their_new_cash_order_with_every_cancellation_side_effect(): void
    {
        PromoCode::create(['code' => 'ONCE', 'type' => 'percentage', 'value' => 10, 'min_order_amount' => 0, 'is_active' => true, 'usage_limit' => 1]);
        $order = $this->checkout($this->customer, 3, 'cash', 'pickup', 'ONCE');
        $this->assertSame(7, (int) $this->variant->fresh()->stock);

        $response = $this->cancelAsCustomer($order, ['reason' => 'customer_request', 'note' => 'Rejam o\'zgardi'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $order->uuid)
            ->assertJsonPath('data.status', 'canceled')
            ->assertJsonPath('data.cancel_reason', 'customer_request')
            ->assertJsonPath('data.cancel_note', "Rejam o'zgardi")
            ->assertJsonPath('data.items.0.quantity', 3);
        $this->assertArrayHasKey('product', $response->json('data.items.0.variant'));

        $order->refresh();
        $this->assertSame(OrderStatus::CANCELED, $order->status);
        $this->assertSame($this->customer->id, $order->cancelled_by);
        $this->assertSame(10, (int) $this->variant->fresh()->stock);
        $history = OrderStatusHistory::where('order_id', $order->id)->where('status', 'canceled')->sole();
        $this->assertSame($this->customer->id, $history->user_id);
        $this->assertStringContainsString("Sabab: Mijoz so'rovi", $history->notes);

        // The promo use came back
        $this->checkout($this->makeCustomer(), 1, 'cash', 'pickup', 'ONCE');

        // A customer's own cancel of a new order never costs them cash payment
        $this->assertFalse(Order::cashPickupBanApplies($this->customer->id));
    }

    public function test_reason_defaults_to_customer_request_and_no_other_reason_is_accepted(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->cancelAsCustomer($order, ['reason' => 'no_show'])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->cancelAsCustomer($order, ['reason' => 'payment_timeout'])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->cancelAsCustomer($order, ['note' => str_repeat('a', 501)])->assertStatus(422)->assertJsonValidationErrors('note');
        $this->assertSame(OrderStatus::NEW, $order->fresh()->status);

        $this->cancelAsCustomer($order)->assertOk()
            ->assertJsonPath('data.cancel_reason', 'customer_request')
            ->assertJsonPath('data.cancel_note', null);
    }

    public function test_online_order_with_a_pending_payment_is_cancelled_and_a_later_payment_is_refused(): void
    {
        $order = $this->checkout($this->customer, 1, 'click');
        $this->assertSame(PaymentStatus::PENDING, $order->payment_status);

        $this->cancelAsCustomer($order)->assertOk()->assertJsonPath('data.status', 'canceled');

        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => 'click',
            'amount' => $order->total_price,
            'status' => PaymentStatus::PAID,
            'paid_at' => now(),
        ]);
        event(new PaymentPaid($payment));

        $this->assertSame(OrderStatus::CANCELED, $order->fresh()->status);
        $this->assertSame(0, OrderStatusHistory::where('order_id', $order->id)->where('status', 'accepted')->count());
    }

    public function test_only_the_orders_own_customer_may_cancel_it(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);
        $this->postJson("/api/v1/orders/{$order->uuid}/cancel")->assertUnauthorized();

        $this->cancelAsCustomer($order, [], $this->makeCustomer())->assertForbidden();
        // Staff of the store cancel through /status with a reason, not as the customer
        $this->cancelAsCustomer($order, [], $this->owner)->assertForbidden();
        $this->cancelAsCustomer($order, [], $this->manager)->assertForbidden();

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders/' . Str::uuid() . '/cancel')->assertNotFound();
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders/not-a-uuid/cancel')->assertNotFound();

        $this->assertSame(OrderStatus::NEW, $order->fresh()->status);
    }

    public function test_accepted_paid_or_already_cancelled_orders_cannot_be_cancelled_by_the_customer(): void
    {
        $orders = [
            $this->makeOrder(OrderStatus::ACCEPTED),
            $this->makeOrder(OrderStatus::PREPARING, ['order_type' => 'pickup']),
            $this->makeOrder(OrderStatus::NEW, ['payment_method' => 'click', 'payment_status' => PaymentStatus::PAID->value]),
            $this->makeOrder(OrderStatus::NEW, ['payment_method' => 'keshbek', 'payment_status' => PaymentStatus::PAID->value]),
            $this->makeOrder(OrderStatus::CANCELED, ['cancel_reason' => 'out_of_stock']),
            $this->makeOrder(OrderStatus::DELIVERED),
        ];

        foreach ($orders as $order) {
            $this->travel(1)->minutes(); // past the 5 per minute limit
            $this->cancelAsCustomer($order)
                ->assertStatus(422)
                ->assertJsonPath('success', false)
                ->assertJsonPath('message', "Buyurtmani endi bekor qilib bo'lmaydi: u allaqachon qabul qilingan yoki to'langan.");
        }

        $this->assertSame(OrderStatus::ACCEPTED, $orders[0]->fresh()->status);
        $this->assertSame(CancelReason::OUT_OF_STOCK, $orders[4]->fresh()->cancel_reason);
    }

    public function test_store_accepting_first_wins_over_a_stale_customer_cancel(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);
        $stale = Order::find($order->id);
        $this->setStatus($order, $this->manager, 'accepted')->assertOk();

        try {
            app(OrderStatusService::class)->transition($stale, OrderStatus::CANCELED, $this->customer, [
                'customer' => true,
                'cancel_reason' => CancelReason::CUSTOMER_REQUEST,
            ]);
            $this->fail('An accepted order must not be cancelled by the customer.');
        } catch (OrderTransitionException $e) {
            $this->assertSame(422, $e->status);
        }

        $this->assertSame(OrderStatus::ACCEPTED, $order->fresh()->status);
    }

    public function test_customer_cancel_is_throttled(): void
    {
        $order = $this->makeOrder(OrderStatus::ACCEPTED);

        for ($i = 0; $i < 5; $i++) {
            $this->cancelAsCustomer($order)->assertStatus(422);
        }

        $this->cancelAsCustomer($order)->assertStatus(429);
    }

    public function test_customer_and_staff_are_told_about_a_customer_cancel(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW, ['order_type' => 'pickup']);

        $this->cancelAsCustomer($order, ['note' => 'Vaqtim_yo\'q'])->assertOk();

        $customer = $this->messagesTo('5009');
        $this->assertCount(1, $customer);
        $this->assertStringContainsString("📝 *Sabab:* Sizning so'rovingizga ko'ra", $customer[0]);
        $this->assertStringNotContainsString('xatolik', $customer[0]);

        $staff = $this->messagesTo('5001');
        $this->assertCount(1, $staff);
        $this->assertStringContainsString("Mijoz buyurtmani bekor qildi #{$order->short_code}", $staff[0]);
        $this->assertStringContainsString("Vaqtim\\_yo'q", $staff[0]);
    }

    public function test_staff_must_give_a_reason_to_cancel_or_reject_through_the_api(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);

        foreach (['canceled', 'rejected'] as $status) {
            $this->setStatus($order, $this->manager, $status, ['reason' => null])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['reason' => 'Bekor qilish sababini tanlang.']);
        }
        // The system's own reason, and unknown ones, are refused
        $this->setStatus($order, $this->manager, 'canceled', ['reason' => 'payment_timeout'])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->setStatus($order, $this->manager, 'canceled', ['reason' => 'bogus'])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->setStatus($order, $this->manager, 'canceled', ['reason' => 'other', 'note' => str_repeat('a', 501)])->assertStatus(422)->assertJsonValidationErrors('note');
        $this->assertSame(OrderStatus::NEW, $order->fresh()->status);

        $this->setStatus($order, $this->manager, 'rejected', ['reason' => 'out_of_stock', 'note' => '  Lavash tugadi  '])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.cancel_reason', 'out_of_stock')
            ->assertJsonPath('data.cancel_note', 'Lavash tugadi');

        $this->assertSame($this->manager->id, $order->fresh()->cancelled_by);

        // Other transitions need no reason and record none
        $accepted = $this->makeOrder(OrderStatus::NEW);
        $this->setStatus($accepted, $this->manager, 'accepted', ['reason' => 'other'])->assertOk()->assertJsonPath('data.cancel_reason', null);
    }

    public function test_the_service_refuses_a_cancellation_without_an_allowed_reason(): void
    {
        $service = app(OrderStatusService::class);
        $order = $this->makeOrder(OrderStatus::NEW);

        $refused = function (callable $call) {
            try {
                $call();
                $this->fail('The cancellation should have been refused.');
            } catch (OrderTransitionException $e) {
                $this->assertSame(422, $e->status);
                $this->assertSame('reason', $e->reason);
            }
        };

        $refused(fn () => $service->transition($order, OrderStatus::CANCELED, $this->owner));
        $refused(fn () => $service->transition($order, OrderStatus::CANCELED, null));
        $refused(fn () => $service->transition($order, OrderStatus::REJECTED, $this->owner, ['cancel_reason' => 'nonsense']));
        $refused(fn () => $service->transition($order, OrderStatus::CANCELED, $this->owner, ['cancel_reason' => CancelReason::PAYMENT_TIMEOUT]));
        $refused(fn () => $service->transition($order, OrderStatus::CANCELED, $this->customer, ['customer' => true, 'cancel_reason' => CancelReason::NO_SHOW]));

        $this->assertSame(OrderStatus::NEW, $order->fresh()->status);
        $this->assertSame(10, (int) $this->variant->fresh()->stock);
    }

    public function test_unpaid_online_orders_expire_with_the_payment_timeout_reason(): void
    {
        config(['orders.unpaid_online_ttl_minutes' => 30]);
        $order = $this->checkout($this->customer, 1, 'click');
        $order->forceFill(['created_at' => now()->subMinutes(31)])->saveQuietly();

        dispatch_sync(new CancelUnpaidOnlineOrdersJob);

        $order->refresh();
        $this->assertSame(OrderStatus::CANCELED, $order->status);
        $this->assertSame(CancelReason::PAYMENT_TIMEOUT, $order->cancel_reason);
        $this->assertNull($order->cancelled_by);
        $this->assertStringContainsString("To'lov belgilangan vaqtda amalga oshirilmadi", $this->messagesTo('5009')[0]);
    }

    public function test_customer_message_names_the_store_reason_and_note(): void
    {
        $order = $this->makeOrder(OrderStatus::ACCEPTED, ['order_type' => 'pickup']);

        $this->setStatus($order, $this->manager, 'canceled', ['reason' => 'store_closed', 'note' => 'Elektr *yo\'q*'])->assertOk();

        $message = $this->messagesTo('5009')[0];
        $this->assertStringStartsWith('❌ *Buyurtmangiz bekor qilindi.*', $message);
        $this->assertStringContainsString("📝 *Sabab:* Do'kon hozir ishlamayapti", $message);
        $this->assertStringContainsString("💬 Elektr \\*yo'q\\*", $message);
        $this->assertStringContainsString("bog'laning", $message);
    }

    public function test_every_reason_has_uzbek_labels_and_staff_never_get_payment_timeout(): void
    {
        foreach (CancelReason::cases() as $reason) {
            $this->assertNotSame('', $reason->label());
            $this->assertNotSame('', $reason->customerLabel());
        }

        $this->assertNotContains('payment_timeout', CancelReason::staffValues());
        $this->assertCount(count(CancelReason::cases()) - 1, CancelReason::staffOptions());
    }

    public function test_order_json_carries_the_reason_fields(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);

        $json = $order->fresh()->toArray();

        $this->assertArrayHasKey('cancel_reason', $json);
        $this->assertArrayHasKey('cancel_note', $json);
        $this->assertNull($json['cancel_reason']);
    }

    public function test_checkout_still_bans_cash_after_a_no_show_cancelled_through_the_api(): void
    {
        $order = $this->checkout($this->customer, 1);
        $this->setStatus($order, $this->manager, 'accepted')->assertOk();
        $this->setStatus($order, $this->manager, 'canceled', ['reason' => 'no_show'])->assertOk();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('naqd pulda buyurtma berish taqiqlangan');
        try {
            $this->checkout($this->customer, 1);
        } catch (ValidationException $e) {
            $this->fail('Expected the cash ban, got a validation error: ' . $e->getMessage());
        }
    }
}
