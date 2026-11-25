<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PlatformRole;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Wave P review (security): the hold's and the decision's audit keep what a reviewer needs (Payme's reason, the payments
 * and the amount), the review flag never reaches the customer's or the store's order JSON, a Payme transaction of
 * another cashbox is answered like an unknown one, and only an integer Payme reason is taken.
 */
class PaymentStoreRefundReviewSecurityTest extends TestCase
{
    use RefreshDatabase;
    use AdminFixtures;

    private Store $store;
    private Store $other;
    private User $customer;
    private string $storeKey;
    private string $otherKey;

    protected function setUp(): void
    {
        parent::setUp();
        // Built at runtime: no merchant key is written in the code base
        $this->storeKey = 'store-' . Str::random(32);
        $this->otherKey = 'other-' . Str::random(32);
        config(['services.payme.secret_key' => 'platform-' . Str::random(32)]);
        $this->store = Store::forceCreate(['name' => 'A', 'status' => 'active', 'payment_account_type' => 'own_account',
            'payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'a', 'secret_key' => $this->storeKey]], 'credit_limit' => -1000000]);
        $this->other = Store::forceCreate(['name' => 'B', 'status' => 'active', 'payment_account_type' => 'own_account',
            'payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'b', 'secret_key' => $this->otherKey]], 'credit_limit' => -1000000]);
        $this->customer = User::create(['name' => 'Customer', 'phone' => '+998909991001', 'password' => bcrypt('password')]);
    }

    private function payme(string $method, array $params, ?string $key = null)
    {
        return $this->postJson('/api/v1/payments/payme/webhook', ['jsonrpc' => '2.0', 'id' => 7, 'method' => $method, 'params' => $params], [
            'Authorization' => 'Basic ' . base64_encode('Paycom:' . ($key ?? $this->storeKey)),
        ]);
    }

    private function deliveredOrder(Store $store, string $key, string $trx): Order
    {
        $order = Order::create(['store_id' => $store->id, 'user_id' => $this->customer->id, 'total_price' => 50000, 'commission_amount' => 5000,
            'status' => 'new', 'order_type' => 'pickup', 'payment_method' => 'payme', 'payment_owner' => 'STORE', 'payment_status' => 'pending']);
        $this->payme('CreateTransaction', ['id' => $trx, 'time' => 1760000000000, 'amount' => 5000000, 'account' => ['order_id' => $order->uuid]], $key)->assertJsonPath('result.state', 1);
        $this->payme('PerformTransaction', ['id' => $trx], $key)->assertJsonPath('result.state', 2);
        foreach ([OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::DELIVERED] as $s) {
            app(OrderStatusService::class)->transition($order->fresh(), $s, null);
        }

        return $order->fresh();
    }

    public function test_the_hold_and_decision_audit_keep_the_payme_reason_payments_and_amount(): void
    {
        $order = $this->deliveredOrder($this->store, $this->storeKey, 'own-1');
        $this->payme('CancelTransaction', ['id' => 'own-1', 'reason' => 5])->assertJsonPath('result.state', -2);
        $payment = Payment::where('provider_transaction_id', 'own-1')->sole();

        // `*_code` keys are redacted as secrets (AuditRedactor): the reason is kept under payme_reason
        $entry = AuditLog::where('action', 'order.store_refund_held')->where('model_id', $order->id)->sole();
        $this->assertSame(5, $entry->payload_json['payme_reason'] ?? null, 'audit payload: ' . json_encode($entry->payload_json));

        $member = $this->makeMember(PlatformRole::FINANCE);
        $this->adminJson('POST', "/orders/{$order->uuid}/store-refund-review/dismiss", $this->confirmedSession($member), ['reason' => 'soxta'])->assertOk();
        $decision = AuditLog::where('action', 'admin.orders.store_refund_review.dismissed')->sole();
        $this->assertSame([(int) $payment->id], $decision->payload_json['payment_ids'] ?? null, 'audit payload: ' . json_encode($decision->payload_json));
        $this->assertSame('50000.00', $decision->payload_json['amount'] ?? null);
        $this->assertSame('dismissed', $decision->payload_json['decision'] ?? null);
    }

    public function test_the_review_flag_is_not_in_customer_or_store_order_json(): void
    {
        $order = $this->deliveredOrder($this->store, $this->storeKey, 'own-1');
        $this->payme('CancelTransaction', ['id' => 'own-1', 'reason' => 5])->assertJsonPath('result.state', -2);
        $this->assertNotNull($order->fresh()->store_refund_review_at);

        $customer = $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/orders')->assertOk()->getContent();
        $this->app['auth']->forgetGuards();
        $owner = User::forceCreate(['name' => 'Owner', 'phone' => '+998909991002', 'password' => bcrypt('x'), 'role' => UserRole::OWNER->value, 'store_id' => $this->store->id]);
        $store = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/orders')->assertOk()->getContent();
        $staff = $this->getJson('/api/v1/staff/orders')->assertOk()->getContent();
        $staffOne = $this->getJson("/api/v1/staff/orders/{$order->uuid}")->assertOk()->getContent();

        $this->assertStringNotContainsString('store_refund_review', $customer, 'customer GET /api/v1/orders');
        $this->assertStringNotContainsString('store_refund_review', $store, 'owner GET /api/v1/orders');
        $this->assertStringNotContainsString('store_refund_review', $staff, 'owner GET /api/v1/staff/orders');
        $this->assertStringNotContainsString('store_refund_review', $staffOne, 'owner GET /api/v1/staff/orders/{uuid}');
    }

    public function test_another_cashboxs_transaction_id_is_answered_like_an_unknown_one(): void
    {
        $mine = $this->deliveredOrder($this->store, $this->storeKey, 'own-1');
        $this->deliveredOrder($this->other, $this->otherKey, 'oth-1');

        foreach (['CancelTransaction' => ['reason' => 5], 'CheckTransaction' => [], 'PerformTransaction' => []] as $method => $extra) {
            $unknown = $this->payme($method, ['id' => 'no-such', 'account' => ['order_id' => $mine->uuid]] + $extra)->json('error');
            $foreign = $this->payme($method, ['id' => 'oth-1', 'account' => ['order_id' => $mine->uuid]] + $extra)->json('error');
            $this->assertSame(-31003, $unknown['code'] ?? null, $method);
            $this->assertSame($unknown, $foreign, "{$method}: unknown trx => " . json_encode($unknown) . ", other cashbox's trx => " . json_encode($foreign));
        }

        // The other cashbox's transaction is untouched, and its own key still works on it
        $this->assertSame('paid', Payment::where('provider_transaction_id', 'oth-1')->sole()->status->value);
        $this->payme('CheckTransaction', ['id' => 'oth-1'], $this->otherKey)->assertJsonPath('result.state', 2);
        // Without an account too (security review F5: the id is looked up in the key's own cashbox only), and a key no
        // cashbox has is refused
        $this->payme('CheckTransaction', ['id' => 'oth-1'])->assertJsonPath('error.code', -31003);
        $this->payme('CheckTransaction', ['id' => 'oth-1'], 'no-such-key')->assertJsonPath('error.code', -32504);
    }

    public function test_a_malformed_reason_is_refused_and_never_recorded(): void
    {
        $this->deliveredOrder($this->store, $this->storeKey, 'own-1');
        foreach ([['forged' => true], '5', 5.5, 0, 99, null] as $reason) {
            $this->payme('CancelTransaction', ['id' => 'own-1', 'reason' => $reason])->assertJsonPath('error.code', -32600);
        }

        $payment = Payment::where('provider_transaction_id', 'own-1')->sole();
        $this->assertSame('paid', $payment->status->value);
        $this->assertArrayNotHasKey('store_refund_review', $payment->payload ?? []);

        // A Payme reason is taken as it is
        $this->payme('CancelTransaction', ['id' => 'own-1', 'reason' => 5])->assertJsonPath('result.state', -2);
        $this->assertSame(5, Payment::where('provider_transaction_id', 'own-1')->sole()->payload['store_refund_review']['reason']);
    }
}
