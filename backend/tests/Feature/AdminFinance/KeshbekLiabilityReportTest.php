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
        $this->assertSame(['keshbek_orders_without_payment' => 0], $october['unrecorded']);

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
     * A balance change that leaves no record makes the difference non-zero, whatever its sign: a legacy keshbek order
     * without a keshbek payment row (OrderStatusService::refundKeshbek then gives the whole total back and writes
     * nothing) and a balance edited in the database by hand.
     */
    public function test_a_balance_change_without_a_record_shows_as_the_difference(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder(OrderStatus::DELIVERED, ['user_id' => $customer->id, 'payment_method' => 'keshbek', 'payment_owner' => 'PLATFORM', 'payment_status' => PaymentStatus::PAID->value, 'total_price' => 50000]);
        $admin = $this->makeStaff(UserRole::SUPER_ADMIN, null, null, ['store_id' => null]);
        app(OrderStatusService::class)->refund($order->fresh(), $admin, 'Mijoz shikoyati');
        $this->assertSame('50000.00', (string) $customer->fresh()->balance);

        $period = now()->setTimezone('Asia/Tashkent')->format('Y-m');
        $report = $this->report($period);
        $this->assertSame(['count' => 0, 'amount' => '0.00'], $report['returned']);
        $this->assertSame('50000.00', $report['reconciliation']['live']);
        $this->assertSame('0.00', $report['reconciliation']['expected_now']);
        $this->assertSame('50000.00', $report['reconciliation']['difference']);
        $this->assertSame(['keshbek_orders_without_payment' => 1], $report['unrecorded']);

        DB::table('users')->where('id', $this->customer->id)->update(['balance' => '-1234.56']);
        $this->assertSame('48765.44', $this->report($period)['reconciliation']['difference']);
        // The service reads the same
        $this->assertSame('48765.44', app(KeshbekLiabilityReport::class)->build($period)['reconciliation']['difference']->toString());
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

        $entry = AuditLog::where('action', 'admin.finance.keshbek_report_exported')->sole();
        $this->assertSame($finance->id, (int) $entry->user_id);
        $this->assertSame('2026-10', $entry->payload_json['period']);
    }
}
