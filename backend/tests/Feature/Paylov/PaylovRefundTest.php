<?php

namespace Tests\Feature\Paylov;

use App\Enums\CancelReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PlatformRole;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Feature\AdminOrders\AdminOrdersFixtures;
use Tests\TestCase;

/**
 * Wave P: Paylov refunds are full refunds only (developer.paylov.uz/subscribe/cancel-payment takes a transactionId and
 * no amount). Where a Click/Payme payment is flagged for a manual refund (a paid order cancelled or refunded,
 * OrderStatusService), a Paylov payment is given back through payment/cancel; a refund request for a part of a Paylov
 * order is refused; a refusal or an outage of Paylov never loses the refund (retried, then a manual flag).
 */
class PaylovRefundTest extends TestCase
{
    use RefreshDatabase;
    use AdminOrdersFixtures;
    use PaylovFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->store->forceFill([
            'payment_account_type' => 'platform_account',
            'credit_limit' => -1000000,
            'payment_settings' => array_merge($this->store->payment_settings ?? [], ['paylov' => ['enabled' => true]]),
        ])->save();
        $this->setUpPaylov();
    }

    /**
     * A Paylov order paid and confirmed (accepted by the auto-accept), delivered when asked.
     *
     * @return array{0: Order, 1: string}
     */
    private function paidOrder(bool $delivered = false): array
    {
        $order = $this->paylovOrder($this->store, ['branch_id' => $this->branch->id]);
        $txn = (string) Str::uuid();
        $this->perform($order, $txn)->assertExactJson(self::answer());
        if ($delivered) {
            foreach ([OrderStatus::PREPARING, OrderStatus::DELIVERED] as $status) {
                app(OrderStatusService::class)->transition($order->fresh(), $status, null);
            }
        }

        return [$order->fresh(), $txn];
    }

    private function payment(string $txn): Payment
    {
        return Payment::where('provider', 'paylov')->where('provider_transaction_id', $txn)->sole();
    }

    public function test_cancelling_a_paid_paylov_order_gives_the_money_back_through_paylov_once(): void
    {
        [$order, $txn] = $this->paidOrder();

        $result = app(OrderStatusService::class)->transition($order, OrderStatus::CANCELED, $this->owner, ['cancel_reason' => CancelReason::OTHER->value]);

        $this->assertFalse($result->manualRefundRequired);
        $cancel = $this->sentTo('/merchant/payment/cancel/');
        $this->assertCount(1, $cancel);
        $this->assertSame(['transactionId' => $txn], $cancel[0]->data());

        $payment = $this->payment($txn);
        $this->assertSame(PaymentStatus::REFUNDED, $payment->status);
        $this->assertSame('done', $payment->payload['paylov']['refund']['status']);
        $this->assertSame('merchant', $payment->payload['paylov']['cancelled_by']);
        $this->assertArrayNotHasKey('manual_refund_required', $payment->payload);
        $this->assertSame(PaymentStatus::REFUNDED, $order->fresh()->payment_status);
        $note = OrderStatusHistory::where('order_id', $order->id)->where('status', 'canceled')->sole()->notes;
        $this->assertStringContainsString("Paylov orqali mijozga avtomatik qaytarilmoqda", $note);

        // Paylov's own cancel webhook for the same transaction (retried) changes nothing, and the re-check asks nothing
        $this->cancelWebhook($order, $txn)->assertOk();
        $this->cancelWebhook($order, $txn)->assertOk();
        Artisan::call('payments:paylov-reconcile');
        $this->assertCount(1, $this->sentTo('/merchant/payment/cancel/'));
        $this->assertSame(PaymentStatus::REFUNDED, $this->payment($txn)->status);
    }

    public function test_a_refund_paylov_refuses_for_good_is_flagged_for_a_manual_refund(): void
    {
        Log::spy();
        [$order, $txn] = $this->paidOrder();
        $this->paylovCancelError = 'transaction_not_available_for_cancel';

        app(OrderStatusService::class)->transition($order, OrderStatus::CANCELED, $this->owner, ['cancel_reason' => CancelReason::OTHER->value]);

        $payment = $this->payment($txn);
        $this->assertSame(PaymentStatus::PAID, $payment->status);
        $this->assertTrue($payment->payload['manual_refund_required']);
        $this->assertSame('paylov_cancel_failed', $payment->payload['manual_refund_reason']);
        $this->assertSame('failed', $payment->payload['paylov']['refund']['status']);
        // Paylov's status was read back before giving up (it may have been cancelled already)
        $this->assertCount(2, $this->sentTo('/merchant/getTransactions/'));
        Log::shouldHaveReceived('error')->withArgs(fn ($message) => str_contains($message, 'Paylov refund failed'));

        // Not asked again by the re-check
        Artisan::call('payments:paylov-reconcile');
        $this->assertCount(1, $this->sentTo('/merchant/payment/cancel/'));
    }

    public function test_a_cancel_paylov_already_did_is_recorded_not_flagged(): void
    {
        [$order, $txn] = $this->paidOrder();
        $this->paylovCancelError = 'transaction_not_available_for_cancel';
        $this->paylovStatus = 'cancel';

        app(OrderStatusService::class)->transition($order, OrderStatus::CANCELED, $this->owner, ['cancel_reason' => CancelReason::OTHER->value]);

        $payment = $this->payment($txn);
        $this->assertSame(PaymentStatus::REFUNDED, $payment->status);
        $this->assertArrayNotHasKey('manual_refund_required', $payment->payload);
    }

    public function test_a_refund_paylov_does_not_answer_is_asked_again_until_it_is_done(): void
    {
        [$order, $txn] = $this->paidOrder();
        $this->paylovCancelError = 'connection';

        app(OrderStatusService::class)->transition($order, OrderStatus::CANCELED, $this->owner, ['cancel_reason' => CancelReason::OTHER->value]);

        $payment = $this->payment($txn);
        $this->assertSame(PaymentStatus::PAID, $payment->status);
        $this->assertSame('retrying', $payment->payload['paylov']['refund']['status']);
        $this->assertArrayNotHasKey('manual_refund_required', $payment->payload);

        Artisan::call('payments:paylov-reconcile');
        $this->assertSame(2, (int) $this->payment($txn)->payload['paylov']['refund']['attempts']);

        $this->paylovCancelError = null;
        Artisan::call('payments:paylov-reconcile');
        $this->assertSame(PaymentStatus::REFUNDED, $this->payment($txn)->status);
        $this->assertSame(PaymentStatus::REFUNDED, $order->fresh()->payment_status);
        $this->assertCount(3, $this->sentTo('/merchant/payment/cancel/'));
    }

    public function test_a_refund_request_for_part_of_a_paylov_order_is_refused_and_the_whole_is_refunded_through_paylov(): void
    {
        [$order, $txn] = $this->paidOrder(true);
        $support = $this->makeMember(PlatformRole::SUPPORT);
        $supportToken = $this->confirmedSession($support);

        $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $supportToken, ['amount' => '20000', 'reason' => 'Bitta taom kelmadi'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'paylov_partial_refund_not_supported')
            ->assertJsonPath('message', fn (string $message) => str_contains($message, "Paylov faqat to'liq qaytarish"));

        $request = $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $supportToken, ['amount' => '50000', 'reason' => 'Buyurtma kelmadi'])
            ->assertCreated()->json('data');

        $finance = $this->makeMember(PlatformRole::FINANCE);
        $financeToken = $this->signIn($finance);
        $this->stepUp($finance, $financeToken);
        $approved = $this->adminJson('POST', "/refund-requests/{$request['id']}/approve", $financeToken, ['reason' => 'Tasdiq'])->assertOk()->json('data');

        $this->assertSame('order_refunded', $approved['execution']['mode']);
        $this->assertSame('paylov', $approved['execution']['provider_refund']);
        $this->assertSame('provider', $approved['execution']['allocations'][0]['kind']);
        $this->assertSame('50000.00', $approved['execution']['automatic_amount']);
        $this->assertSame('0.00', $approved['execution']['manual_amount']);

        $this->assertCount(1, $this->sentTo('/merchant/payment/cancel/'));
        $this->assertSame(PaymentStatus::REFUNDED, $this->payment($txn)->status);
        $order->refresh();
        $this->assertSame(OrderStatus::REFUNDED, $order->status);
        $this->assertSame(PaymentStatus::REFUNDED, $order->payment_status);
        $this->assertArrayNotHasKey('manual_refund_required', $this->payment($txn)->payload);
    }

    public function test_the_admin_order_card_marks_a_paylov_payment_still_unconfirmed_and_its_refund(): void
    {
        $order = $this->paylovOrder($this->store, ['branch_id' => $this->branch->id]);
        $txn = (string) Str::uuid();
        $this->paylovStatus = 'pending';
        $this->perform($order, $txn)->assertExactJson(self::answer());
        $token = $this->confirmedSession($this->makeMember(PlatformRole::SUPPORT));

        $card = $this->adminJson('GET', "/orders/{$order->uuid}", $token)->assertOk()->json('data');
        $payment = collect($card['payments'])->firstWhere('provider', 'paylov');
        $this->assertSame(['pending', 'unconfirmed', null], [$payment['status'], $payment['confirmation'], $payment['provider_refund']]);
        // Other providers carry neither
        $this->assertNull(collect($card['payments'])->firstWhere('provider', 'cash')['confirmation'] ?? null);

        $this->paylovStatus = 'payed';
        $this->paylovCancelError = 'connection';
        Artisan::call('payments:paylov-reconcile');
        app(OrderStatusService::class)->transition($order->fresh(), OrderStatus::CANCELED, $this->owner, ['cancel_reason' => CancelReason::OTHER->value]);

        $card = $this->adminJson('GET', "/orders/{$order->uuid}", $token)->assertOk()->json('data');
        $payment = collect($card['payments'])->firstWhere('provider', 'paylov');
        $this->assertSame(['paid', 'confirmed', 'retrying'], [$payment['status'], $payment['confirmation'], $payment['provider_refund']]);
    }

    public function test_a_refund_request_is_refused_while_paylov_is_still_giving_the_money_back(): void
    {
        [$order] = $this->paidOrder();
        $this->paylovCancelError = 'connection';
        app(OrderStatusService::class)->transition($order, OrderStatus::CANCELED, $this->owner, ['cancel_reason' => CancelReason::OTHER->value]);

        $token = $this->confirmedSession($this->makeMember(PlatformRole::SUPPORT));
        $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $token, ['amount' => '50000', 'reason' => 'Pul qaytmadi'])
            ->assertStatus(422)->assertJsonPath('code', 'paylov_refund_in_progress');
    }
}
