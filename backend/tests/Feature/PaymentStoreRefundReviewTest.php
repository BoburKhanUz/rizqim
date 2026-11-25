<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PlatformRole;
use App\Enums\StoreTransactionType;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderLedgerReversal;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Models\User;
use App\Services\Admin\Attention\AttentionCollector;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Wave P (forged provider cancels): a store collecting into its own Payme cashbox knows the key that authenticates
 * Payme's Merchant API calls, so it can post CancelTransaction itself. A cancel of a performed transaction of an order
 * RIZQIM has settled (delivered: commission booked) is answered and shown (payment refunded) but never reverses the
 * commission by itself: it waits for a manager in the Boshqaruv markazi (approve: RIZQIM's own refund path; dismiss:
 * the commission stays). Platform-owned orders keep their behaviour (PaymentLateSettlementTest).
 */
class PaymentStoreRefundReviewTest extends TestCase
{
    use RefreshDatabase;
    use AdminFixtures;

    private Store $store;
    private User $customer;
    private string $storeKey;
    private string $platformKey;

    protected function setUp(): void
    {
        parent::setUp();

        // Built at runtime: no merchant key is written in the code base
        $this->storeKey = 'store-' . Str::random(32);
        $this->platformKey = 'platform-' . Str::random(32);
        config(['services.payme.secret_key' => $this->platformKey]);

        $this->store = Store::forceCreate([
            'name' => 'Own Cashbox Cafe',
            'status' => 'active',
            'payment_account_type' => 'own_account',
            'payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'own-' . Str::random(8), 'secret_key' => $this->storeKey]],
            'credit_limit' => -1000000,
        ]);
        $this->customer = User::create(['name' => 'Customer', 'phone' => '+998909991001', 'password' => bcrypt('password')]);
    }

    private function payme(string $method, array $params, ?string $key = null)
    {
        return $this->postJson('/api/v1/payments/payme/webhook', ['jsonrpc' => '2.0', 'id' => 7, 'method' => $method, 'params' => $params], [
            'Authorization' => 'Basic ' . base64_encode('Paycom:' . ($key ?? $this->storeKey)),
        ]);
    }

    /**
     * A STORE-owned Payme order of 50 000 so'm (5 000 commission), paid and delivered: the commission is on the debt.
     */
    private function deliveredOrder(string $trx = 'own-1'): Order
    {
        $order = Order::create([
            'store_id' => $this->store->id,
            'user_id' => $this->customer->id,
            'total_price' => 50000,
            'commission_amount' => 5000,
            'status' => OrderStatus::NEW->value,
            'order_type' => 'pickup',
            'payment_method' => 'payme',
            'payment_owner' => 'STORE',
            'payment_status' => PaymentStatus::PENDING->value,
        ]);

        $this->payme('CreateTransaction', ['id' => $trx, 'time' => 1760000000000, 'amount' => 5000000, 'account' => ['order_id' => $order->uuid]])
            ->assertJsonPath('result.state', 1);
        $this->payme('PerformTransaction', ['id' => $trx])->assertJsonPath('result.state', 2);

        $service = app(OrderStatusService::class);
        foreach ([OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::DELIVERED] as $status) {
            $service->transition($order->fresh(), $status, null);
        }
        $this->assertEquals(5000, (float) $this->store->fresh()->debt_balance);

        return $order->fresh();
    }

    private function ledgerRows(): int
    {
        return StoreTransaction::count() + StoreWalletTransaction::count();
    }

