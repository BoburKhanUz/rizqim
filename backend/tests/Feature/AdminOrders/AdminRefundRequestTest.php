<?php

namespace Tests\Feature\AdminOrders;

use App\Enums\OrderStatus;
use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderRefundRequest;
use App\Models\Payment;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-14: refund requests (amount ≤ paid − refunded, one pending per order), four-eyes approval through the existing
 * refund path, idempotent approval, rejection, audit; parts of a keshbek order back to the balance, provider refunds
 * after a partial approval, orders with several payments.
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

    /**
     * @return array{0: string, 1: string} a requester's and an approver's confirmed sessions
     */
    private function requesterAndApprover(): array
    {
        return [$this->confirmedSession($this->makeMember(PlatformRole::OPERATOR)), $this->confirmedSession($this->makeMember(PlatformRole::FINANCE))];
    }

    /**
     * @return array<string, mixed> the approved request
     */
    private function askAndApprove(Order $order, string $amount, string $requester, string $approver): array
    {
        $request = $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $requester, ['amount' => $amount, 'reason' => 'Qisman qaytarish'])
            ->assertCreated()->json('data');

        return $this->adminJson('POST', "/refund-requests/{$request['id']}/approve", $approver, ['reason' => 'Tasdiqlandi'])->assertOk()->json('data');
    }

    /**
     * @return array<string, mixed>
     */
    private function refundsOf(Order $order, string $token): array
    {
        $card = $this->adminJson('GET', "/orders/{$order->uuid}", $token)->assertOk()->json('data');

        return ['paid' => $card['refunds']['paid'], 'refunded' => $card['refunds']['refunded'], 'refundable' => $card['refunds']['refundable'], 'manual_refund_required' => $card['manual_refund_required'], 'can_request_refund' => $card['actions']['can_request_refund'], 'payments' => $card['payments']];
    }

    public function test_a_keshbek_order_refunded_in_two_parts_returns_exactly_the_amount_to_the_balance(): void
    {
        $order = $this->deliveredKeshbekOrder();
        $customer = $order->user;
        [$requester, $approver] = $this->requesterAndApprover();
        $ledgerRows = fn () => StoreTransaction::where('order_id', $order->id)->count() + StoreWalletTransaction::where('order_id', $order->id)->count();
        $ledgerBefore = $ledgerRows();

        $first = $this->askAndApprove($order, '30000', $requester, $approver);
        $this->assertSame('balance', $first['execution']['mode']);
        $this->assertSame('30000.00', $first['execution']['automatic_amount']);
        $this->assertSame('0.00', $first['execution']['manual_amount']);
        $this->assertSame('none', $first['execution']['provider_refund']);
        $this->assertSame('delivered', $first['execution']['order_status_after']);
        $this->assertEquals([['provider' => 'keshbek', 'amount' => '30000.00', 'kind' => 'balance']], array_map(fn (array $row) => array_diff_key($row, ['payment_id' => 1]), $first['execution']['allocations']));
        $this->assertSame('30000.00', $customer->fresh()->balance);
        $payment = Payment::where('order_id', $order->id)->sole();
        $this->assertSame('paid', $payment->status->value);
        $this->assertArrayNotHasKey('manual_refund_approved', $payment->payload);
        $this->assertSame(['paid' => '100000.00', 'refunded' => '30000.00', 'refundable' => '70000.00'], array_slice($this->refundsOf($order, $approver), 0, 3));

        // The rest: a part too (something was given back before), exactly 70 000 more, never the whole payment again
        $second = $this->askAndApprove($order, '70000', $requester, $approver);
        $this->assertSame('balance', $second['execution']['mode']);
        $this->assertSame('70000.00', $second['execution']['automatic_amount']);
        $this->assertSame('100000.00', $customer->fresh()->balance);
        $order->refresh();
        $this->assertSame(OrderStatus::DELIVERED, $order->status);
        $this->assertSame('refunded', $order->payment_status->value);
        $payment->refresh();
        $this->assertSame('refunded', $payment->status->value);
        $this->assertSame(['30000.00', '70000.00'], array_column($payment->payload['refunded_to_balance_parts'], 'amount'));
        $refunds = $this->refundsOf($order, $approver);
        $this->assertSame(['paid' => '100000.00', 'refunded' => '100000.00', 'refundable' => '0.00'], array_slice($refunds, 0, 3));
        $this->assertFalse($refunds['can_request_refund']);
        $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $requester, ['amount' => '1', 'reason' => 'Yana'])
            ->assertStatus(422)->assertJsonPath('code', 'amount_exceeds_refundable');
        // Parts leave the store's ledger as it was (docs/API.md: not yet)
        $this->assertSame($ledgerBefore, $ledgerRows());

        // The order's own refund path afterwards finds no keshbek left to return
        app(OrderStatusService::class)->refund($order, null, 'Tizim');
        $this->assertSame('100000.00', $customer->fresh()->balance);
        $this->assertSame(['paid' => '100000.00', 'refunded' => '100000.00', 'refundable' => '0.00'], array_slice($this->refundsOf($order, $approver), 0, 3));
    }

    public function test_the_order_refund_path_after_a_partial_request_returns_only_the_rest_of_the_keshbek(): void
    {
        $order = $this->deliveredKeshbekOrder();
        $customer = $order->user;
        [$requester, $approver] = $this->requesterAndApprover();
        $this->askAndApprove($order, '30000', $requester, $approver);
        $this->assertSame('30000.00', $customer->fresh()->balance);

        // The Filament "Qaytarish" button (and any other caller) goes through OrderStatusService::refund
        $superadmin = $this->makeMember();
        $result = app(OrderStatusService::class)->refund($order, $superadmin, "Mijoz shikoyati");
        $this->assertTrue($result->changed);
        $this->assertSame('100000.00', $customer->fresh()->balance); // 30 000 + the remaining 70 000, not 130 000
        $order->refresh();
        $this->assertSame(OrderStatus::REFUNDED, $order->status);
        $this->assertSame('refunded', $order->payment_status->value);
        $payment = Payment::where('order_id', $order->id)->sole();
        $this->assertSame('refunded', $payment->status->value);
        $this->assertEquals(70000, $payment->payload['refunded_to_balance']);
        $this->assertEquals(30000, $payment->payload['refunded_to_balance_by_requests']);
        $this->assertSame(['paid' => '100000.00', 'refunded' => '100000.00', 'refundable' => '0.00'], array_slice($this->refundsOf($order, $approver), 0, 3));

        // Again: nothing is left, nothing moves
        app(OrderStatusService::class)->refund($order, $superadmin, "Yana bir bor");
        $this->assertSame('100000.00', $customer->fresh()->balance);

        // Two parts that return everything, then the refund path: no keshbek left to return
        $whole = $this->deliveredKeshbekOrder();
        $this->askAndApprove($whole, '40000', $requester, $approver);
        $this->askAndApprove($whole, '60000', $requester, $approver);
        $wholeCustomer = $whole->user;
        $this->assertSame('100000.00', $wholeCustomer->fresh()->balance);
        app(OrderStatusService::class)->refund($whole->fresh(), $superadmin, 'Hammasi');
        $this->assertSame('100000.00', $wholeCustomer->fresh()->balance);
    }

    public function test_a_provider_refund_after_a_partial_approval_leaves_nothing_to_refund(): void
    {
        $order = $this->orderWithHistory(100, ['canceled' => 50], ['cancel_reason' => 'store_closed']);
        $payment = $this->paidPayment($order, 'payme', '50000.00', ['manual_refund_required' => true]);
        [$requester, $approver] = $this->requesterAndApprover();
        $row = fn () => collect($this->adminJson('GET', '/orders?status=canceled&from=2000-01-01', $approver)->assertOk()->json('data.data'))->firstWhere('uuid', (string) $order->uuid);
        $this->assertTrue($row()['manual_refund_required']);

        $approved = $this->askAndApprove($order, '10000', $requester, $approver);
        $this->assertSame('manual', $approved['execution']['mode']);
        $this->assertSame('10000.00', $approved['execution']['manual_amount']);

        // 40 000 is still owed: the order still needs a manual refund, and so does the payment
        $this->assertTrue($row()['manual_refund_required']);
        $refunds = $this->refundsOf($order, $approver);
        $this->assertSame(['paid' => '50000.00', 'refunded' => '10000.00', 'refundable' => '40000.00', 'manual_refund_required' => true], array_slice($refunds, 0, 4));
        $this->assertTrue($refunds['payments'][0]['manual_refund_required']);
        $this->assertEquals([['refund_request_id' => $approved['id'], 'amount' => '10000.00']], array_map(fn (array $row) => array_diff_key($row, ['approved_at' => 1]), $refunds['payments'][0]['manual_refund_approved']));

        // Payme cancels the whole transaction from its cabinet (CancelTransaction): all 50 000 went back
        $payment->update(['status' => 'refunded']);
        $refunds = $this->refundsOf($order, $approver);
        $this->assertSame(['paid' => '50000.00', 'refunded' => '50000.00', 'refundable' => '0.00', 'manual_refund_required' => false], array_slice($refunds, 0, 4));
        $this->assertFalse($refunds['can_request_refund']);
        $this->assertFalse($row()['manual_refund_required']);
        $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $requester, ['amount' => '1', 'reason' => 'Yana'])
            ->assertStatus(422)->assertJsonPath('code', 'amount_exceeds_refundable');

        // A request asked before that refund is refused at approval, not paid twice
        $other = $this->orderWithHistory(100, ['canceled' => 50], ['cancel_reason' => 'store_closed']);
        $otherPayment = $this->paidPayment($other, 'click', '50000.00', ['manual_refund_required' => true]);
        $pending = $this->adminJson('POST', "/orders/{$other->uuid}/refund-requests", $requester, ['amount' => '20000', 'reason' => 'Qisman'])->assertCreated()->json('data');
        $otherPayment->update(['status' => 'refunded']);
        $this->adminJson('POST', "/refund-requests/{$pending['id']}/approve", $approver, ['reason' => 'Tasdiq'])
            ->assertStatus(422)->assertJsonPath('code', 'amount_exceeds_refundable')->assertJsonPath('data.refundable', '0.00');
    }

    public function test_an_order_with_several_payments_spreads_approvals_and_counts_provider_refunds_once(): void
    {
        [$requester, $approver] = $this->requesterAndApprover();

        // Click 30 000 + Payme 20 000; 40 000 approved: Click in full, 10 000 of Payme
        $order = $this->orderWithHistory(100, ['canceled' => 50], ['cancel_reason' => 'store_closed']);
        $click = $this->paidPayment($order, 'click', '30000.00', ['manual_refund_required' => true]);
        $payme = $this->paidPayment($order, 'payme', '20000.00', ['manual_refund_required' => true]);
        $approved = $this->askAndApprove($order, '40000', $requester, $approver);
        $this->assertSame([[$click->id, '30000.00', 'manual'], [$payme->id, '10000.00', 'manual']], array_map(fn (array $row) => [$row['payment_id'], $row['amount'], $row['kind']], $approved['execution']['allocations']));
        $this->assertSame('30000.00', $click->fresh()->payload['manual_refund_approved'][0]['amount']);
        $this->assertSame('10000.00', $payme->fresh()->payload['manual_refund_approved'][0]['amount']);
        $refunds = $this->refundsOf($order, $approver);
        $this->assertSame('10000.00', $refunds['refundable']);
        $this->assertSame([false, true], array_column($refunds['payments'], 'manual_refund_required'));

        // Payme refunds its 20 000: its approved 10 000 is inside that, Click's 30 000 still counts
        $payme->update(['status' => 'refunded']);
        $this->assertSame(['paid' => '50000.00', 'refunded' => '50000.00', 'refundable' => '0.00'], array_slice($this->refundsOf($order, $approver), 0, 3));

        // Same order shape, but Click refunds its 30 000 first: 10 000 of Payme is approved, 10 000 is left
        $order = $this->orderWithHistory(100, ['canceled' => 50], ['cancel_reason' => 'store_closed']);
        $click = $this->paidPayment($order, 'click', '30000.00', ['manual_refund_required' => true]);
        $payme = $this->paidPayment($order, 'payme', '20000.00', ['manual_refund_required' => true]);
        $this->askAndApprove($order, '40000', $requester, $approver);
        $click->update(['status' => 'refunded']);
        $this->assertSame(['paid' => '50000.00', 'refunded' => '40000.00', 'refundable' => '10000.00'], array_slice($this->refundsOf($order, $approver), 0, 3));

        // The rest goes on the payment still paid; the refunded one is never tagged with it
        $rest = $this->askAndApprove($order, '10000', $requester, $approver);
        $this->assertSame([[$payme->id, '10000.00']], array_map(fn (array $row) => [$row['payment_id'], $row['amount']], $rest['execution']['allocations']));
        $this->assertNotContains($rest['id'], $click->fresh()->payload['refund_request_ids']);
        $this->assertContains($rest['id'], $payme->fresh()->payload['refund_request_ids']);
        $this->assertSame(['paid' => '50000.00', 'refunded' => '50000.00', 'refundable' => '0.00'], array_slice($this->refundsOf($order, $approver), 0, 3));
        $this->assertFalse($this->refundsOf($order, $approver)['manual_refund_required']);
    }

    public function test_a_refund_with_tiyin_is_said_exactly(): void
    {
        $order = $this->orderWithHistory(100, ['canceled' => 50], ['cancel_reason' => 'store_closed']);
        $this->paidPayment($order, 'click', '50000.50', ['manual_refund_required' => true]);
        [$requester, $approver] = $this->requesterAndApprover();
        $request = $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $requester, ['amount' => '50000.75', 'reason' => 'Hammasi'])
            ->assertStatus(422)->assertJsonPath('data.refundable', '50000.50');
        $this->assertStringContainsString("50 000,50 so'm", $request->json('message'));

        $request = $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $requester, ['amount' => '50000.5', 'reason' => 'Hammasi'])->assertCreated()->json('data');
        $response = $this->adminJson('POST', "/refund-requests/{$request['id']}/approve", $approver, ['reason' => 'Tasdiq'])->assertOk();
        $this->assertStringContainsString("50 000,50 so'm", $response->json('message'));
        $this->assertSame('50000.50', $response->json('data.execution.manual_amount'));
    }
}
