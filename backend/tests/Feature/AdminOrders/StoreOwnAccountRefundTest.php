<?php

namespace Tests\Feature\AdminOrders;

use App\Enums\CancelReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PlatformRole;
use App\Enums\UserRole;
use App\Jobs\SendTelegramRequest;
use App\Models\Order;
use App\Models\OrderLedgerReversal;
use App\Models\Payment;
use App\Services\Admin\Orders\StoreRefundReviewDecision;
use App\Services\Order\OrderStatusService;
use App\Services\Order\OrderTransitionException;
use App\Services\Payment\StoreOwnRefund;
use App\Services\Payment\StoreRefundReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Owner decision 4: a paid order whose money went into the store's own provider account (payment_owner STORE) is
 * refunded by the store from that account — RIZQIM holds none of it and cannot send it back. Such a refund is labelled
 * `refund_by: store_own_account` ("Pulni ovqatxona o'z hisobidan qaytaradi") on the admin order card, the refund
 * request, the partner order detail and the customer's order (until the money is seen back); and RIZQIM's commission on
 * a delivered store-owned order is reversed only after a two-person check (a refund request asked by one member and
 * approved by another, or StoreRefundReview's proposal + confirmation): no single admin, no store action.
 *
 * Numbers: a 50 000 so'm order, commission 5 000 (10 %) booked on the store's debt at delivery.
 */
class StoreOwnAccountRefundTest extends TestCase
{
    use RefreshDatabase;
    use AdminOrdersFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([SendTelegramRequest::class]);
        $this->setUpLifecycleStore();
        $this->store->forceFill(['payment_account_type' => 'own_account', 'credit_limit' => -1000000])->save();
        $this->customer->forceFill(['telegram_id' => '9100100'])->save();
    }

    private function paidOrder(string $provider, string $owner = 'STORE', OrderStatus $status = OrderStatus::ON_THE_WAY): Order
    {
        $order = $this->makeOrder($status, [
            'courier_id' => $this->courier->id,
            'payment_method' => $provider,
            'payment_owner' => $owner,
            'payment_status' => PaymentStatus::PAID->value,
        ]);
        Payment::create([
            'order_id' => $order->id,
            'store_id' => $order->store_id,
            'merchant_scope' => Payment::merchantScopeOf($order),
            'provider' => $provider,
            'provider_transaction_id' => 'txn-' . Str::random(12),
            'amount' => '50000.00',
            'status' => PaymentStatus::PAID->value,
            'paid_at' => now(),
        ]);

        return $order->fresh();
    }

    private function deliveredOrder(string $provider, string $owner = 'STORE'): Order
    {
        $order = $this->paidOrder($provider, $owner);
        $this->assertTrue(app(OrderStatusService::class)->transition($order, OrderStatus::DELIVERED, $this->courier)->changed);
        $this->assertSame($owner === 'STORE' ? '5000.00' : '0.00', $this->debt());

        return $order->fresh();
    }

    private function debt(): string
    {
        return (string) $this->store->fresh()->debt_balance;
    }

    private function reversals(Order $order): int
    {
        return OrderLedgerReversal::where('order_id', $order->id)->count();
    }

    /**
     * @return array{0: string, 1: string} two members' confirmed admin sessions (each may ask and approve)
     */
    private function twoMembers(): array
    {
        return [$this->confirmedSession($this->makeMember()), $this->confirmedSession($this->makeMember())];
    }

    private function card(Order $order, string $token): array
    {
        return $this->adminJson('GET', "/orders/{$order->uuid}", $token)->assertOk()->json('data');
    }

    private function customerOrder(Order $order): array
    {
        $this->app['auth']->forgetGuards();

        return collect($this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/orders')->assertOk()->json('data'))
            ->firstWhere('uuid', $order->uuid);
    }

    public function test_a_single_admin_cannot_reverse_the_commission_of_a_delivered_store_owned_order(): void
    {
        $order = $this->deliveredOrder('click');
        $admin = $this->makeStaff(UserRole::SUPER_ADMIN, null, null, ['store_id' => null]);

        try {
            app(OrderStatusService::class)->refund($order, $admin, 'Mijoz shikoyati');
            $this->fail('A delivered store-owned order is refunded only after a two-person check.');
        } catch (OrderTransitionException $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame('two_person_check', $e->reason);
            $this->assertStringContainsString("qaytarish so'rovi", $e->getMessage());
        }

        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertSame('5000.00', $this->debt());
        $this->assertSame(0, $this->reversals($order));
    }

    public function test_a_named_two_person_check_must_really_have_happened(): void
    {
        $order = $this->deliveredOrder('click');
        $asker = $this->makeMember();
        $refund = fn (\App\Models\User $actor, string $check) => app(OrderStatusService::class)->refund($order->fresh(), $actor, 'Qaytarish', false, $check);
        $refused = function (callable $call): void {
            try {
                $call();
                $this->fail('Refunded without a real two-person check.');
            } catch (OrderTransitionException $e) {
                $this->assertSame('two_person_check', $e->reason);
            }
        };

        // A request that does not exist, one of another order, and the asker approving their own request alone
        $refused(fn () => $refund($asker, 'refund_request:999999'));
        $other = $this->paidOrder('click', 'STORE', OrderStatus::DELIVERED);
        $foreign = app(\App\Services\Admin\Orders\RefundRequestService::class)->request($other, $asker, '50000', 'Boshqa buyurtma');
        $refused(fn () => $refund($this->makeMember(), "refund_request:{$foreign->id}"));
        $own = app(\App\Services\Admin\Orders\RefundRequestService::class)->request($order, $asker, '50000', 'Taom kelmadi');
        $refused(fn () => $refund($asker, "refund_request:{$own->id}"));
        // No cabinet refund waiting, or an unknown check
        $refused(fn () => $refund($this->makeMember(), 'store_refund_review'));
        $refused(fn () => $refund($this->makeMember(), 'trust_me'));

        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertSame('5000.00', $this->debt());
        $this->assertSame(0, $this->reversals($order));
    }

    public function test_a_cash_order_of_the_same_store_is_refunded_by_one_admin_as_before(): void
    {
        // Not store-owned in the sense of decision 4 (no provider account): unchanged (OrderLifecycleRefundTest)
        $order = $this->makeOrder(OrderStatus::ON_THE_WAY, ['courier_id' => $this->courier->id, 'payment_method' => 'cash', 'payment_owner' => 'STORE']);
        app(OrderStatusService::class)->transition($order, OrderStatus::DELIVERED, $this->courier);
        $this->assertSame('5000.00', $this->debt());

        $admin = $this->makeStaff(UserRole::SUPER_ADMIN, null, null, ['store_id' => null]);
        $this->assertTrue(app(OrderStatusService::class)->refund($order->fresh(), $admin, 'Mijoz shikoyati')->changed);
        $this->assertSame('0.00', $this->debt());
        $this->assertNull($this->card($order, $this->confirmedSession($this->makeMember()))['refund_by']);
    }

    public function test_neither_the_store_nor_an_admin_intervention_can_refund_or_cancel_a_delivered_order(): void
    {
        $order = $this->deliveredOrder('payme');

        $this->setStatus($order, $this->owner, 'refunded')->assertStatus(422);
        $this->setStatus($order, $this->owner, 'canceled')->assertStatus(422);

        $token = $this->confirmedSession($this->makeMember());
        $this->adminJson('POST', "/orders/{$order->uuid}/cancel", $token, ['cancel_reason' => CancelReason::OTHER->value, 'reason' => 'Bekor qilish'])
            ->assertStatus(422);
        $this->adminJson('POST', "/orders/{$order->uuid}/status", $token, ['status' => 'refunded', 'reason' => 'Qaytarish'])
            ->assertStatus(422)->assertJsonPath('code', 'use_refund_request');

        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertSame('5000.00', $this->debt());
        $this->assertSame(0, $this->reversals($order));
    }

    public function test_a_full_refund_request_reverses_the_commission_only_on_the_second_members_approval_and_labels_the_store(): void
    {
        foreach (['click', 'payme', 'uzum', 'paynet'] as $provider) {
            $this->store->forceFill(['debt_balance' => '0.00'])->save();
            $order = $this->deliveredOrder($provider);
            [$asker, $approver] = $this->twoMembers();

            $request = $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $asker, ['amount' => '50000', 'reason' => 'Taom kelmadi'])
                ->assertCreated()->assertJsonPath('data.refund_by', StoreOwnRefund::REFUND_BY)->json('data');
            $this->assertSame('5000.00', $this->debt(), $provider);
            $this->adminJson('POST', "/refund-requests/{$request['id']}/approve", $asker, ['reason' => 'Tasdiq'])->assertForbidden()->assertJsonPath('code', 'four_eyes');
            $this->assertSame('5000.00', $this->debt(), $provider);

            $approved = $this->adminJson('POST', "/refund-requests/{$request['id']}/approve", $approver, ['reason' => 'Tasdiq'])->assertOk();
            $approved->assertJsonPath('data.refund_by', StoreOwnRefund::REFUND_BY)
                ->assertJsonPath('data.execution.refund_by', StoreOwnRefund::REFUND_BY)
                ->assertJsonPath('data.execution.mode', 'order_refunded');
            $this->assertStringContainsString("ovqatxona o'z hisobidan qaytaradi", $approved->json('message'));
            $this->assertSame('0.00', $this->debt(), $provider);
            $this->assertSame(1, $this->reversals($order), $provider);

            // A repeated approval changes nothing
            $this->adminJson('POST', "/refund-requests/{$request['id']}/approve", $approver, ['reason' => 'Tasdiq'])->assertOk();
            $this->assertSame('0.00', $this->debt(), $provider);
            $this->assertSame(1, $this->reversals($order), $provider);

            // The card says who sends the money back, so does the order's history
            $card = $this->card($order, $approver);
            $this->assertSame(StoreOwnRefund::REFUND_BY, $card['refund_by'], $provider);
            $this->assertSame(StoreOwnRefund::REFUND_BY, $card['payments'][0]['refund_by'], $provider);
            $this->assertStringContainsString(StoreOwnRefund::LABEL, collect($card['history'])->last()['notes'], $provider);
        }
    }

    public function test_the_customer_is_told_the_restaurant_refunds_until_the_money_is_seen_back(): void
    {
        config(['services.telegram.bot_token' => '123456:store-own-refund']);
        $order = $this->deliveredOrder('payme');
        $this->assertNull($this->customerOrder($order)['refund']);

        [$asker, $approver] = $this->twoMembers();
        $id = $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $asker, ['amount' => '50000', 'reason' => 'Taom kelmadi'])->json('data.id');
        $this->adminJson('POST', "/refund-requests/{$id}/approve", $approver, ['reason' => 'Tasdiq'])->assertOk();

        $this->assertSame(['refund_by' => StoreOwnRefund::REFUND_BY, 'returned' => false], $this->customerOrder($order)['refund']);
        // The Telegram message says so too, never "qaytarildi"
        $text = Queue::pushed(SendTelegramRequest::class)->map(fn (SendTelegramRequest $job) => (string) ($job->params['text'] ?? ''))
            ->first(fn (string $text) => str_contains($text, $order->short_code) && str_contains($text, 'pul'));
        $this->assertNotNull($text);
        $this->assertStringContainsString("ovqatxona o'z hisobidan qaytaradi", mb_strtolower($text));
        $this->assertStringNotContainsString('qaytarildi', $text);
        // ...and so does the app push
        $push = app(\App\Services\Push\OrderPushMessages::class)->forCustomer($order->fresh());
        $this->assertSame('Pulni ovqatxona qaytaradi', $push->title);
        $this->assertStringNotContainsString('qaytarildi', $push->body);

        // The store refunds in its Payme cabinet: Payme's cancel arrives, the payment is refunded
        Payment::where('order_id', $order->id)->update(['status' => PaymentStatus::REFUNDED->value, 'cancelled_at' => now()]);
        $this->assertSame(['refund_by' => StoreOwnRefund::REFUND_BY, 'returned' => true], $this->customerOrder($order)['refund']);
        $this->assertSame('Pul qaytarildi', app(\App\Services\Push\OrderPushMessages::class)->forCustomer($order->fresh())->title);
    }

    public function test_a_partial_refund_reverses_its_share_once_and_only_on_the_second_members_approval(): void
    {
        $order = $this->deliveredOrder('click');
        [$asker, $approver] = $this->twoMembers();

        // 20 000 of 50 000: 2 000 of the 5 000 commission
        $first = $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $asker, ['amount' => '20000', 'reason' => 'Bitta taom yetmadi'])
            ->assertCreated()->assertJsonPath('data.refund_by', StoreOwnRefund::REFUND_BY)->json('data');
        $this->assertSame('5000.00', $this->debt());
        $this->adminJson('POST', "/refund-requests/{$first['id']}/approve", $approver, ['reason' => 'Tasdiq'])->assertOk()
            ->assertJsonPath('data.execution.mode', 'manual')->assertJsonPath('data.execution.refund_by', StoreOwnRefund::REFUND_BY);
        $this->assertSame('3000.00', $this->debt());
        $this->adminJson('POST', "/refund-requests/{$first['id']}/approve", $approver, ['reason' => 'Tasdiq'])->assertOk();
        $this->assertSame('3000.00', $this->debt());
        $this->assertSame(1, $this->reversals($order));

        // The other 30 000: the rest of the commission, once
        $second = $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $approver, ['amount' => '30000', 'reason' => 'Qolgani'])->assertCreated()->json('data');
        $this->assertSame('3000.00', $this->debt());
        $this->adminJson('POST', "/refund-requests/{$second['id']}/approve", $approver, ['reason' => 'Tasdiq'])->assertForbidden();
        $this->adminJson('POST', "/refund-requests/{$second['id']}/approve", $asker, ['reason' => 'Tasdiq'])->assertOk();
        $this->assertSame('0.00', $this->debt());
        $this->assertSame(2, $this->reversals($order));
        $this->assertSame(StoreOwnRefund::REFUND_BY, $this->card($order, $asker)['refund_by']);
    }

    public function test_a_held_cabinet_refund_keeps_the_commission_until_the_second_member_and_dismiss_keeps_it(): void
    {
        $decisions = app(StoreRefundReviewDecision::class);
        $first = $this->makeMember();
        $second = $this->makeMember();

        $held = function (): Order {
            $order = $this->deliveredOrder('payme');
            \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
                $locked = Order::whereKey($order->id)->lockForUpdate()->first();
                $payment = Payment::where('order_id', $order->id)->lockForUpdate()->first();
                $payment->update(['status' => PaymentStatus::REFUNDED, 'cancelled_at' => now()]);
                app(StoreRefundReview::class)->hold($locked, $payment, 'payme', 5);
            });

            return $order->fresh();
        };

        $order = $held();
        $this->assertSame('5000.00', $this->debt());
        // The store gave the money back in its own cabinet: labelled, and the customer sees it back
        $this->assertSame(StoreOwnRefund::REFUND_BY, $this->card($order, $this->confirmedSession($this->makeMember()))['refund_by']);
        $this->assertSame(['refund_by' => StoreOwnRefund::REFUND_BY, 'returned' => true], $this->customerOrder($order)['refund']);
        $this->assertSame(StoreRefundReviewDecision::PROPOSED, $decisions->approve($order, $first, 'Taklif')['decision']);
        $this->assertSame('5000.00', $this->debt());
        $this->assertSame(StoreRefundReview::APPROVED, $decisions->approve($order, $second, 'Tasdiq')['decision']);
        $this->assertSame('0.00', $this->debt());
        $this->assertSame(1, $this->reversals($order));

        $this->store->forceFill(['debt_balance' => '0.00'])->save();
        $dismissed = $held();
        $decisions->dismiss($dismissed, $first, 'Soxta qaytarish');
        $this->assertSame('5000.00', $this->debt());
        $this->assertSame(0, $this->reversals($dismissed));
    }

    public function test_a_cancelled_paid_order_still_in_progress_is_labelled_and_books_no_commission(): void
    {
        $order = $this->paidOrder('click', 'STORE', OrderStatus::ACCEPTED);

        $this->setStatus($order, $this->owner, 'canceled')->assertOk();

        $this->assertSame('0.00', $this->debt());
        $history = $order->statusHistories()->where('status', 'canceled')->sole();
        $this->assertStringContainsString(StoreOwnRefund::LABEL, (string) $history->notes);
        $this->assertSame(StoreOwnRefund::REFUND_BY, $this->card($order, $this->confirmedSession($this->makeMember()))['refund_by']);
        $this->assertSame(['refund_by' => StoreOwnRefund::REFUND_BY, 'returned' => false], $this->customerOrder($order)['refund']);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/staff/orders/{$order->uuid}")->assertOk()
            ->assertJsonPath('data.refund_by', StoreOwnRefund::REFUND_BY);
    }

    public function test_a_paylov_refund_rizqim_could_not_make_is_the_stores_to_make(): void
    {
        $order = $this->paidOrder('paylov', 'STORE', OrderStatus::ACCEPTED);
        $payment = Payment::where('order_id', $order->id)->sole();
        $token = $this->confirmedSession($this->makeMember());
        // RIZQIM gives a store-owned Paylov payment back itself (payment/cancel with the store's account): not labelled
        $this->assertNull($this->card($order, $token)['refund_by']);

        $payment->forceFill(['payload' => ['paylov' => ['state' => 'confirmed', 'refund' => ['status' => 'failed']], 'manual_refund_required' => true, 'manual_refund_reason' => 'paylov_cancel_failed']])->save();

        $this->assertSame(StoreOwnRefund::REFUND_BY, $this->card($order, $token)['refund_by']);
    }

    public function test_platform_collected_orders_are_refunded_as_before(): void
    {
        $order = $this->deliveredOrder('click', 'PLATFORM');
        // P4-23 netting: the 50 000 paid the 5 000 commission, 45 000 reached the wallet
        $this->assertSame(['0.00', '45000.00'], [(string) $this->store->fresh()->debt_balance, (string) $this->store->fresh()->wallet_balance]);
        [$asker, $approver] = $this->twoMembers();

        $id = $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $asker, ['amount' => '50000', 'reason' => 'Taom kelmadi'])
            ->assertCreated()->assertJsonPath('data.refund_by', null)->json('data.id');
        $this->adminJson('POST', "/refund-requests/{$id}/approve", $approver, ['reason' => 'Tasdiq'])->assertOk()
            ->assertJsonPath('data.refund_by', null)->assertJsonPath('data.execution.refund_by', null)
            ->assertJsonPath('message', "Tasdiqlandi. 50 000 so'mni mijozga to'lov tizimi kabinetida yoki naqd qaytaring.");

        $this->assertSame(['0.00', '0.00'], [(string) $this->store->fresh()->debt_balance, (string) $this->store->fresh()->wallet_balance]);
        $this->assertNull($this->card($order, $approver)['refund_by']);
        $this->assertNull($this->customerOrder($order)['refund']);

        // A single admin's direct refund of a platform-collected order is unchanged (OrderLifecycleRefundTest)
        $other = $this->deliveredOrder('payme', 'PLATFORM');
        $admin = $this->makeStaff(UserRole::SUPER_ADMIN, null, null, ['store_id' => null]);
        $this->assertTrue(app(OrderStatusService::class)->refund($other, $admin, 'Mijoz shikoyati')->changed);
    }
}
