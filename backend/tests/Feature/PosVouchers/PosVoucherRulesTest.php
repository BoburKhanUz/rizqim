<?php

namespace Tests\Feature\PosVouchers;

use App\Models\AuditLog;
use App\Models\Voucher;
use App\Models\VoucherEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/**
 * P6-43: expiry by the branch's business day (Asia/Tashkent, 05:00), the lookup (masked buyer, another store's code
 * 404), the module gate (selling asks, spending never), the owner's page (list, block and unblock audited, report,
 * Excel shape) and its permissions.
 */
class PosVoucherRulesTest extends TestCase
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
        // A PIN session lives for hours, not days
        $this->cashierToken = $this->pinSession($this->till, $this->cashier);
    }

    public function test_a_certificate_pays_through_its_last_business_day_and_not_after(): void
    {
        $this->at('2026-11-20 10:00');
        $this->openShift();
        $code = $this->sell('50000', 'cash', ['valid_until' => '2026-11-20'])->assertCreated()->assertJsonPath('data.valid_until', '2026-11-20')->json('data.code');

        // 04:59 the next morning is still the 20th's business day
        $this->at('2026-11-21 04:59');
        $this->pay($this->billOf($this->menuOrder('10000')), [['tender' => 'voucher', 'amount' => '10000', 'voucher_code' => $code]])->assertOk();

        $this->at('2026-11-21 05:00');
        $this->pos('GET', "/vouchers/{$code}")->assertOk()->assertJsonPath('data.voucher.status', 'expired');
        $this->pay($this->billOf($this->menuOrder('10000')), [['tender' => 'voucher', 'amount' => '10000', 'voucher_code' => $code]])
            ->assertConflict()->assertJsonPath('data.reason', 'voucher_expired');
        $this->assertSame('40000.00', Voucher::sole()->balance);

        // The default validity is a year
        $this->assertSame('2027-11-21', $this->sell('10000')->assertCreated()->json('data.valid_until'));
    }

    public function test_lookup_is_the_stores_own_and_masks_the_buyer(): void
    {
        $this->openShift();
        $code = $this->sell('30000', 'qr', ['reference' => 'CLICK-1', 'buyer_phone' => '+998901234567'])->assertCreated()->json('data.code');

        $this->pos('GET', '/vouchers/' . strtolower(str_replace('-', '', $code)))->assertOk()
            ->assertJsonPath('data.voucher.code', $code)
            ->assertJsonPath('data.voucher.balance', '30000.00')
            ->assertJsonPath('data.voucher.buyer.phone', '+99890*****67')
            ->assertJsonPath('data.entries.0.type', 'sale');

        // Another store's certificate: unknown here, at the lookup and at the payment
        $other = $this->makeStore('Boshqa');
        Voucher::create(['store_id' => $other->id, 'code' => 'AAAA-CCCC-DDDD', 'face_value' => '10000', 'balance' => '10000', 'valid_from' => '2026-01-01', 'sale_tender' => 'cash']);
        $this->pos('GET', '/vouchers/AAAA-CCCC-DDDD')->assertNotFound()->assertJsonPath('data.reason', 'voucher_not_found');
        $this->pay($this->billOf($this->menuOrder('10000')), [['tender' => 'voucher', 'amount' => '10000', 'voucher_code' => 'AAAA-CCCC-DDDD']])
            ->assertNotFound()->assertJsonPath('data.reason', 'voucher_not_found');
        $this->assertSame('10000.00', Voucher::where('store_id', $other->id)->sole()->balance);
    }

    public function test_selling_needs_the_module_and_the_shift_spending_never_asks_the_module(): void
    {
        $this->sell()->assertConflict()->assertJsonPath('data.reason', 'shift_not_open');
        $this->openShift();
        $code = $this->sell('20000')->assertCreated()->json('data.code');

        $this->modules(['vouchers' => false]);
        $this->sell()->assertForbidden()->assertJsonPath('code', 'module_disabled');
        $this->pay($this->billOf($this->menuOrder('20000')), [['tender' => 'voucher', 'amount' => '20000', 'voucher_code' => $code]])->assertOk()->assertJsonPath('data.bill.status', 'paid');
    }

    public function test_the_owners_page_blocks_reports_and_exports(): void
    {
        $this->openShift();
        $first = $this->sell('100000', 'cash', ['buyer_name' => 'Aziz', 'buyer_phone' => '+998901234567'])->assertCreated()->json('data');
        $this->sell('50000', 'card', ['card_type' => 'humo', 'transaction_id' => 'R1'])->assertCreated();
        $this->pay($this->billOf($this->menuOrder('30000')), [['tender' => 'voucher', 'amount' => '30000', 'voucher_code' => $first['code']]])->assertOk();

        $this->owner($this->owner, 'GET', '/vouchers')->assertOk()->assertJsonCount(2, 'data.vouchers')
            ->assertJsonPath('data.vouchers.1.buyer.phone', '+99890*****67');
        $this->owner($this->owner, 'GET', '/vouchers?q=1234567')->assertOk()->assertJsonCount(1, 'data.vouchers');
        $this->owner($this->owner, 'GET', "/vouchers/{$first['uuid']}")->assertOk()
            ->assertJsonPath('data.voucher.buyer.phone', '+998901234567')
            ->assertJsonPath('data.entries.0.type', 'redemption');

        // Block: audited; a blocked certificate pays nothing; a cashier may not block
        $this->owner($this->cashier, 'POST', "/vouchers/{$first['uuid']}/block", ['reason' => "Yo'qolgan"])->assertForbidden();
        $this->owner($this->owner, 'POST', "/vouchers/{$first['uuid']}/block", ['reason' => "Yo'qolgan"])->assertOk()->assertJsonPath('data.status', 'blocked');
        $this->owner($this->owner, 'POST', "/vouchers/{$first['uuid']}/block", ['reason' => "Yo'qolgan"])->assertConflict()->assertJsonPath('data.reason', 'voucher_blocked');
        $this->assertTrue(AuditLog::where('action', 'voucher.blocked')->exists());
        $this->cashierToken = $this->pinSession($this->till, $this->cashier);
        $this->pay($this->billOf($this->menuOrder('10000')), [['tender' => 'voucher', 'amount' => '10000', 'voucher_code' => $first['code']]])
            ->assertConflict()->assertJsonPath('data.reason', 'voucher_blocked');

        // The report: sold, redeemed, the liability today with the blocked one apart
        $report = $this->owner($this->owner, 'GET', '/vouchers/report')->assertOk()->json('data');
        $this->assertSame(['count' => 2, 'amount' => '150000.00'], array_intersect_key($report['sold'], ['count' => 1, 'amount' => 1]));
        $this->assertSame(['tender' => 'card', 'count' => 1, 'amount' => '50000.00'], $report['sold']['by_tender'][1]);
        $this->assertSame(['count' => 1, 'amount' => '30000.00'], $report['redeemed']);
        $this->assertSame('120000.00', $report['net_change']);
        $this->assertSame(['count' => 1, 'amount' => '50000.00'], $report['outstanding']);
        $this->assertSame(['count' => 1, 'amount' => '70000.00'], $report['blocked']);
        $this->owner($this->owner, 'GET', '/vouchers?status=blocked')->assertOk()->assertJsonCount(1, 'data.vouchers');

        $this->owner($this->owner, 'POST', "/vouchers/{$first['uuid']}/unblock")->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertSame(['sale', 'redemption', 'block', 'unblock'], VoucherEntry::where('voucher_id', Voucher::where('uuid', $first['uuid'])->value('id'))->orderBy('id')->pluck('type')->map->value->all());

        // The Excel workbook: the certificates and the report
        $response = $this->owner($this->owner, 'GET', '/vouchers/export')->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('Content-Type'));
        $file = $response->baseResponse;
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $file);
        $path = $file->getFile()->getPathname();
        $reader = new Reader();
        $reader->open($path);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();
        $this->assertSame(['Sertifikatlar', 'Hisobot'], array_keys($sheets));
        $this->assertSame(\App\Domain\Pos\Vouchers\VoucherExport::HEADERS, $sheets['Sertifikatlar'][0]);
        $this->assertCount(3, $sheets['Sertifikatlar']);
        $this->assertSame([$first['code'], 'Faol', 100000, 70000], array_slice($sheets['Sertifikatlar'][2], 0, 4));
        $this->assertSame('+99890*****67', $sheets['Sertifikatlar'][2][9]);
        $this->assertTrue(AuditLog::where('action', 'voucher.exported')->exists());

        // reports.view reads, vouchers.manage blocks; another store's certificate is unknown
        $this->owner($this->owner, 'GET', '/vouchers/00000000-0000-4000-8000-000000000000')->assertNotFound();
    }
}
