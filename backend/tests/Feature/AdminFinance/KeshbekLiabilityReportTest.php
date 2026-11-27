<?php

namespace Tests\Feature\AdminFinance;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PlatformRole;
use App\Enums\StoreTransactionType;
use App\Enums\UserRole;
use App\Jobs\RefundPaylovPayment;
use App\Jobs\SendTelegramRequest;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\StoreTransaction;
use App\Models\User;
use App\Services\Finance\LedgerService;
use App\Services\Finance\Reports\KeshbekLiabilityReport;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Feature\AdminOrders\AdminOrdersFixtures;
use Tests\Feature\Referral\ReferralOrders;
use Tests\TestCase;

/**
 * Moliya → Oylik hisobot → Keshbek: the keshbek (users.balance) movements of an Asia/Tashkent month read back from
 * where they are booked — platform referral bonuses and their clawbacks, legacy store-paid bonuses (apart), keshbek
 * payments, give-backs of refunds and cancellations — the customers' balances now, and the reconciliation of the
 * movements with SUM(users.balance) (0 when every balance change left a record).
 */
class KeshbekLiabilityReportTest extends TestCase
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
    }

    private function tashkent(string $at): Carbon
    {
        return Carbon::parse($at, 'Asia/Tashkent')->setTimezone((string) config('app.timezone'));
    }

    /** @return array<string, mixed> */
    private function report(string $period): array
    {
        $token = $this->confirmedSession($this->makeMember(PlatformRole::FINANCE));

        return $this->adminJson('GET', "/finance/reports/keshbek?period={$period}", $token)->assertOk()->json('data');
    }

    /** A legacy, store-paid referral bonus as the code before owner decision 3's addition booked it. */
    private function legacyBonus(User $referrer, string $at, int $referredUserId): void
    {
        StoreTransaction::forceCreate([
            'store_id' => $this->store->id, 'type' => StoreTransactionType::COMMISSION->value, 'amount' => '5000.00',
            'balance_before' => '0.00', 'balance_after' => '5000.00', 'notes' => LedgerService::referralNote($referredUserId),
            'created_at' => $this->tashkent($at), 'updated_at' => $this->tashkent($at),
        ]);
        DB::table('users')->where('id', $referrer->id)->increment('balance', 5000);
    }

    /**
     * October 2026 through the real paths: 3 referral bonuses to K (+15 000), one taken back by a full refund
     * (−5 000), K's 50 000 keshbek checkout, 20 000 of it given back by an approved refund request. K's opening
     * balance (50 000) is ten legacy store-paid bonuses of August.
     */
    public function test_a_month_of_grants_a_clawback_a_keshbek_order_and_a_refund_reconciles_to_zero(): void
    {
        $k = $this->makeCustomer();
        $this->referrer = $k;
        for ($i = 1; $i <= 10; $i++) {
            $this->legacyBonus($k, '2026-08-1' . ($i % 10) . ' 12:00', 900000 + $i);
        }

        $this->travelTo($this->tashkent('2026-10-03 12:00'));
        $earned = [];
        for ($i = 0; $i < 3; $i++) {
            $friend = $this->makeCustomer(0, ['referred_by' => $k->id]);
            $friend->forceFill(['referred_by' => $k->id])->save();
            $earned[] = $this->deliveredOrder('platform_click', ['user_id' => $friend->id]);
        }
        $this->assertSame('65000.00', (string) $k->fresh()->balance);

        $this->travelTo($this->tashkent('2026-10-04 12:00'));
        $admin = $this->makeStaff(UserRole::SUPER_ADMIN, null, null, ['store_id' => null]);
        $this->assertTrue(app(OrderStatusService::class)->refund($earned[0]->fresh(), $admin, 'Mijoz shikoyati')->changed);
        $this->assertSame('60000.00', (string) $k->fresh()->balance);

        $this->travelTo($this->tashkent('2026-10-15 12:00'));
        $order = $this->checkout($k, 1, 'keshbek');
        $service = app(OrderStatusService::class);
        foreach ([OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::READY, OrderStatus::DELIVERED] as $status) {
            $service->transition($order->fresh(), $status, $this->manager);
        }
        $this->assertSame('10000.00', (string) $k->fresh()->balance);

        $this->travelTo($this->tashkent('2026-10-20 12:00'));
        $requester = $this->confirmedSession($this->makeMember(PlatformRole::OPERATOR));
        $approver = $this->confirmedSession($this->makeMember(PlatformRole::FINANCE));
        $request = $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $requester, ['amount' => '20000', 'reason' => 'Qisman qaytarish'])->assertCreated()->json('data');
        $this->adminJson('POST', "/refund-requests/{$request['id']}/approve", $approver, ['reason' => 'Tasdiqlandi'])->assertOk();
        $this->assertSame('30000.00', (string) $k->fresh()->balance);

        $this->travelTo($this->tashkent('2026-11-10 12:00'));
        $october = $this->report('2026-10');

        $this->assertSame('2026-10', $october['period']);
        $this->assertSame('Asia/Tashkent', $october['timezone']);
        $this->assertSame(['count' => 3, 'amount' => '15000.00'], $october['issued']);
        $this->assertSame(['count' => 1, 'amount' => '5000.00'], $october['clawed_back']);
        $this->assertSame(['count' => 0, 'amount' => '0.00'], $october['legacy_store_paid']);
        $this->assertSame(['count' => 1, 'amount' => '50000.00'], $october['spent']);
        $this->assertSame(['count' => 1, 'amount' => '20000.00'], $october['returned']);
        $this->assertSame([
            'opening' => '50000.00', 'movement' => '-20000.00', 'closing' => '30000.00', 'after_period' => '0.00',
            'expected_now' => '30000.00', 'live' => '30000.00', 'difference' => '0.00',
        ], $october['reconciliation']);
        $this->assertSame(['positive' => '30000.00', 'negative' => '0.00', 'net' => '30000.00', 'holders' => 1, 'debtors' => 0], $october['outstanding']);
        $this->assertSame(['keshbek_orders_without_payment' => 0, 'invalid_payload_values' => 0], $october['unrecorded']);

        // The month before: only the opening; October's movements come after it, and it still reconciles
        $september = $this->report('2026-09');
        $this->assertSame(['count' => 0, 'amount' => '0.00'], $september['issued']);
        $this->assertSame(['opening' => '50000.00', 'movement' => '0.00', 'closing' => '50000.00', 'after_period' => '-20000.00', 'expected_now' => '30000.00', 'live' => '30000.00', 'difference' => '0.00'], $september['reconciliation']);
        // August: the legacy store-paid bonuses, apart from the platform's
        $august = $this->report('2026-08');
        $this->assertSame(['count' => 0, 'amount' => '0.00'], $august['issued']);
        $this->assertSame(['count' => 10, 'amount' => '50000.00'], $august['legacy_store_paid']);
        $this->assertSame('0.00', $august['reconciliation']['opening']);
        $this->assertSame('50000.00', $august['reconciliation']['closing']);
        $this->assertSame('0.00', $august['reconciliation']['difference']);
    }

    /**
     * Month boundaries are Tashkent's (UTC+5): 23:30 on 30 September is September, 00:10 on 1 October is October
     * (19:10 UTC on 30 September), a give-back stamped 00:05 on 1 November (its refunded_at, with an offset) is
     * November. Records as the code writes them, the balances as the code moved them; a debt (−5 000 after a
     * clawback) is counted apart from the positive balances.
     */
    public function test_month_boundaries_are_tashkent_months_and_legacy_rows_are_apart(): void
    {
        $u = $this->makeCustomer();
        $w = $this->makeCustomer();
        $row = fn (string $type, string $amount, string $at, string $notes) => StoreTransaction::forceCreate([
            'store_id' => $this->store->id, 'type' => $type, 'amount' => $amount, 'balance_before' => '0.00', 'balance_after' => '0.00',
            'notes' => $notes, 'created_at' => $this->tashkent($at), 'updated_at' => $this->tashkent($at),
        ]);
        $row('referral_bonus', '5000.00', '2026-09-30 23:30', LedgerService::referralBonusNote(1));
        $row('referral_bonus', '5000.00', '2026-10-01 00:10', LedgerService::referralBonusNote(2));
        $row('referral_bonus', '-5000.00', '2026-10-10 12:00', LedgerService::referralClawbackNote(3, 'AB12'));
        $this->legacyBonus($u, '2026-10-15 12:00', 4);
        $keshbek = function (string $amount, string $at, ?array $payload, string $status = 'paid') use ($u): void {
            $order = $this->makeOrder(OrderStatus::DELIVERED, ['user_id' => $u->id, 'payment_method' => 'keshbek', 'payment_owner' => 'PLATFORM', 'payment_status' => $status, 'total_price' => $amount]);
            $payment = Payment::create(['order_id' => $order->id, 'provider' => 'keshbek', 'amount' => $amount, 'status' => $status, 'payload' => $payload]);
            DB::table('payments')->where('id', $payment->id)->update(['created_at' => $this->tashkent($at), 'updated_at' => $this->tashkent($at)]);
        };
        // Spent on 31 October 23:50; 3 000 of it given back by a request at 00:05 on 1 November
        $keshbek('10000.00', '2026-10-31 23:50', ['refunded_to_balance_parts' => [['refund_request_id' => 1, 'amount' => '3000.00', 'refunded_at' => '2026-11-01T00:05:00+05:00']]]);
        // Spent on 2 October, the whole of it given back by a cancellation at 00:30 on 1 November Tashkent (19:30 UTC)
        $keshbek('2000.00', '2026-10-02 12:00', ['refunded_to_balance' => 2000, 'refunded_at' => '2026-10-31T19:30:00+00:00'], 'refunded');
        // The balances the code moved: U +5 000 +5 000 (legacy) −10 000 +3 000 −2 000 +2 000; W −5 000 (the clawback)
        DB::table('users')->where('id', $u->id)->update(['balance' => '8000.00']);
        DB::table('users')->where('id', $w->id)->update(['balance' => '-5000.00']);
        // U's first 5 000 bonus is September's grant; give W's clawback its matching grant before the period
        $row('referral_bonus', '5000.00', '2026-07-01 12:00', LedgerService::referralBonusNote(3));
        DB::table('users')->where('id', $w->id)->update(['balance' => '0.00']);

        $this->travelTo($this->tashkent('2026-11-20 12:00'));
        $october = $this->report('2026-10');
        $this->assertSame(['count' => 1, 'amount' => '5000.00'], $october['issued']);
        $this->assertSame(['count' => 1, 'amount' => '5000.00'], $october['clawed_back']);
        $this->assertSame(['count' => 1, 'amount' => '5000.00'], $october['legacy_store_paid']);
        $this->assertSame(['count' => 2, 'amount' => '12000.00'], $october['spent']);
        $this->assertSame(['count' => 0, 'amount' => '0.00'], $october['returned']);
        // Opening: July's 5 000 and September's 5 000; October: +5 000 −5 000 +5 000 −12 000; November: +5 000
        $this->assertSame(['opening' => '10000.00', 'movement' => '-7000.00', 'closing' => '3000.00', 'after_period' => '5000.00', 'expected_now' => '8000.00', 'live' => '8000.00', 'difference' => '0.00'], $october['reconciliation']);
        $this->assertSame(['positive' => '8000.00', 'negative' => '0.00', 'net' => '8000.00', 'holders' => 1, 'debtors' => 0], $october['outstanding']);

        $september = $this->report('2026-09');
        $this->assertSame(['count' => 1, 'amount' => '5000.00'], $september['issued']);
        $november = $this->report('2026-11');
        $this->assertSame(['count' => 2, 'amount' => '5000.00'], $november['returned']);
        $this->assertSame(['count' => 0, 'amount' => '0.00'], $november['spent']);
        $this->assertSame('0.00', $november['reconciliation']['difference']);

        // A debt: a second clawback (its bonus already spent) leaves W at −5 000, counted apart from what customers hold
        $row('referral_bonus', '-5000.00', '2026-11-05 12:00', LedgerService::referralClawbackNote(5, 'CD34'));
        DB::table('users')->where('id', $w->id)->update(['balance' => '-5000.00']);
        $november = $this->report('2026-11');
        $this->assertSame(['count' => 1, 'amount' => '5000.00'], $november['clawed_back']);
        $this->assertSame(['positive' => '8000.00', 'negative' => '-5000.00', 'net' => '3000.00', 'holders' => 1, 'debtors' => 1], $november['outstanding']);
        $this->assertSame(['opening' => '3000.00', 'movement' => '0.00', 'closing' => '3000.00', 'after_period' => '0.00', 'expected_now' => '3000.00', 'live' => '3000.00', 'difference' => '0.00'], $november['reconciliation']);
    }

    /**
     * A balance change that leaves no record makes the difference non-zero, whatever its sign. A legacy keshbek order
     * without a keshbek payment row: the code of the time debited the balance and wrote nothing, so while the order
     * stands the difference is −total. Owner decision B: OrderStatusService::refundKeshbek gives the whole total back
     * and writes the missing keshbek payment row with it (refunded, the total, `backfilled_without_debit_record`,
     * `debited_at` = the order's placing): the report counts it as spent at the order's placing (October) and as
     * returned at the refund (November), the difference is 0 again and the order no longer counts as unrecorded. A
     * balance edited in the database by hand stays a difference.
     */
    public function test_a_balance_change_without_a_record_shows_as_the_difference(): void
    {
        $customer = $this->makeCustomer();
        for ($i = 1; $i <= 10; $i++) {
            $this->legacyBonus($customer, '2026-08-1' . ($i % 10) . ' 12:00', 900000 + $i);
        }
        $this->travelTo($this->tashkent('2026-10-15 12:00'));
        $order = $this->makeOrder(OrderStatus::DELIVERED, ['user_id' => $customer->id, 'payment_method' => 'keshbek', 'payment_owner' => 'PLATFORM', 'payment_status' => PaymentStatus::PAID->value, 'total_price' => 50000]);
        DB::table('users')->where('id', $customer->id)->decrement('balance', 50000);

        $this->travelTo($this->tashkent('2026-11-10 12:00'));
        $report = $this->report('2026-10');
        $this->assertSame(['count' => 0, 'amount' => '0.00'], $report['spent']);
        $this->assertSame('0.00', $report['reconciliation']['live']);
        $this->assertSame('50000.00', $report['reconciliation']['expected_now']);
        $this->assertSame('-50000.00', $report['reconciliation']['difference']);
        $this->assertSame(1, $report['unrecorded']['keshbek_orders_without_payment']);

        $admin = $this->makeStaff(UserRole::SUPER_ADMIN, null, null, ['store_id' => null]);
        app(OrderStatusService::class)->refund($order->fresh(), $admin, 'Mijoz shikoyati');
        $this->assertSame('50000.00', (string) $customer->fresh()->balance);

        $payment = Payment::where('order_id', $order->id)->sole();
        $this->assertSame(['keshbek', PaymentStatus::REFUNDED, '50000.00'], [$payment->provider, $payment->status, (string) $payment->amount]);
        $this->assertSame(50000, (int) $payment->payload['refunded_to_balance']);
        $this->assertTrue($payment->payload['backfilled_without_debit_record']);
        $this->assertSame(Carbon::parse($payment->payload['refunded_at'])->getTimestamp(), now()->getTimestamp());
        $this->assertMatchesRegularExpression('/[+-]\d\d:\d\d$/', $payment->payload['refunded_at']);
        $this->assertSame($order->created_at->getTimestamp(), Carbon::parse($payment->payload['debited_at'])->getTimestamp());

        // October: the debit, at the order's placing; November: the give-back, at the refund
        $october = $this->report('2026-10');
        $this->assertSame(['count' => 1, 'amount' => '50000.00'], $october['spent']);
        $this->assertSame(['count' => 0, 'amount' => '0.00'], $october['returned']);
        $this->assertSame(['opening' => '50000.00', 'movement' => '-50000.00', 'closing' => '0.00', 'after_period' => '50000.00', 'expected_now' => '50000.00', 'live' => '50000.00', 'difference' => '0.00'], $october['reconciliation']);
        $this->assertSame(['keshbek_orders_without_payment' => 0, 'invalid_payload_values' => 0], $october['unrecorded']);
        $november = $this->report('2026-11');
        $this->assertSame(['count' => 0, 'amount' => '0.00'], $november['spent']);
        $this->assertSame(['count' => 1, 'amount' => '50000.00'], $november['returned']);
        $this->assertSame('0.00', $november['reconciliation']['difference']);

        // The customer card: spent at the placing, given back at the refund, adding up to the balance
        $history = app(\App\Services\Admin\Customers\KeshbekHistory::class)->for($customer->fresh());
        $this->assertSame([['refunded', '50000.00'], ['spent', '-50000.00']], array_map(fn (array $entry) => [$entry['kind'], $entry['amount']], $history['entries']));
        $this->assertSame($order->created_at->getTimestamp(), Carbon::parse($history['entries'][1]['at'])->getTimestamp());

        // A second refund attempt finds the order refunded: nothing more is given back or written
        app(OrderStatusService::class)->refund($order->fresh(), $admin, 'Yana');
        $this->assertSame('50000.00', (string) $customer->fresh()->balance);
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());

        DB::table('users')->where('id', $this->customer->id)->update(['balance' => '-1234.56']);
        $this->assertSame('-1234.56', $this->report('2026-11')['reconciliation']['difference']);
        // The service reads the same
        $this->assertSame('-1234.56', app(KeshbekLiabilityReport::class)->build('2026-11')['reconciliation']['difference']->toString());
    }

    /** K's 50 000 keshbek checkout of 15 October, delivered; K holds 50 000 of legacy bonuses of August before it. */
    private function deliveredKeshbekOrder(User $k): \App\Models\Order
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->legacyBonus($k, '2026-08-1' . ($i % 10) . ' 12:00', 800000 + $i);
        }
        $this->travelTo($this->tashkent('2026-10-15 12:00'));
        $order = $this->checkout($k, 1, 'keshbek');
        $service = app(OrderStatusService::class);
        foreach ([OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::READY, OrderStatus::DELIVERED] as $status) {
            $service->transition($order->fresh(), $status, $this->manager);
        }
        $this->assertSame('0.00', (string) $k->fresh()->balance);

        return $order;
    }

    /** @return array<string, mixed> the refund request created and approved */
    private function approvedRequest(\App\Models\Order $order, string $amount, ?string &$approverToken = null): array
    {
        $requester = $this->confirmedSession($this->makeMember(PlatformRole::OPERATOR));
        $approverToken = $this->confirmedSession($this->makeMember(PlatformRole::FINANCE));
        $request = $this->adminJson('POST', "/orders/{$order->uuid}/refund-requests", $requester, ['amount' => $amount, 'reason' => 'Qisman qaytarish'])->assertCreated()->json('data');
        $this->adminJson('POST', "/refund-requests/{$request['id']}/approve", $approverToken, ['reason' => 'Tasdiqlandi'])->assertOk();

        return $request;
    }

    /**
     * Regression: 20 000 given back by an approved request, then the whole order refunded (refundKeshbek gives the
     * other 30 000 back and writes refunded_to_balance 30 000 with refunded_to_balance_by_requests 20 000): returned
     * is 50 000 in two give-backs, never the 20 000 twice. The same with the rest returned by a second request (whose
     * last part also writes the whole-amount marker refunded_to_balance 50 000): 50 000, not 100 000.
     */
    public function test_a_partial_then_a_full_refund_counts_each_give_back_once(): void
    {
        $k = $this->makeCustomer();
        $order = $this->deliveredKeshbekOrder($k);
        $this->travelTo($this->tashkent('2026-10-20 12:00'));
        $this->approvedRequest($order, '20000');
        $this->assertSame('20000.00', (string) $k->fresh()->balance);
        $this->travelTo($this->tashkent('2026-10-21 12:00'));
        $admin = $this->makeStaff(UserRole::SUPER_ADMIN, null, null, ['store_id' => null]);
        $this->assertTrue(app(OrderStatusService::class)->refund($order->fresh(), $admin, 'Mijoz shikoyati')->changed);
        $this->assertSame('50000.00', (string) $k->fresh()->balance);

        $this->travelTo($this->tashkent('2026-11-02 12:00'));
        $october = $this->report('2026-10');
        $this->assertSame(['count' => 1, 'amount' => '50000.00'], $october['spent']);
        $this->assertSame(['count' => 2, 'amount' => '50000.00'], $october['returned']);
        $this->assertSame('50000.00', $october['reconciliation']['live']);
        $this->assertSame('0.00', $october['reconciliation']['difference']);

        // The rest by a second request instead
        $other = $this->makeCustomer();
        $second = $this->deliveredKeshbekOrder($other);
        $this->travelTo($this->tashkent('2026-10-22 12:00'));
        $this->approvedRequest($second, '20000');
        $this->approvedRequest($second, '30000');
        $this->assertSame('50000.00', (string) $other->fresh()->balance);
        $this->assertSame(50000.0, (float) Payment::where('order_id', $second->id)->where('provider', 'keshbek')->sole()->payload['refunded_to_balance']);

        $this->travelTo($this->tashkent('2026-11-02 12:00'));
        $october = $this->report('2026-10');
        $this->assertSame(['count' => 2, 'amount' => '100000.00'], $october['spent']);
        $this->assertSame(['count' => 4, 'amount' => '100000.00'], $october['returned']);
        $this->assertSame('0.00', $october['reconciliation']['difference']);
    }

    /**
     * Review L4: the backfill is for a legacy order with NO keshbek payment row at all. An order whose keshbek payment
     * row exists but is no longer paid (here: refunded, its 50 000 already back on the balance, the order still paid)
     * gets nothing back a second time and no second row on a full refund.
     */
    public function test_an_order_with_a_refunded_keshbek_payment_is_not_backfilled_or_credited_twice(): void
    {
        $k = $this->makeCustomer();
        $order = $this->deliveredKeshbekOrder($k);
        $payment = Payment::where('order_id', $order->id)->where('provider', 'keshbek')->sole();
        // Given back already (as a cancellation's give-back writes it), the order left paid
        $payment->update(['status' => PaymentStatus::REFUNDED, 'cancelled_at' => now(), 'payload' => array_merge($payment->payload ?? [], ['refunded_to_balance' => 50000.0, 'refunded_at' => now()->toIso8601String()])]);
        DB::table('users')->where('id', $k->id)->increment('balance', 50000);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);

        $this->travelTo($this->tashkent('2026-10-21 12:00'));
        $admin = $this->makeStaff(UserRole::SUPER_ADMIN, null, null, ['store_id' => null]);
        $this->assertTrue(app(OrderStatusService::class)->refund($order->fresh(), $admin, 'Mijoz shikoyati')->changed);

        $this->assertSame('50000.00', (string) $k->fresh()->balance, 'credited once');
        $this->assertSame([$payment->id], Payment::where('order_id', $order->id)->pluck('id')->all(), 'no backfilled row');
        $this->assertSame(PaymentStatus::REFUNDED, $order->fresh()->payment_status);
        $this->travelTo($this->tashkent('2026-11-02 12:00'));
        $this->assertSame('0.00', $this->report('2026-10')['reconciliation']['difference']);
    }

    /** Regression: an approval sent again (replayed) credits nothing a second time, and the report counts it once. */
    public function test_a_replayed_approval_gives_nothing_back_twice(): void
    {
        $k = $this->makeCustomer();
        $order = $this->deliveredKeshbekOrder($k);
        $this->travelTo($this->tashkent('2026-10-20 12:00'));
        $approver = null;
        $request = $this->approvedRequest($order, '20000', $approver);
        $this->adminJson('POST', "/refund-requests/{$request['id']}/approve", $approver, ['reason' => 'Tasdiqlandi'])->assertOk();
        $this->assertSame('20000.00', (string) $k->fresh()->balance);

        $this->travelTo($this->tashkent('2026-11-02 12:00'));
        $october = $this->report('2026-10');
        $this->assertSame(['count' => 1, 'amount' => '20000.00'], $october['returned']);
        $this->assertSame('0.00', $october['reconciliation']['difference']);
    }

    /**
     * Review M2: a payload value the report cannot read never fails it (500). Amounts must be strict decimals and
     * timestamps ISO 8601 with an explicit offset or Z (an offset-less time is invalid: in which zone it was written is
     * unknown, review L3). A part whose amount is unreadable is not counted; a give-back whose time is unreadable falls
     * back to the payment's created_at, as a missing one does. Each rejected value is counted in
     * unrecorded.invalid_payload_values, shown beside the difference.
     */
    public function test_malformed_payload_values_are_counted_not_fatal(): void
    {
        $u = $this->makeCustomer();
        $keshbek = function (string $amount, array $payload, string $status = 'paid') use ($u): void {
            $order = $this->makeOrder(OrderStatus::DELIVERED, ['user_id' => $u->id, 'payment_method' => 'keshbek', 'payment_owner' => 'PLATFORM', 'payment_status' => $status, 'total_price' => $amount]);
            $payment = Payment::create(['order_id' => $order->id, 'provider' => 'keshbek', 'amount' => $amount, 'status' => $status, 'payload' => $payload]);
            DB::table('payments')->where('id', $payment->id)->update(['created_at' => $this->tashkent('2026-10-10 12:00'), 'updated_at' => $this->tashkent('2026-10-10 12:00')]);
        };
        $valid = '2026-10-20T12:00:00+05:00';
        $keshbek('10000.00', ['refunded_to_balance_parts' => [
            ['amount' => '', 'refunded_at' => $valid],
            ['amount' => 'garbage', 'refunded_at' => $valid],
            ['amount' => 'NaN', 'refunded_at' => $valid],
            ['amount' => 'abc', 'refunded_at' => $valid],
            ['amount' => '3000.00', 'refunded_at' => '1730000000'],
            // Offset-less: as UTC it would be 05:05 on 1 November in Tashkent; unreadable, it is the payment's October
            ['amount' => '2000', 'refunded_at' => '2026-11-01T00:05:00'],
            ['amount' => '1000', 'refunded_at' => ''],
            ['amount' => '500', 'refunded_at' => 'garbage'],
            ['amount' => '700', 'refunded_at' => '2026-02-30T10:00:00+05:00'],
            ['amount' => '100.50', 'refunded_at' => '2026-10-21T00:00:00Z'],
        ]]);
        $keshbek('2000.00', ['refunded_to_balance' => 'NaN', 'refunded_at' => 'garbage'], 'refunded');
        $keshbek('1000.00', ['refunded_to_balance' => 'abc'], 'refunded');
        $keshbek('1000.00', ['refunded_to_balance' => '', 'refunded_at' => '2026-10-31T19:30:00'], 'refunded');

        $this->travelTo($this->tashkent('2026-11-20 12:00'));
        $october = $this->report('2026-10');
        $this->assertSame(['count' => 4, 'amount' => '14000.00'], $october['spent']);
        $this->assertSame(['count' => 6, 'amount' => '7300.50'], $october['returned']);
        $this->assertSame('-6699.50', $october['reconciliation']['expected_now']);
        $this->assertSame('6699.50', $october['reconciliation']['difference']);
        // 4 amounts and 5 times of the parts; 'NaN' and 'garbage', 'abc', '' and the offset-less time of the others
        $this->assertSame(['keshbek_orders_without_payment' => 0, 'invalid_payload_values' => 14], $october['unrecorded']);
        $this->assertSame(['count' => 0, 'amount' => '0.00'], $this->report('2026-11')['returned']);
    }

    /**
     * Review M3: the store_transactions movements are read in one pass through the partial index of the keshbek rows
     * (referral_bonus and the legacy referral-note commission rows), and the keshbek payments through the
     * provider-leading index: EXPLAIN of every query the report sends, sequential scans off.
     */
    public function test_the_report_reads_through_its_indexes(): void
    {
        $queries = [];
        DB::listen(function (\Illuminate\Database\Events\QueryExecuted $query) use (&$queries) {
            if (preg_match('/^\s*select/i', $query->sql) === 1) {
                $queries[] = [$query->sql, $query->bindings];
            }
        });
        app(KeshbekLiabilityReport::class)->build('2026-10');

        $ledger = array_values(array_filter($queries, fn (array $query) => str_contains($query[0], '"store_transactions"')));
        $payments = array_values(array_filter($queries, fn (array $query) => str_contains($query[0], '"payments"') && !str_contains($query[0], '"orders"')));
        $this->assertCount(1, $ledger, 'one pass over store_transactions');
        $this->assertCount(2, $payments, 'the payments in one pass, their give-back parts in another');
        $plan = fn (array $query) => DB::transaction(function () use ($query) {
            DB::statement('SET LOCAL enable_seqscan = off');

            return implode("\n", array_column(array_map(fn ($row) => (array) $row, DB::select('EXPLAIN ' . $query[0], $query[1])), 'QUERY PLAN'));
        });
        $this->assertStringContainsString('store_transactions_keshbek_movements_index', $plan($ledger[0]));
        // The index predicate's literals are the code's
        $this->assertStringContainsString("type = '" . StoreTransactionType::REFERRAL_BONUS->value . "'", KeshbekLiabilityReport::LEDGER_ROWS);
        $this->assertStringContainsString("type = '" . StoreTransactionType::COMMISSION->value . "' AND notes LIKE '" . LedgerService::REFERRAL_NOTE . "%'", KeshbekLiabilityReport::LEDGER_ROWS);
        foreach ($payments as $query) {
            $this->assertStringContainsString('payments_provider_scope_transaction_unique', $plan($query));
        }
    }

    public function test_the_excel_export_needs_the_export_permission_and_is_audited(): void
    {
        $k = $this->makeCustomer();
        $this->legacyBonus($k, '2026-10-05 12:00', 7);
        $this->travelTo($this->tashkent('2026-11-02 12:00'));

        $viewer = $this->confirmedSession($this->makeMember(PlatformRole::VIEWER));
        $this->adminJson('GET', '/finance/reports/keshbek?period=2026-10', $viewer)->assertOk();
        $this->adminJson('GET', '/finance/reports/keshbek/export?period=2026-10', $viewer)->assertForbidden();
        $this->adminJson('GET', '/finance/reports/keshbek?period=2026-13', $viewer)->assertStatus(422);

        $finance = $this->makeMember(PlatformRole::FINANCE);
        $response = $this->adminJson('GET', '/finance/reports/keshbek/export?period=2026-10', $this->confirmedSession($finance))
            ->assertOk()->assertDownload('rizqim-keshbek-2026-10.xlsx');

        $reader = new Reader();
        $reader->open($response->baseResponse->getFile()->getPathname());
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $this->assertSame('Keshbek', $sheet->getName());
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();
        $values = array_column(array_filter($rows, fn (array $row) => count($row) >= 2), 1, 0);
        $this->assertSame(5000, (int) $values["Eski bonuslar (ovqatxona to'lagan)"]);
        $this->assertSame(0, (int) $values['Berilgan bonuslar (RIZQIM)']);
        $this->assertSame(5000, (int) $values['Yakuniy qoldiq (harakatlardan)']);
        $this->assertSame(0, (int) $values['Farq']);
        $this->assertSame(0, (int) $values["O'qib bo'lmagan to'lov qiymatlari"]);

        $entry = AuditLog::where('action', 'admin.finance.keshbek_report_exported')->sole();
        $this->assertSame($finance->id, (int) $entry->user_id);
        $this->assertSame('2026-10', $entry->payload_json['period']);
    }
}
