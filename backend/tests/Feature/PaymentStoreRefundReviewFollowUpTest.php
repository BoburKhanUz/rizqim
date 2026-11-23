<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderLedgerReversal;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Wave P review (money): a store-raised refund review reverses RIZQIM's commission only on two members' word (four eyes,
 * as P4-14 refund requests); a delivered order whose money was given back is never payable again (Payme and Click), so
 * the store cannot raise a dismissed review again; the card keeps every review; and a Payme refund of a paid order
 * cancelled after payment leaves it `refunded`, as Paylov does.
 */
class PaymentStoreRefundReviewFollowUpTest extends TestCase
{
    use RefreshDatabase;
    use AdminFixtures;

    private Store $store;
    private User $customer;
    private string $storeKey;

    protected function setUp(): void
    {
        parent::setUp();
        // Built at runtime: no merchant key is written in the code base
        $this->storeKey = 'store-' . Str::random(32);
        config(['services.payme.secret_key' => 'platform-' . Str::random(32)]);
        $this->store = Store::forceCreate([
            'name' => 'Own Cashbox Cafe', 'status' => 'active', 'payment_account_type' => 'own_account',
            'payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'own-' . Str::random(8), 'secret_key' => $this->storeKey]],
            'credit_limit' => -1000000,
        ]);
        $this->customer = User::create(['name' => 'Customer', 'phone' => '+998909991002', 'password' => bcrypt('password')]);
    }

    private function payme(string $method, array $params)
    {
        return $this->postJson('/api/v1/payments/payme/webhook', ['jsonrpc' => '2.0', 'id' => 7, 'method' => $method, 'params' => $params], [
            'Authorization' => 'Basic ' . base64_encode('Paycom:' . $this->storeKey),
        ]);
    }

    private function deliveredOrder(): Order
    {
        $order = Order::create([
            'store_id' => $this->store->id, 'user_id' => $this->customer->id, 'total_price' => 50000, 'commission_amount' => 5000,
            'status' => OrderStatus::NEW->value, 'order_type' => 'pickup', 'payment_method' => 'payme', 'payment_owner' => 'STORE',
            'payment_status' => PaymentStatus::PENDING->value,
        ]);
        $this->payme('CreateTransaction', ['id' => 'own-1', 'time' => 1760000000000, 'amount' => 5000000, 'account' => ['order_id' => $order->uuid]])->assertJsonPath('result.state', 1);
        $this->payme('PerformTransaction', ['id' => 'own-1'])->assertJsonPath('result.state', 2);
        foreach ([OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::DELIVERED] as $status) {
            app(OrderStatusService::class)->transition($order->fresh(), $status, null);
        }
        $this->assertEquals(5000, (float) $this->store->fresh()->debt_balance);

        return $order->fresh();
    }

