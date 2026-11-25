<?php

namespace Tests\Feature\Referral;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreTransactionType;
use App\Enums\UserRole;
use App\Jobs\RefundPaylovPayment;
use App\Jobs\SendTelegramRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StoreTransaction;
use App\Models\User;
use App\Services\Admin\Orders\RefundRequestService;
use App\Services\Admin\Orders\StoreRefundReviewDecision;
use App\Services\Finance\LedgerService;
use App\Services\Finance\Reports\MonthlyRevenueReport;
use App\Services\Order\OrderStatusService;
use App\Services\Payment\StoreRefundReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\AdminOrders\AdminOrdersFixtures;
use Tests\TestCase;

/**
 * Review finding 1: the platform pays the referral bonus, so a bonus that survived a full refund could be farmed in a
 * loop (order → delivered → bonus → refund). When the order that earned the bonus is fully refunded — whichever way:
 * a refund request for the whole order, a confirmed cabinet refund, a refund the provider confirmed, a single admin's
 * refund of a cash or platform-collected order, or approved parts that add up to everything paid — the 5 000 goes back
 * from the referrer exactly once, in the reversal's transaction, as a compensating referral_bonus row (−5 000, no effect
 * on the store's balances). The referral is open again: a later genuine qualifying order earns it, once.
 *
 * Numbers: 50 000 so'm orders, commission 5 000 (10 %), bonus 5 000.
 */
