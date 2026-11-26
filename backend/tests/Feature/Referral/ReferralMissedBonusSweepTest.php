<?php

namespace Tests\Feature\Referral;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Jobs\SendTelegramRequest;
use App\Jobs\SweepMissedReferralBonusesJob;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Finance\SettlementRecoveryService;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * Owner decision C: MarkOrderAsPaid books a delivered order's late payment in two transactions — the payment status
 * and the store's settlement first, the referral bonus after. A process that dies between them (no marker:
 * settlement_failed_at is written only by a booking that failed) leaves the bonus unbooked, and a provider does not
 * replay a payment it got an answer for. SweepMissedReferralBonusesJob (hourly) grants such a bonus, and only the
 * bonus (LedgerService::grantMissedReferralBonus, under the order's lock, then the grant's), so every rule of the
 * grant applies: exactly once, the monthly cap, the commission that must cover the bonus, a refunded order never.
 * Review M1: only for an order paid after its delivery, decided as of the payment: an order the grant refused (at
 * delivery, or at the late payment) stays refused.
 */
class ReferralMissedBonusSweepTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use ReferralOrders;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([SendTelegramRequest::class]);
        $this->setUpLifecycleStore();
        $this->referrer = $this->makeCustomer();
        $this->customer->forceFill(['referred_by' => $this->referrer->id])->save();
    }

    /**
     * A delivered order whose payment landed later, and only the listener's first transaction committed: paid and
     * settled, the bonus never booked, no marker.
     */
    private function paidButBonusLost(string $how = 'platform_click_unpaid', array $attributes = []): Order
    {
        $order = $this->deliveredOrder($how, $attributes);
        // The payment lands after the delivery (the only gap the sweep exists for)
        $this->travel(1)->minutes();
        DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            Payment::where('order_id', $order->id)->update(['status' => PaymentStatus::PAID->value, 'paid_at' => now()]);
            $locked->update(['payment_status' => PaymentStatus::PAID->value]);
            app(SettlementRecoveryService::class)->settleLatePayment($locked);
        });
        $order->refresh();
        $this->assertNull($order->settlement_failed_at);
        $this->assertSame([], array_values(array_filter($this->rewards(), fn (array $row) => $row['order_id'] === $order->id)));

        return $order;
    }

    private function sweep(): void
    {
        app()->call([new SweepMissedReferralBonusesJob, 'handle']);
    }

    /** @return list<int> the orders that carry a bonus row */
    private function rewardedOrders(): array
    {
        return array_values(array_unique(array_column($this->rewards(), 'order_id')));
    }

    public function test_a_lost_bonus_is_booked_once_by_the_sweep(): void
    {
        $order = $this->paidButBonusLost();
        $this->travel(20)->minutes();
        Log::spy();

        $this->sweep();

        $this->assertSame([['order_id' => $order->id, 'store_id' => $this->store->id, 'amount' => '5000.00']], $this->rewards());
        $this->assertSame(5000.0, $this->referrerBalance());
        $this->assertNull($order->fresh()->settlement_failed_at);
        Log::shouldHaveReceived('info')->withArgs(fn (string $message) => str_contains($message, 'Missed referral bonus') && str_contains($message, (string) $order->uuid))->once();

        // A second run, and a later order of the same customer: nothing more
        $this->paidButBonusLost();
        $this->travel(20)->minutes();
        $this->sweep();
        $this->assertSame([$order->id], $this->rewardedOrders());
        $this->assertSame(5000.0, $this->referrerBalance());
    }

    public function test_an_order_paid_a_moment_ago_is_left_to_the_listener_and_an_old_one_to_the_window(): void
    {
        $this->paidButBonusLost();
        $this->sweep();
        $this->assertSame([], $this->rewards(), 'the listener may still be booking it');

        $this->travel(SweepMissedReferralBonusesJob::WINDOW_DAYS)->days();
        $this->travel(1)->hours();
        $this->sweep();
        $this->assertSame([], $this->rewards(), 'outside the bounded window');
    }

    public function test_an_order_that_did_not_earn_the_bonus_is_untouched(): void
    {
        // Below the minimum, paid into the store's own Click account (not verified by the platform), a commission
        // below the bonus: each of the customer's, none earns it, the referral stays open
        $this->paidButBonusLost('platform_click_unpaid', ['total_price' => 40000]);
        $this->paidButBonusLost('platform_click_unpaid', ['commission_amount' => 4000]);
        $store = $this->deliveredOrder('store_click');
        $this->travel(20)->minutes();

        $this->sweep();

        $this->assertSame([], $this->rewards());
        $this->assertSame(0.0, $this->referrerBalance());
        $this->assertNull($store->fresh()->settlement_failed_at);
    }

    public function test_the_monthly_cap_holds_and_a_refunded_order_is_untouched(): void
    {
        // The referrer's 10 bonuses of the month, through the real grant
        for ($i = 0; $i < \App\Services\Auth\ReferralService::MONTHLY_CAP_PER_REFERRER; $i++) {
            $friend = $this->makeCustomer();
            $friend->forceFill(['referred_by' => $this->referrer->id])->save();
            $this->deliveredOrder('platform_click', ['user_id' => $friend->id]);
        }
        $this->assertCount(10, $this->rewards());
        $capped = $this->paidButBonusLost();

        $refundedCustomer = $this->makeCustomer();
        $otherReferrer = $this->makeCustomer();
        $refundedCustomer->forceFill(['referred_by' => $otherReferrer->id])->save();
        $refunded = $this->paidButBonusLost('platform_click_unpaid', ['user_id' => $refundedCustomer->id]);
        $admin = $this->makeStaff(UserRole::SUPER_ADMIN, null, null, ['store_id' => null]);
        $this->assertTrue(app(OrderStatusService::class)->refund($refunded->fresh(), $admin, 'Mijoz shikoyati')->changed);
        $this->assertSame(OrderStatus::REFUNDED, $refunded->fresh()->status);
        $this->travel(20)->minutes();

        $this->sweep();

        $this->assertCount(10, $this->rewards());
        $this->assertNotContains($capped->id, $this->rewardedOrders());
        $this->assertNotContains($refunded->id, $this->rewardedOrders());
        $this->assertSame('0.00', (string) $otherReferrer->fresh()->balance);
        $this->assertNull($capped->fresh()->settlement_failed_at, 'a cap is not a failure');
    }

    public function test_the_candidate_query_is_bounded_and_indexed(): void
    {
        $queries = [];
        DB::listen(function (\Illuminate\Database\Events\QueryExecuted $query) use (&$queries) {
            if (preg_match('/^\s*select/i', $query->sql) === 1 && str_contains($query->sql, '"orders"')) {
                $queries[] = [$query->sql, $query->bindings];
            }
        });
        $this->sweep();

        $this->assertCount(1, $queries, 'one candidate query per run when nothing is found');
        $this->assertStringContainsString('limit ' . SweepMissedReferralBonusesJob::MAX_PER_RUN, $queries[0][0]);
        $plan = DB::transaction(function () use ($queries) {
            DB::statement('SET LOCAL enable_seqscan = off');

            return implode("\n", array_column(array_map(fn ($row) => (array) $row, DB::select('EXPLAIN ' . $queries[0][0], $queries[0][1])), 'QUERY PLAN'));
        });
        $this->assertStringContainsString('payments_paid_at_index', $plan);
    }

    private function tashkent(string $at): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::parse($at, 'Asia/Tashkent')->setTimezone((string) config('app.timezone'));
    }

    /** The referrer's ReferralService::MONTHLY_CAP_PER_REFERRER bonuses of the month, through the real grant */
    private function fillTheCap(): array
    {
        $orders = [];
        for ($i = 0; $i < \App\Services\Auth\ReferralService::MONTHLY_CAP_PER_REFERRER; $i++) {
            $friend = $this->makeCustomer();
            $friend->forceFill(['referred_by' => $this->referrer->id])->save();
            $orders[] = $this->deliveredOrder('platform_click', ['user_id' => $friend->id]);
        }
        $this->assertCount(\App\Services\Auth\ReferralService::MONTHLY_CAP_PER_REFERRER, $this->rewards());

        return $orders;
    }

    /** A delivered, unpaid order paid a minute later through the real listener (both of its transactions) */
    private function paidLate(array $attributes = []): Order
    {
        $order = $this->deliveredOrder('platform_click_unpaid', $attributes);
        $this->travel(1)->minutes();
        $payment = Payment::where('order_id', $order->id)->sole();
        $payment->update(['status' => PaymentStatus::PAID, 'paid_at' => now()]);
        event(new \App\Events\PaymentPaid($payment));

        return $order->fresh();
    }

    private function refundInFull(Order $order): void
    {
        $admin = $this->makeStaff(UserRole::SUPER_ADMIN, null, null, ['store_id' => null]);
        $this->assertTrue(app(OrderStatusService::class)->refund($order->fresh(), $admin, 'Mijoz shikoyati')->changed);
    }

    /**
     * Review M1: the monthly cap refused the 11th order at its delivery on 31 October (paid before delivery). A refused
     * order earns nothing; the referral stays open for a LATER order. Neither a clawback that frees an October slot nor
     * the new month lets the sweep grant it afterwards; the customer's next order earns it.
     */
    public function test_an_order_the_cap_refused_at_delivery_is_never_granted_by_the_sweep(): void
    {
        $this->travelTo($this->tashkent('2026-10-31 20:00'));
        $october = $this->fillTheCap();
        $refused = $this->deliveredOrder('platform_click');
        $this->assertNotContains($refused->id, $this->rewardedOrders(), 'the cap refused it at delivery');
        $this->assertSame(50000.0, $this->referrerBalance());

        // A clawback frees an October slot
        $this->travelTo($this->tashkent('2026-10-31 20:10'));
        $this->refundInFull($october[0]);
        $this->assertSame(45000.0, $this->referrerBalance());
        $this->travelTo($this->tashkent('2026-10-31 20:40'));
        $this->sweep();
        $this->assertNotContains($refused->id, $this->rewardedOrders());

        // The first sweep of November
        $this->travelTo($this->tashkent('2026-11-01 00:05'));
        $this->sweep();
        $this->assertNotContains($refused->id, $this->rewardedOrders());
        $this->assertSame(45000.0, $this->referrerBalance());

        // The referral stayed open: a later order of the customer earns it
        $later = $this->deliveredOrder('platform_click');
        $this->assertContains($later->id, $this->rewardedOrders());
        $this->assertSame(50000.0, $this->referrerBalance());
    }

    /**
     * Review M1: a payment that landed after delivery on 31 October at 23:41, its bonus decided (and refused at the
     * cap) by the listener's second transaction. The sweep decides as of the payment: still refused on 1 November.
     */
    public function test_a_late_payment_the_cap_refused_is_not_granted_in_the_next_month(): void
    {
        $this->travelTo($this->tashkent('2026-10-31 23:40'));
        $this->fillTheCap();
        $refused = $this->paidLate();
        $this->assertSame(PaymentStatus::PAID, $refused->payment_status);
        $this->assertNull($refused->settlement_failed_at, 'a cap is not a failure');
        $this->assertNotContains($refused->id, $this->rewardedOrders());

        $this->travelTo($this->tashkent('2026-11-01 00:30'));
        $this->sweep();

        $this->assertNotContains($refused->id, $this->rewardedOrders());
        $this->assertSame(50000.0, $this->referrerBalance());
    }

    /**
     * Review M1: the customer's referral stood when the late payment landed (an earlier order had earned it), so the
     * listener refused it; a full refund of that earlier order reopens the referral afterwards. The refused order is
     * not granted by the sweep: the referral is open for a LATER order.
     */
    public function test_a_late_payment_refused_because_the_referral_stood_is_not_granted_after_it_reopens(): void
    {
        $earned = $this->deliveredOrder('platform_click');
        $this->assertSame([$earned->id], $this->rewardedOrders());
        $this->travel(1)->minutes();
        $refused = $this->paidLate();
        $this->assertSame([$earned->id], $this->rewardedOrders());

        $this->travel(1)->minutes();
        $this->refundInFull($earned);
        $this->assertSame(0.0, $this->referrerBalance());
        $this->travel(20)->minutes();
        $this->sweep();

        $this->assertNotContains($refused->id, $this->rewardedOrders());
        $this->assertSame(0.0, $this->referrerBalance());
    }

    /**
     * Review L1+L2: the sweep grants only the referral bonus. A grant that fails (here: the bonus row's insert throws)
     * leaves no settlement marker and sends no "settlement not booked" alert (only a log line); the next run grants it.
     */
    public function test_a_failing_grant_leaves_no_marker_and_no_alert_and_is_retried_by_the_next_run(): void
    {
        $order = $this->paidButBonusLost();
        $this->travel(20)->minutes();
        $fail = true;
        \App\Models\StoreTransaction::creating(function (\App\Models\StoreTransaction $row) use (&$fail) {
            $type = $row->type instanceof \BackedEnum ? $row->type->value : $row->type;
            if ($fail && $type === \App\Enums\StoreTransactionType::REFERRAL_BONUS->value) {
                throw new \RuntimeException('SQLSTATE[55P03]: Lock not available');
            }
        });
        $alerts = $this->spy(\App\Services\Notifications\SuperadminAlertService::class);
        Log::spy();

        $this->sweep();

        $this->assertSame([], $this->rewards());
        $this->assertSame(0.0, $this->referrerBalance());
        $order->refresh();
        $this->assertSame([null, null, 0], [$order->settlement_failed_at, $order->settlement_error, (int) $order->settlement_attempts]);
        $alerts->shouldNotHaveReceived('send');
        Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'Missed referral bonus sweep') && str_contains($message, 'Lock not available'))->once();

        $fail = false;
        $this->travel(1)->hours();
        $this->sweep();
        $this->assertSame([['order_id' => $order->id, 'store_id' => $this->store->id, 'amount' => '5000.00']], $this->rewards());
        $this->assertSame(5000.0, $this->referrerBalance());
        $this->assertNull($order->fresh()->settlement_failed_at);
        $alerts->shouldNotHaveReceived('send');
    }

    /**
     * Review L1+L2: an order whose late payment committed without its settlement (paid, nothing credited, no marker:
     * the listener's settlement failed and the process died) is left to the settlement path: the sweep writes no
     * commission, settlement or bonus row.
     */
    public function test_the_sweep_never_books_a_commission_or_a_settlement(): void
    {
        $order = $this->deliveredOrder('platform_click_unpaid');
        $this->travel(1)->minutes();
        DB::transaction(function () use ($order) {
            Order::whereKey($order->id)->lockForUpdate()->firstOrFail()->update(['payment_status' => PaymentStatus::PAID->value]);
            Payment::where('order_id', $order->id)->update(['status' => PaymentStatus::PAID->value, 'paid_at' => now()]);
        });
        $ledger = fn () => [
            \App\Models\StoreTransaction::where('order_id', $order->id)->orderBy('id')->pluck('type')->map(fn ($type) => $type instanceof \BackedEnum ? $type->value : $type)->all(),
            \App\Models\StoreWalletTransaction::where('order_id', $order->id)->count(),
            (string) $this->store->fresh()->debt_balance,
            (string) $this->store->fresh()->wallet_balance,
        ];
        $before = $ledger();
        $this->assertSame(['commission'], $before[0]);
        $this->travel(20)->minutes();

        $this->sweep();

        $this->assertSame($before, $ledger());
        $this->assertSame([], $this->rewards());
        $this->assertNull($order->fresh()->settlement_failed_at);
    }

    /**
     * Review L3: candidates newest first (a run never sits behind older ones that are refused for good), and the shapes
     * that can never earn the bonus are not candidates: an order refunded in full by its parts (a full reversal row),
     * a platform order with a keshbek share (never verified).
     */
    public function test_candidates_are_newest_first_and_leave_out_permanently_refused_shapes(): void
    {
        $customers = [];
        $orders = [];
        foreach (range(1, 4) as $i) {
            $customers[$i] = $this->makeCustomer();
            $customers[$i]->forceFill(['referred_by' => $this->referrer->id])->save();
            $orders[$i] = $this->paidButBonusLost('platform_click_unpaid', ['user_id' => $customers[$i]->id]);
        }
        \App\Models\OrderLedgerReversal::forceCreate(['order_id' => $orders[2]->id, 'store_id' => $this->store->id, 'is_full' => true, 'components' => []]);
        Payment::create([
            'order_id' => $orders[3]->id, 'store_id' => $this->store->id, 'merchant_scope' => Payment::merchantScopeOf($orders[3]),
            'provider' => 'keshbek', 'amount' => '1000.00', 'status' => PaymentStatus::PAID->value, 'paid_at' => now()->subMinute(),
        ]);
        // An order paid before its delivery is no candidate at all (its bonus was decided at delivery: here none, the
        // customer named a referrer only afterwards)
        $atDelivery = $this->makeCustomer();
        $this->deliveredOrder('platform_click', ['user_id' => $atDelivery->id]);
        $atDelivery->forceFill(['referred_by' => $this->makeCustomer()->id])->save();
        $this->travel(20)->minutes();

        $this->assertSame([$orders[4]->id, $orders[1]->id], SweepMissedReferralBonusesJob::candidates()->pluck('orders.id')->map(fn ($id) => (int) $id)->all());
    }
}