    public function test_approving_a_store_raised_review_needs_two_different_members(): void
    {
        $order = $this->deliveredOrder();
        $this->payme('CancelTransaction', ['id' => 'own-1', 'reason' => 5])->assertJsonPath('result.state', -2);

        // The first member's approval only proposes it: the commission stays, the review still waits
        $first = $this->makeMember(PlatformRole::FINANCE);
        $firstToken = $this->confirmedSession($first);
        $this->adminJson('POST', "/orders/{$order->uuid}/store-refund-review/approve", $firstToken, ['reason' => 'Kabinetda tekshirildi'])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.store_refund_review.status', 'pending')
            ->assertJsonPath('data.store_refund_review.proposed_by', $first->id)
            ->assertJsonPath('data.actions.can_approve_store_refund', false)
            ->assertJsonPath('data.actions.can_decide_store_refund', true);
        $this->assertEquals(5000, (float) $this->store->fresh()->debt_balance, 'a single member reversed the commission (no four eyes)');
        $this->assertNotNull($order->fresh()->store_refund_review_at);
        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertSame(1, AuditLog::where('action', 'admin.orders.store_refund_review.approved')->where('payload_json->decision', 'proposed')->count());

        // The same member cannot confirm it, a superadmin neither
        $this->adminJson('POST', "/orders/{$order->uuid}/store-refund-review/approve", $firstToken, ['reason' => 'Yana men'])
            ->assertStatus(403)->assertJsonPath('code', 'four_eyes');
        $this->assertEquals(5000, (float) $this->store->fresh()->debt_balance);

        // A different member with orders.refund_approve confirms it: RIZQIM's own refund, the commission reversed once
        $second = $this->makeMember(PlatformRole::FINANCE);
        $secondToken = $this->confirmedSession($second);
        $this->adminJson('GET', "/orders/{$order->uuid}", $secondToken)->assertJsonPath('data.actions.can_approve_store_refund', true);
        $this->adminJson('POST', "/orders/{$order->uuid}/store-refund-review/approve", $secondToken, ['reason' => 'Ikkinchi tasdiq'])
            ->assertOk()
            ->assertJsonPath('data.status', 'refunded')
            ->assertJsonPath('data.store_refund_review.status', 'approved')
            ->assertJsonPath('data.store_refund_review.proposed_by', $first->id)
            ->assertJsonPath('data.store_refund_review.decided_by', $second->id);
        $this->assertEquals(0, (float) $this->store->fresh()->debt_balance);
        $this->assertSame(1, OrderLedgerReversal::where('order_id', $order->id)->count());
        $this->assertNull($order->fresh()->store_refund_review_at);
    }

    public function test_dismissing_stays_one_member_and_drops_a_proposal(): void
    {
        $order = $this->deliveredOrder();
        $this->payme('CancelTransaction', ['id' => 'own-1', 'reason' => 5]);
        $first = $this->makeMember(PlatformRole::FINANCE);
        $this->adminJson('POST', "/orders/{$order->uuid}/store-refund-review/approve", $this->confirmedSession($first), ['reason' => 'Taklif'])->assertOk();

        // Dismissing keeps the platform's money: one member is enough, even after another proposed approving it
        $other = $this->makeMember(PlatformRole::FINANCE);
        $token = $this->confirmedSession($other);
        $this->adminJson('POST', "/orders/{$order->uuid}/store-refund-review/dismiss", $token, ['reason' => 'soxta'])
            ->assertOk()->assertJsonPath('data.store_refund_review.status', 'dismissed');
        $this->adminJson('POST', "/orders/{$order->uuid}/store-refund-review/approve", $token, ['reason' => 'Endi'])
            ->assertStatus(409)->assertJsonPath('code', 'no_store_refund_review');
        $this->assertEquals(5000, (float) $this->store->fresh()->debt_balance);
    }

    public function test_a_delivered_order_whose_money_went_back_is_not_payable_again_so_a_dismissed_review_is_not_raised_again(): void
    {
        $order = $this->deliveredOrder();
        $this->payme('CancelTransaction', ['id' => 'own-1', 'reason' => 5]);
        $member = $this->makeMember(PlatformRole::FINANCE);
        $token = $this->confirmedSession($member);
        $this->adminJson('POST', "/orders/{$order->uuid}/store-refund-review/dismiss", $token, ['reason' => 'soxta'])->assertOk();

        // The store, again with its own key, no money moving: refused as not payable (Payme's -31051)
        $this->payme('CheckPerformTransaction', ['amount' => 5000000, 'account' => ['order_id' => $order->uuid]])->assertJsonPath('error.code', -31051);
        $this->payme('CreateTransaction', ['id' => 'own-2', 'time' => 1760000100000, 'amount' => 5000000, 'account' => ['order_id' => $order->uuid]])
            ->assertJsonPath('error.code', -31051);

        $this->assertNull($order->fresh()->store_refund_review_at, 'the dismissed order was held again by a forged re-pay + cancel');
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());

