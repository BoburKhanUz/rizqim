<?php

namespace Tests\Feature\AdminOrders;

use App\Enums\OrderStatus;
use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderRefundRequest;
use App\Models\Payment;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-14: refund requests (amount ≤ paid − refunded, one pending per order), four-eyes approval through the existing
 * refund path, idempotent approval, rejection, audit.
 */
class AdminRefundRequestTest extends TestCase
{
    use RefreshDatabase;
    use AdminOrdersFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    private function deliveredKeshbekOrder(): Order
    {
        $customer = $this->makeCustomer(100000);
        $order = $this->checkout($customer, 2, 'keshbek'); // 100 000 so'm, paid from the balance at checkout
        $service = app(OrderStatusService::class);
        foreach ([OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::READY, OrderStatus::DELIVERED] as $status) {
            $service->transition($order, $status, $this->manager);
        }

        return $order->refresh();
    }

    public function test_full_refund_of_a_delivered_order_goes_through_the_refund_path_with_four_eyes(): void
    {
        $order = $this->deliveredKeshbekOrder();
        $customer = $order->user;
        $this->assertEquals(0, (float) $customer->fresh()->balance);

        $support = $this->makeMember(PlatformRole::SUPPORT);
        $supportToken = $this->confirmedSession($support);

        $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $supportToken, ['amount' => '100000.01', 'reason' => 'Taom sovuq keldi'])
            ->assertStatus(422)->assertJsonPath('code', 'amount_exceeds_refundable')->assertJsonPath('data.refundable', '100000.00');
        $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $supportToken, ['amount' => '100000'])
            ->assertStatus(422)->assertJsonValidationErrors('reason');

        $request = $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $supportToken, ['amount' => '100000', 'reason' => 'Taom sovuq keldi'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.status_label', 'Tasdiq kutilmoqda')
            ->assertJsonPath('data.mine', true)
            ->assertJsonPath('data.can_decide', false)
            ->json('data');
        $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $supportToken, ['amount' => '1000', 'reason' => 'Yana bittasi'])
            ->assertStatus(409)->assertJsonPath('code', 'refund_request_pending');

        // The requester cannot approve, even with the permission (a superadmin who asked)
        $boss = $this->makeMember();
        $bossToken = $this->confirmedSession($boss);
        $own = $this->deliveredKeshbekOrder();
        $ownRequest = $this->adminJson('POST', "/orders/{$own->uuid}/refund-requests", $bossToken, ['amount' => '500', 'reason' => "Qisman qaytarish"])->assertCreated()->json('data');
        $this->adminJson('POST', "/refund-requests/{$ownRequest['id']}/approve", $bossToken, ['reason' => "O'zim tasdiqlayman"])
            ->assertForbidden()->assertJsonPath('code', 'four_eyes');

        // Support has no approve permission at all
        $this->adminJson('POST', "/refund-requests/{$request['id']}/approve", $supportToken, ['reason' => 'Tasdiq'])->assertForbidden();

        $finance = $this->makeMember(PlatformRole::FINANCE);
        $financeToken = $this->signIn($finance);
        $this->adminJson('POST', "/refund-requests/{$request['id']}/approve", $financeToken, ['reason' => 'Tasdiq'])
            ->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->stepUp($finance, $financeToken);

        $card = $this->adminJson('GET', "/orders/{$order->uuid}", $financeToken)->json('data');
        $this->assertTrue($card['refunds']['requests'][0]['can_decide']);

        $approved = $this->adminJson('POST', "/refund-requests/{$request['id']}/approve", $financeToken, ['reason' => 'Shikoyat tasdiqlandi'], )
            ->assertOk()->json('data');
        $this->assertSame('approved', $approved['status']);
        $this->assertSame('order_refunded', $approved['execution']['mode']);
        $this->assertSame('delivered', $approved['execution']['order_status_before']);
        $this->assertSame('refunded', $approved['execution']['order_status_after']);
        $this->assertSame('100000.00', $approved['execution']['automatic_amount']);
        $this->assertSame('0.00', $approved['execution']['manual_amount']);

        // The existing path did the work: refunded status, keshbek back, the store's ledger reversed
        $order->refresh();
        $this->assertSame(OrderStatus::REFUNDED, $order->status);
        $this->assertEquals(100000, (float) $customer->fresh()->balance);
        $this->assertSame(1, AuditLog::where('action', 'order.refunded')->where('model_id', $order->id)->where('user_id', $finance->id)->count());

        $card = $this->adminJson('GET', "/orders/{$order->uuid}", $financeToken)->json('data');
        $this->assertSame('0.00', $card['refunds']['refundable']);
        $this->assertFalse($card['actions']['can_request_refund']);

        // A repeat is the same answer, nothing runs twice, nothing is audited twice
        $this->adminJson('POST', "/refund-requests/{$request['id']}/approve", $financeToken, ['reason' => 'Shikoyat tasdiqlandi'])
            ->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertEquals(100000, (float) $customer->fresh()->balance);
        $this->assertSame(1, AuditLog::where('action', 'admin.refund_requests.approved')->count());
        $this->adminJson('POST', "/refund-requests/{$request['id']}/reject", $financeToken, ['reason' => 'Fikr o\'zgardi'])
            ->assertStatus(409)->assertJsonPath('code', 'refund_request_decided');

        $entry = AuditLog::where('action', 'admin.refund_requests.approved')->sole();
        $this->assertSame($order->id, (int) $entry->model_id);
        $this->assertSame('Shikoyat tasdiqlandi', $entry->payload_json['reason']);
        $this->assertSame(['refund_request_status' => 'approved'], $entry->after);
        $this->assertSame($request['id'], $entry->payload_json['refund_request_id']);
        $this->assertSame('order_refunded', $entry->payload_json['execution']['mode']);
        $this->assertSame(1, AuditLog::where('action', 'admin.orders.refund_requested')->where('user_id', $support->id)->count());
    }

    public function test_approval_is_idempotent_with_an_idempotency_key(): void
    {
        $order = $this->orderWithHistory(100, ['canceled' => 50], ['cancel_reason' => 'store_closed']);
        $this->paidPayment($order, 'click', '50000.00', ['manual_refund_required' => true]);
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $request = $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $this->confirmedSession($operator), ['amount' => '50000', 'reason' => 'Bekor qilingan buyurtma'])
            ->assertCreated()->json('data');

        $finance = $this->makeMember(PlatformRole::FINANCE);
        $token = $this->confirmedSession($finance);
        $headers = ['Idempotency-Key' => 'approve-' . $request['id']];
        $first = $this->confirmedJson('POST', "/refund-requests/{$request['id']}/approve", $token, ['reason' => 'Tasdiqlandi'], $headers)->assertOk();
        $second = $this->confirmedJson('POST', "/refund-requests/{$request['id']}/approve", $token, ['reason' => 'Tasdiqlandi'], $headers)->assertOk();

        $second->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame($first->json('data'), $second->json('data'));
        $this->assertSame(1, AuditLog::where('action', 'admin.refund_requests.approved')->count());

        // A cancelled card payment: recorded as a manual refund, the order stays cancelled
        $this->assertSame('manual', $first->json('data.execution.mode'));
        $this->assertSame('50000.00', $first->json('data.execution.manual_amount'));
        $this->assertSame('manual', $first->json('data.execution.provider_refund'));
        $this->assertStringContainsString("50 000 so'm", $first->json('message'));
        $this->assertSame(OrderStatus::CANCELED, $order->fresh()->status);
        $payment = Payment::where('order_id', $order->id)->sole();
        $this->assertSame([(int) $request['id']], $payment->payload['refund_request_ids']);
        $this->assertSame('50000.00', $payment->payload['manual_refund_approved'][0]['amount']);

        $card = $this->adminJson('GET', "/orders/{$order->uuid}", $token)->json('data');
        $this->assertSame('0.00', $card['refunds']['refundable']);
        $this->assertFalse($card['manual_refund_required']);

        // A later Payme/Click refund of the same payment is not counted twice
        $payment->update(['status' => 'refunded']);
        $card = $this->adminJson('GET', "/orders/{$order->uuid}", $token)->json('data');
        $this->assertSame('50000.00', $card['refunds']['refunded']);
    }

    public function test_partial_refunds_reject_and_rules(): void
    {
        $order = $this->orderWithHistory(100, ['accepted' => 90, 'preparing' => 80, 'ready' => 60, 'delivered' => 30], ['order_type' => 'pickup']);
        $this->paidPayment($order, 'payme');
        $active = $this->orderWithHistory(10, ['accepted' => 9]);
        $this->paidPayment($active, 'payme');

        $support = $this->makeMember(PlatformRole::SUPPORT);
        $token = $this->confirmedSession($support);
        $this->adminJson('POST', "/orders/{$active->uuid}/refund-requests", $token, ['amount' => '1000', 'reason' => 'Kam keldi'])
            ->assertStatus(422)->assertJsonPath('code', 'order_active');
        $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $token, ['amount' => '0', 'reason' => 'Kam keldi'])
            ->assertStatus(422);
        $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $token, ['amount' => '12.345', 'reason' => 'Kam keldi'])
            ->assertStatus(422)->assertJsonValidationErrors('amount');

        $viewer = $this->makeMember(PlatformRole::VIEWER);
        $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $this->confirmedSession($viewer), ['amount' => '1000', 'reason' => 'Kam keldi'])
            ->assertForbidden();

        $first = $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $token, ['amount' => '20000', 'reason' => 'Bitta taom kam keldi'])->assertCreated()->json('data');

        $finance = $this->makeMember(PlatformRole::FINANCE);
        $financeToken = $this->confirmedSession($finance);
        $this->adminJson('POST', "/refund-requests/{$first['id']}/reject", $financeToken, [])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adminJson('POST', "/refund-requests/{$first['id']}/reject", $financeToken, ['reason' => 'Rasm yo\'q'])
            ->assertOk()->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.decision_note', "Rasm yo'q");
        $this->assertSame(1, AuditLog::where('action', 'admin.refund_requests.rejected')->count());

        // A partial amount of a delivered order: a manual refund, the order stays delivered
        $second = $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $token, ['amount' => '20000', 'reason' => 'Rasm yuborildi'])->assertCreated()->json('data');
        $approved = $this->adminJson('POST', "/refund-requests/{$second['id']}/approve", $financeToken, ['reason' => 'Rasm bor'])->assertOk()->json('data');
        $this->assertSame('manual', $approved['execution']['mode']);
        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);

        $card = $this->adminJson('GET', "/orders/{$order->uuid}", $token)->json('data');
        $this->assertSame('30000.00', $card['refunds']['refundable']);
        $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $token, ['amount' => '30000.01', 'reason' => 'Hammasi'])
            ->assertStatus(422)->assertJsonPath('data.refundable', '30000.00');

        // The approval queue
        $queue = $this->adminJson('GET', '/refund-requests?status=all', $financeToken)->assertOk()->json('data.data');
        $this->assertCount(2, $queue);
        $this->assertSame((string) $order->uuid, $queue[0]['order']['uuid']);
        $this->assertSame(0, count($this->adminJson('GET', '/refund-requests', $financeToken)->json('data.data')));
        $this->assertSame(2, OrderRefundRequest::count());
    }
}
