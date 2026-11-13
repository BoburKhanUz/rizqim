<?php

namespace Tests\Feature\PosVouchers;

use App\Domain\Pos\Vouchers\VoucherLedgerService;
use App\Models\Voucher;
use App\Models\VoucherEntry;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Review fixes of P6 wave 4 on certificates: C4 — the report's liability "as of" the business day (02:00 is still
 * the day before); C6 — a refund onto an expired certificate extends it to the refund's business day + 30 days, onto a
 * blocked one it is recorded and the certificate stays blocked.
 */
class PosVoucherReviewFixesTest extends TestCase
{
    use PosVouchersFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpVouchers();
    }

    private function at(string $local): void
    {
        $this->travelTo(Carbon::parse($local, 'Asia/Tashkent'));
        $this->cashierToken = $this->pinSession($this->till, $this->cashier);
    }

    public function test_the_report_counts_a_certificate_live_through_its_last_business_day(): void
    {
        $this->at('2026-11-20 10:00');
        $this->openShift();
        $this->sell('50000', 'cash', ['valid_until' => '2026-11-20'])->assertCreated();

        // 02:00 on the 21st is still the 20th's business day: the certificate still pays, so it is still owed
        $this->at('2026-11-21 02:00');
        $report = $this->owner($this->owner, 'GET', '/vouchers/report')->assertOk()->json('data');
        $this->assertSame('2026-11-20', $report['as_of']);
        $this->assertSame(['count' => 1, 'amount' => '50000.00'], $report['outstanding']);
        $this->assertSame(['count' => 0, 'amount' => '0.00'], $report['expired']);
        $branchReport = $this->owner($this->owner, 'GET', "/vouchers/report?branch_id={$this->branch->id}")->assertOk()->json('data');
        $this->assertSame(['count' => 1, 'amount' => '50000.00'], $branchReport['outstanding']);

        $this->at('2026-11-21 05:00');
        $report = $this->owner($this->owner, 'GET', '/vouchers/report')->assertOk()->json('data');
        $this->assertSame('2026-11-21', $report['as_of']);
        $this->assertSame(['count' => 0, 'amount' => '0.00'], $report['outstanding']);
        $this->assertSame(['count' => 1, 'amount' => '50000.00'], $report['expired']);
    }

    public function test_a_refund_onto_an_expired_certificate_extends_it_and_onto_a_blocked_one_keeps_it_blocked(): void
    {
        $this->at('2026-11-20 10:00');
        $shift = $this->openShift();
        $expiring = $this->sell('50000', 'cash', ['valid_until' => '2026-11-20'])->assertCreated()->json('data');
        $blocked = $this->sell('40000', 'cash')->assertCreated()->json('data');
        $bill = $this->billOf($this->menuOrder('30000'));
        $this->pay($bill, [
            ['tender' => 'voucher', 'amount' => '20000', 'voucher_code' => $expiring['code']],
            ['tender' => 'voucher', 'amount' => '10000', 'voucher_code' => $blocked['code']],
        ])->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $this->owner($this->owner, 'POST', "/vouchers/{$blocked['uuid']}/block", ['reason' => "Yo'qolgan"])->assertOk();

        // Five days later the guest returns the dishes: the money goes back onto the certificates
        $this->at('2026-11-25 10:00');
        $ledger = app(VoucherLedgerService::class);
        $ledger->reverse($expiring['uuid'], Money::of('20000'), $bill, (string) Str::uuid(), $this->manager->id, null, $shift->id);
        $ledger->reverse($blocked['uuid'], Money::of('10000'), $bill, (string) Str::uuid(), $this->manager->id, null, $shift->id);

        $voucher = Voucher::where('uuid', $expiring['uuid'])->sole();
        $this->assertSame('50000.00', $voucher->balance);
        $this->assertSame('2026-12-25', $voucher->valid_until->toDateString());
        $this->assertSame('active', $voucher->status->value);
        $entry = VoucherEntry::where('voucher_id', $voucher->id)->where('type', 'refund')->sole();
        $this->assertSame("Muddati o'tgan edi: 25.12.2026 gacha uzaytirildi.", $entry->note);
        $this->pos('GET', "/vouchers/{$expiring['code']}")->assertOk()->assertJsonPath('data.voucher.status', 'active');

        $voucher = Voucher::where('uuid', $blocked['uuid'])->sole();
        $this->assertSame('40000.00', $voucher->balance);
        $this->assertSame('blocked', $voucher->status->value);
        $this->assertSame("Sertifikat bloklangan: summa unda saqlandi, menejer hal qiladi.", VoucherEntry::where('voucher_id', $voucher->id)->where('type', 'refund')->sole()->note);
    }
}