        // The card keeps every review of the order, the latest first
        $this->adminJson('GET', "/orders/{$order->uuid}", $token)
            ->assertJsonPath('data.store_refund_review.status', 'dismissed')
            ->assertJsonCount(1, 'data.store_refund_reviews')
            ->assertJsonPath('data.store_refund_reviews.0.status', 'dismissed')
            ->assertJsonPath('data.store_refund_reviews.0.decided_by', $member->id);
    }

    public function test_click_refuses_a_delivered_order_whose_money_went_back_but_takes_a_late_payment_of_an_unpaid_one(): void
    {
        $clickKey = 'click-' . Str::random(32);
        $this->store->update(['payment_settings' => ['click' => ['enabled' => true, 'service_id' => '1', 'merchant_id' => '2', 'merchant_user_id' => '3', 'secret_key' => $clickKey]]]);
        $order = Order::create([
            'store_id' => $this->store->id, 'user_id' => $this->customer->id, 'total_price' => 50000, 'commission_amount' => 5000,
            'status' => OrderStatus::DELIVERED->value, 'order_type' => 'pickup', 'payment_method' => 'click', 'payment_owner' => 'STORE',
            'payment_status' => PaymentStatus::REFUNDED->value,
        ]);
        $prepare = fn (Order $order) => $this->postJson('/api/v1/payments/click/webhook', [
            'click_trans_id' => '78', 'service_id' => '1', 'click_paydoc_id' => '1', 'merchant_trans_id' => $order->uuid,
            'amount' => '50000', 'action' => 0, 'error' => 0, 'sign_time' => '2026-11-22 12:00:00',
            'sign_string' => md5('78' . '1' . $clickKey . $order->uuid . '50000' . '0' . '2026-11-22 12:00:00'),
        ]);
        $complete = fn (Order $order) => $this->postJson('/api/v1/payments/click/webhook', [
            'click_trans_id' => '78', 'service_id' => '1', 'click_paydoc_id' => '1', 'merchant_trans_id' => $order->uuid,
            'merchant_prepare_id' => $order->id, 'amount' => '50000', 'action' => 1, 'error' => 0, 'sign_time' => '2026-11-22 12:00:00',
            'sign_string' => md5('78' . '1' . $clickKey . $order->uuid . $order->id . '50000' . '1' . '2026-11-22 12:00:00'),
        ]);

        $prepare($order)->assertJson(['error' => -9]);
        $complete($order)->assertJson(['error' => -9]);
        $this->assertSame(0, Payment::where('order_id', $order->id)->count());
        $this->assertSame(PaymentStatus::REFUNDED, $order->fresh()->payment_status);

        // A delivered order nothing was paid for yet still takes its (late) payment
        $order->forceFill(['payment_status' => PaymentStatus::PENDING->value])->save();
        $prepare($order)->assertJson(['error' => 0]);
    }

    public function test_a_payme_refund_of_a_cancelled_paid_order_leaves_it_refunded(): void
    {
        $order = Order::create([
            'store_id' => $this->store->id, 'user_id' => $this->customer->id, 'total_price' => 50000, 'commission_amount' => 5000,
            'status' => OrderStatus::NEW->value, 'order_type' => 'pickup', 'payment_method' => 'payme', 'payment_owner' => 'STORE',
            'payment_status' => PaymentStatus::PENDING->value,
        ]);
        $this->payme('CreateTransaction', ['id' => 'own-9', 'time' => 1760000000000, 'amount' => 5000000, 'account' => ['order_id' => $order->uuid]]);
        $this->payme('PerformTransaction', ['id' => 'own-9'])->assertJsonPath('result.state', 2);
        app(OrderStatusService::class)->transition($order->fresh(), OrderStatus::ACCEPTED, null);
        app(OrderStatusService::class)->transition($order->fresh(), OrderStatus::CANCELED, null, ['cancel_reason' => 'out_of_stock']);

        $this->payme('CancelTransaction', ['id' => 'own-9', 'reason' => 5])->assertJsonPath('result.state', -2);

        $this->assertSame(PaymentStatus::REFUNDED, $order->fresh()->payment_status);
        $this->assertSame(OrderStatus::CANCELED, $order->fresh()->status);
    }
}