    public function test_a_cancel_signed_with_the_stores_own_key_keeps_the_commission_of_a_delivered_order(): void
    {
        $order = $this->deliveredOrder();
        $rows = $this->ledgerRows();

        Log::spy();
        // Payme's protocol answer: the transaction is cancelled after it was performed (-2), so Payme's side stays consistent
        $first = $this->payme('CancelTransaction', ['id' => 'own-1', 'reason' => 5])->assertOk()->assertJsonPath('result.state', -2);
        $this->assertGreaterThan(0, $first->json('result.cancel_time'));
        // A repeat gets the same answer and changes nothing
        $this->payme('CancelTransaction', ['id' => 'own-1', 'reason' => 5])->assertJsonPath('result.state', -2)
            ->assertJsonPath('result.cancel_time', $first->json('result.cancel_time'));
        $this->payme('CheckTransaction', ['id' => 'own-1'])->assertJsonPath('result.state', -2)->assertJsonPath('result.reason', 5);

        // The commission stays: no reversal, no ledger row, the debt unchanged
        $this->assertEquals(5000, (float) $this->store->fresh()->debt_balance);
        $this->assertSame($rows, $this->ledgerRows());
        $this->assertSame(0, StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::REFUND->value)->count());
        $this->assertSame(0, OrderLedgerReversal::where('order_id', $order->id)->count());

        // The customer and the store see the money as given back; the food was delivered, the order stays delivered
        $order->refresh();
        $payment = Payment::where('provider_transaction_id', 'own-1')->sole();
        $this->assertSame(PaymentStatus::REFUNDED, $payment->status);
        $this->assertSame(PaymentStatus::REFUNDED, $order->payment_status);
        $this->assertSame(OrderStatus::DELIVERED, $order->status);

        // Recorded as a store-initiated refund waiting for a manager
        $this->assertNotNull($order->store_refund_review_at);
        $this->assertSame('pending', $payment->payload['store_refund_review']['status']);
        $this->assertSame('payme', $payment->payload['store_refund_review']['provider']);
        $this->assertSame(5, $payment->payload['store_refund_review']['reason']);
        $this->assertSame(1, AuditLog::where('action', 'order.store_refund_held')->where('model_id', $order->id)->count());

        // A critical alert once, with ids only (never a key)
        Log::shouldHaveReceived('critical')->once()->withArgs(function ($message, $context = []) use ($order, $payment) {
            $flat = json_encode([$message, $context]);

            return str_contains($message, 'store')
                && ($context['order_id'] ?? null) === $order->id
                && ($context['payment_id'] ?? null) === $payment->id
                && !str_contains($flat, $this->storeKey);
        });

        // Visible in Bugun → Diqqat talab qiladi
        $groups = collect(app(AttentionCollector::class)->collect($this->makeMember(PlatformRole::FINANCE), 5))->keyBy('kind');
        $group = $groups['store_refund_review'];
        $this->assertSame(1, $group['count']);
        $this->assertSame('critical', $group['severity']);
        $this->assertSame('/orders/' . $order->uuid, $group['items'][0]['href']);
        $this->assertSame($this->store->id, $group['items'][0]['store_id']);

