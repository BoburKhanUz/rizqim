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
 * replay a payment it got an answer for. SweepMissedReferralBonusesJob (hourly) books such orders through the
 * existing path (SettlementRecoveryService::bookAfterPayment), so every rule of the grant applies under its locks:
 * exactly once, the monthly cap, the commission that must cover the bonus, a refunded order never.
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
}