class ReferralClawbackTest extends TestCase
{
    use RefreshDatabase;
    use AdminOrdersFixtures;
    use ReferralOrders;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([SendTelegramRequest::class, RefundPaylovPayment::class]);
        $this->setUpLifecycleStore();
        $this->store->forceFill(['credit_limit' => -1000000])->save();
        $this->referrer = $this->makeCustomer();
        $this->customer->forceFill(['referred_by' => $this->referrer->id])->save();
    }

    private function admin(): User
    {
        return $this->makeStaff(UserRole::SUPER_ADMIN, null, null, ['store_id' => null]);
    }

    private function refund(Order $order): void
    {
        $this->assertTrue(app(OrderStatusService::class)->refund($order->fresh(), $this->admin(), 'Mijoz shikoyati')->changed);
    }

    /** @return array{referral_count: int, referral_bonuses: string} */
    private function report(): array
    {
        $totals = app(MonthlyRevenueReport::class)->build(now()->setTimezone('Asia/Tashkent')->format('Y-m'))['totals'];

        return ['referral_count' => $totals['referral_count'], 'referral_bonuses' => $totals['referral_bonuses']->toString()];
    }

    public function test_a_full_refund_takes_the_bonus_back_once_and_a_later_order_earns_it_again(): void
    {
        $order = $this->deliveredOrder('platform_click');
        $this->assertSame(5000.0, $this->referrerBalance());

        $this->refund($order);

        // Back to 0: one +5 000 and one −5 000 row on the order, the store's balances untouched by either
        $this->assertSame(0.0, $this->referrerBalance());
        $this->assertSame([
            ['order_id' => $order->id, 'store_id' => $this->store->id, 'amount' => '5000.00'],
            ['order_id' => $order->id, 'store_id' => $this->store->id, 'amount' => '-5000.00'],
        ], $this->rewards());
        $clawback = StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::REFERRAL_BONUS->value)->where('amount', '<', 0)->sole();
        $this->assertSame((string) $clawback->balance_before, (string) $clawback->balance_after);
        $this->assertStringContainsString("referred user #{$this->customer->id}", (string) $clawback->notes);
        $this->assertStringContainsString("#{$order->short_code}", (string) $clawback->notes);
        // Commission and settlement reversed as before (P4-23 netting): nothing of the bonus on the store
        $this->assertSame(['0.00', '0.00'], [(string) $this->store->fresh()->debt_balance, (string) $this->store->fresh()->wallet_balance]);
        // The monthly report nets it out in the month it happens
        $this->assertSame(['referral_count' => 0, 'referral_bonuses' => '0.00'], $this->report());

        // Repeated refund processing changes nothing
        $this->assertFalse(app(OrderStatusService::class)->refund($order->fresh(), $this->admin(), 'Yana')->changed);
        app(LedgerService::class)->reverseDeliveredOrder($order->fresh());
        $this->assertFalse(app(LedgerService::class)->clawBackReferralReward($order->fresh()));
        // ...and the refunded order never earns it again
        $this->assertFalse(app(LedgerService::class)->grantReferralReward($order->fresh()));
        $this->assertCount(2, $this->rewards());
        $this->assertSame(0.0, $this->referrerBalance());

        // The referral is open again: the next genuine qualifying order earns it, once
        $next = $this->deliveredOrder('platform_payme');
        $this->assertSame(5000.0, $this->referrerBalance());
        $this->assertSame(['order_id' => $next->id, 'store_id' => $this->store->id, 'amount' => '5000.00'], $this->rewards()[2]);
        $this->deliveredOrder('platform_click');
        $this->assertCount(3, $this->rewards());
        $this->assertSame(5000.0, $this->referrerBalance());
        $this->assertSame(['referral_count' => 1, 'referral_bonuses' => '5000.00'], $this->report());
    }

    public function test_the_refund_loop_never_leaves_the_referrer_above_zero(): void
    {
        for ($round = 1; $round <= 3; $round++) {
            $order = $this->deliveredOrder('platform_click');
            $this->assertSame(5000.0, $this->referrerBalance(), "round {$round}");
            $this->refund($order);
            $this->assertSame(0.0, $this->referrerBalance(), "round {$round}");
        }

        $this->assertSame('0.00', (string) StoreTransaction::where('type', StoreTransactionType::REFERRAL_BONUS->value)->sum('amount'));
        $this->assertSame(['referral_count' => 0, 'referral_bonuses' => '0.00'], $this->report());
    }

    public function test_the_store_owned_paylov_loop_through_two_person_refund_requests_never_pays_out(): void
    {
        // The reviewers' loop: a store with its own Paylov account (verified by Paylov's read-back), its refunds approved
        $this->store->forceFill(['payment_account_type' => 'own_account'])->save();
        $requests = app(RefundRequestService::class);
        [$asker, $approver] = [$this->makeMember(), $this->makeMember()];

        for ($round = 1; $round <= 2; $round++) {
            $order = $this->deliveredOrder('store_paylov');
            $this->assertSame(5000.0, $this->referrerBalance(), "round {$round}");
            $request = $requests->request($order, $asker, '50000', 'Taom kelmadi');
            $requests->approve($request, $approver, 'Tasdiq');
            $this->assertSame(OrderStatus::REFUNDED, $order->fresh()->status);
            $this->assertSame(0.0, $this->referrerBalance(), "round {$round}");
        }
        $this->assertSame('0.00', (string) StoreTransaction::where('type', StoreTransactionType::REFERRAL_BONUS->value)->sum('amount'));
    }

    public function test_a_confirmed_cabinet_refund_takes_it_back(): void
    {
        $this->store->forceFill(['payment_account_type' => 'own_account'])->save();
        $order = $this->deliveredOrder('store_paylov');
        $this->assertSame(5000.0, $this->referrerBalance());
        DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            $payment = Payment::where('order_id', $order->id)->lockForUpdate()->first();
            $payment->update(['status' => PaymentStatus::REFUNDED, 'cancelled_at' => now()]);
            app(StoreRefundReview::class)->hold($locked, $payment, 'paylov', null);
        });

        $decisions = app(StoreRefundReviewDecision::class);
        $decisions->approve($order->fresh(), $this->makeMember(), 'Taklif');
        $this->assertSame(5000.0, $this->referrerBalance(), 'a proposal books nothing');
        $decisions->approve($order->fresh(), $this->makeMember(), 'Tasdiq');

        $this->assertSame(OrderStatus::REFUNDED, $order->fresh()->status);
        $this->assertSame(0.0, $this->referrerBalance());
        $this->assertCount(2, $this->rewards());
    }

    public function test_a_refund_the_provider_confirmed_takes_it_back(): void
    {
        $order = $this->deliveredOrder('platform_payme');

        // As Payme's CancelTransaction of a platform-collected delivered order does it
        app(OrderStatusService::class)->refund($order, null, "Payme to'lovni bekor qildi.", true);

        $this->assertSame(0.0, $this->referrerBalance());
        $this->assertCount(2, $this->rewards());
    }

    public function test_a_partial_refund_keeps_the_bonus_but_parts_reaching_everything_take_it_back(): void
    {
        $requests = app(RefundRequestService::class);
        [$asker, $approver] = [$this->makeMember(), $this->makeMember()];
        $order = $this->deliveredOrder('platform_click');

        $requests->approve($requests->request($order, $asker, '20000', 'Bitta taom yetmadi'), $approver, 'Tasdiq');
        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertSame(5000.0, $this->referrerBalance());
        $this->assertCount(1, $this->rewards());

        // The other 30 000: everything paid is given back, the bonus with it (once)
        $requests->approve($requests->request($order->fresh(), $asker, '30000', 'Qolgani'), $approver, 'Tasdiq');
        $this->assertSame(0.0, $this->referrerBalance());
        $this->assertCount(2, $this->rewards());
        $this->assertFalse(app(LedgerService::class)->grantReferralReward($order->fresh()));
        $this->assertSame(0.0, $this->referrerBalance());
    }

    public function test_the_referrer_may_go_below_zero_and_the_next_bonus_pays_the_debt(): void
    {
        $order = $this->deliveredOrder('platform_click');
        // The referrer spent the 5 000 keshbek before the refund
        $this->referrer->forceFill(['balance' => 0])->save();

        $this->refund($order);
        $this->assertSame(-5000.0, $this->referrerBalance());

        // A keshbek checkout needs the whole total on the balance: a negative balance pays for nothing
        $this->deliveredOrder('platform_click');
        $this->assertSame(0.0, $this->referrerBalance());
    }

    public function test_a_legacy_store_charged_bonus_is_not_taken_back_and_keeps_the_referral_closed(): void
    {
        // The store paid it (a commission row with the referral note, before owner decision 3's addition): RIZQIM does
        // not take back money it never paid
        $legacy = $this->deliveredOrder('cash');
        StoreTransaction::create([
            'store_id' => $this->store->id, 'type' => StoreTransactionType::COMMISSION->value, 'amount' => '5000.00',
            'balance_before' => '5000.00', 'balance_after' => '10000.00', 'reference_id' => $legacy->uuid, 'order_id' => $legacy->id,
            'notes' => "Referral cashback reward cost for referred user #{$this->customer->id}",
        ]);
        $this->referrer->forceFill(['balance' => 5000])->save();

        $this->refund($legacy);

        $this->assertSame(5000.0, $this->referrerBalance());
        $this->deliveredOrder('platform_click');
        $this->assertSame([], $this->rewards());
    }

    public function test_approving_a_refund_request_locks_the_store_before_the_customers_balance(): void
    {
        // RefundRequestService::spread raises the customer's balance (a keshbek share); the grant and the clawback lock
        // store → customer → referrer. Both must take the store first, or two of the customer's orders deadlock
        $order = $this->deliveredOrder('keshbek', ['commission_amount' => 5000]);
        [$asker, $approver] = [$this->makeMember(), $this->makeMember()];
        $requests = app(RefundRequestService::class);
        $request = $requests->request($order, $asker, '20000', 'Bitta taom yetmadi');

        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'from "stores"') && str_contains($sql, 'for update')) {
                $statements[] = 'store';
            } elseif (str_starts_with($sql, 'update "users"') && str_contains($sql, '"balance"')) {
                $statements[] = 'user';
            }
        });
        $requests->approve($request, $approver, 'Tasdiq');

        $this->assertContains('user', $statements);
        $this->assertSame('store', $statements[0], implode(', ', $statements));
    }
}