        // No refund request can be asked for money the provider already gave back
        $this->assertTrue(app(\App\Services\Admin\Orders\RefundRequestService::class)->balance($order)['refundable']->isZero());
    }

    public function test_a_manager_approving_the_review_runs_rizqims_refund_and_reverses_the_commission_once(): void
    {
        $order = $this->deliveredOrder();
        $this->payme('CancelTransaction', ['id' => 'own-1', 'reason' => 5])->assertJsonPath('result.state', -2);

        // Viewing alone is not enough
        $viewer = $this->confirmedSession($this->makeMember(PlatformRole::VIEWER));
        $this->adminJson('POST', "/orders/{$order->uuid}/store-refund-review/approve", $viewer, ['reason' => 'Kabinetda tekshirildi'])->assertForbidden();

        $member = $this->makeMember(PlatformRole::FINANCE);
        $token = $this->confirmedSession($member);
        $this->adminJson('POST', "/orders/{$order->uuid}/store-refund-review/approve", $token, [])->assertStatus(422)->assertJsonValidationErrors('reason');

        // Four eyes (PaymentStoreRefundReviewFollowUpTest): another member proposed it, this one confirms it
        $proposer = $this->makeMember(PlatformRole::FINANCE);
        $this->adminJson('POST', "/orders/{$order->uuid}/store-refund-review/approve", $this->confirmedSession($proposer), ['reason' => 'Kabinetda tekshirildi'])
            ->assertOk()->assertJsonPath('data.store_refund_review.status', 'pending');
        $this->assertEquals(5000, (float) $this->store->fresh()->debt_balance);

        $this->adminJson('POST', "/orders/{$order->uuid}/store-refund-review/approve", $token, ['reason' => 'Kabinetda tekshirildi'])
            ->assertOk()
            ->assertJsonPath('data.status', 'refunded')
            ->assertJsonPath('data.store_refund_review.status', 'approved');

        $order->refresh();
        $this->assertSame(OrderStatus::REFUNDED, $order->status);
        $this->assertSame(PaymentStatus::REFUNDED, $order->payment_status);
        $this->assertNull($order->store_refund_review_at);
        $this->assertEquals(0, (float) $this->store->fresh()->debt_balance);
        $this->assertSame(1, OrderLedgerReversal::where('order_id', $order->id)->count());
        $payment = Payment::where('provider_transaction_id', 'own-1')->sole();
        $this->assertSame('approved', $payment->payload['store_refund_review']['status']);
        $this->assertSame($member->id, $payment->payload['store_refund_review']['decided_by']);
        // The provider already gave the money back: nothing is flagged for a manual refund
        $this->assertArrayNotHasKey('manual_refund_required', $payment->payload);

        // Once: a second decision finds nothing to decide, and Payme's repeat changes nothing
        $rows = $this->ledgerRows();
        $this->adminJson('POST', "/orders/{$order->uuid}/store-refund-review/approve", $token, ['reason' => 'Yana bir marta'])
            ->assertStatus(409)->assertJsonPath('code', 'no_store_refund_review');
        $this->payme('CancelTransaction', ['id' => 'own-1', 'reason' => 5])->assertJsonPath('result.state', -2);
        $this->assertSame($rows, $this->ledgerRows());
        $this->assertSame(0, collect(app(AttentionCollector::class)->collect($member, 5))->where('kind', 'store_refund_review')->count());
    }

    public function test_a_manager_dismissing_the_review_keeps_the_commission(): void
    {
        $order = $this->deliveredOrder();
        $this->payme('CancelTransaction', ['id' => 'own-1', 'reason' => 5])->assertJsonPath('result.state', -2);
        $rows = $this->ledgerRows();

        $member = $this->makeMember(PlatformRole::FINANCE);
        $token = $this->confirmedSession($member);
        $this->adminJson('POST', "/orders/{$order->uuid}/store-refund-review/dismiss", $token, ['reason' => "Ovqatxona o'zi qaytargan"])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.store_refund_review.status', 'dismissed');

        $order->refresh();
        $this->assertSame(OrderStatus::DELIVERED, $order->status);
        $this->assertNull($order->store_refund_review_at);
        $this->assertEquals(5000, (float) $this->store->fresh()->debt_balance);
        $this->assertSame($rows, $this->ledgerRows());
        $this->assertSame('dismissed', Payment::where('provider_transaction_id', 'own-1')->sole()->payload['store_refund_review']['status']);
        $this->assertSame(1, AuditLog::where('action', 'admin.orders.store_refund_review.dismissed')->count());

        $this->adminJson('POST', "/orders/{$order->uuid}/store-refund-review/approve", $token, ['reason' => 'Endi tasdiqlayman'])
            ->assertStatus(409)->assertJsonPath('code', 'no_store_refund_review');
    }

    public function test_a_refund_rizqim_made_first_is_not_held_when_the_store_then_returns_the_money_through_payme(): void
    {
        $order = $this->deliveredOrder();
        // Owner decision 4: RIZQIM refunds a delivered store-owned order only through a two-person check (one member
        // asks, another approves); a single admin's direct refund is refused (StoreOwnAccountRefundTest)
        $refunds = app(\App\Services\Admin\Orders\RefundRequestService::class);
        $request = $refunds->request($order, $this->makeMember(PlatformRole::SUPERADMIN), '50000', 'Mijoz shikoyati');
        $refunds->approve($request, $this->makeMember(PlatformRole::SUPERADMIN), 'Tasdiq');
        $this->assertSame(OrderStatus::REFUNDED, $order->fresh()->status);
        $this->assertEquals(0, (float) $this->store->fresh()->debt_balance);
        $rows = $this->ledgerRows();

        $this->payme('CancelTransaction', ['id' => 'own-1', 'reason' => 5])->assertJsonPath('result.state', -2);

        $order->refresh();
        $this->assertNull($order->store_refund_review_at);
        $this->assertSame(PaymentStatus::REFUNDED, $order->payment_status);
        $this->assertSame($rows, $this->ledgerRows());
        $this->assertArrayNotHasKey('store_refund_review', Payment::where('provider_transaction_id', 'own-1')->sole()->payload);
    }

    public function test_a_cancel_of_an_order_still_in_progress_is_not_held(): void
    {
        $order = Order::create([
            'store_id' => $this->store->id, 'user_id' => $this->customer->id, 'total_price' => 50000, 'commission_amount' => 5000,
            'status' => OrderStatus::NEW->value, 'order_type' => 'pickup', 'payment_method' => 'payme', 'payment_owner' => 'STORE',
            'payment_status' => PaymentStatus::PENDING->value,
        ]);
        $this->payme('CreateTransaction', ['id' => 'own-2', 'time' => 1760000000000, 'amount' => 5000000, 'account' => ['order_id' => $order->uuid]]);
        $this->payme('PerformTransaction', ['id' => 'own-2'])->assertJsonPath('result.state', 2);

        // Nothing is booked before delivery: the order is simply unpaid again, as before
        $this->payme('CancelTransaction', ['id' => 'own-2', 'reason' => 5])->assertJsonPath('result.state', -2);
        $order->refresh();
        $this->assertSame(PaymentStatus::PENDING, $order->payment_status);
        $this->assertNull($order->store_refund_review_at);
        $this->assertSame(0, $this->ledgerRows());
    }

    public function test_click_has_no_callback_that_reverses_a_paid_order_even_signed_with_the_stores_own_key(): void
    {
        // Click's SHOP API has only Prepare and Complete; a reversal is RIZQIM's own outbound call. A Complete carrying
        // error < 0 (Click gave a payment up) for a paid, delivered order is answered -9 and changes nothing
        $clickKey = 'click-' . Str::random(32);
        $this->store->update(['payment_settings' => ['click' => ['enabled' => true, 'service_id' => '1', 'merchant_id' => '2', 'merchant_user_id' => '3', 'secret_key' => $clickKey]]]);
        $order = Order::create([
            'store_id' => $this->store->id, 'user_id' => $this->customer->id, 'total_price' => 50000, 'commission_amount' => 5000,
            'status' => OrderStatus::NEW->value, 'order_type' => 'pickup', 'payment_method' => 'click', 'payment_owner' => 'STORE',
            'payment_status' => PaymentStatus::PENDING->value,
        ]);
        $complete = fn (int $error) => $this->postJson('/api/v1/payments/click/webhook', [
            'click_trans_id' => '77', 'service_id' => '1', 'click_paydoc_id' => '1',
            'merchant_trans_id' => $order->uuid, 'merchant_prepare_id' => $order->id,
            'amount' => '50000', 'action' => 1, 'error' => $error, 'sign_time' => '2026-11-22 12:00:00',
            'sign_string' => md5('77' . '1' . $clickKey . $order->uuid . $order->id . '50000' . '1' . '2026-11-22 12:00:00'),
        ]);
        $complete(0)->assertJson(['error' => 0]);
        $service = app(OrderStatusService::class);
        foreach ([OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::DELIVERED] as $status) {
            $service->transition($order->fresh(), $status, null);
        }
        $rows = $this->ledgerRows();
        $debt = (string) $this->store->fresh()->debt_balance;

        $complete(-5017)->assertJson(['error' => -9]);

        $order->refresh();
        $this->assertSame(OrderStatus::DELIVERED, $order->status);
        $this->assertSame(PaymentStatus::PAID, $order->payment_status);
        $this->assertSame(PaymentStatus::PAID, Payment::where('provider', 'click')->sole()->status);
        $this->assertSame($rows, $this->ledgerRows());
        $this->assertSame($debt, (string) $this->store->fresh()->debt_balance);
    }
}
