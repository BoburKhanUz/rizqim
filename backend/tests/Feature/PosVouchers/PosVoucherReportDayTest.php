<?php

namespace Tests\Feature\PosVouchers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/**
 * Wave M3: the owner's certificate report, its export and the list's status filter default to the BUSINESS day the
 * entries are booked on (VoucherEntry.business_date, the branch's day start, 05:00 Asia/Tashkent by default), store-wide
 * too: a certificate sold at 00:30 local belongs to the day before, and the report for "today" must show it until
 * 05:00, then start a new day. Pinned on both sides of local midnight (19:00 UTC) and of the day start (00:00 UTC).
 */
class PosVoucherReportDayTest extends TestCase
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

    /**
     * @return array<string, mixed>
     */
    private function report(string $query = ''): array
    {
        return $this->owner($this->owner, 'GET', '/vouchers/report' . $query)->assertOk()->json('data');
    }

    public function test_a_sale_after_local_midnight_is_in_todays_store_wide_report_until_the_day_starts(): void
    {
        $this->at('2026-11-17 23:30');
        $this->openShift();
        $this->sell('100000')->assertCreated();

        // 00:30 local (19:30 UTC): still the 17th's business day, for the store and for the branch
        $this->at('2026-11-18 00:30');
        $this->sell('50000', 'card', ['card_type' => 'humo', 'transaction_id' => 'N1'])->assertCreated();
        foreach (['', "?branch_id={$this->branch->id}"] as $query) {
            $report = $this->report($query);
            $this->assertSame(['2026-11-17', '2026-11-17'], [$report['from'], $report['to']]);
            $this->assertSame(2, $report['sold']['count']);
            $this->assertSame('150000.00', $report['sold']['amount']);
            $this->assertSame('2026-11-17', $report['as_of']);
        }

        // The export's report sheet follows the same day
        $sheet = $this->exportReportSheet();
        $this->assertSame('Sertifikatlar hisoboti: 2026-11-17 — 2026-11-17', $sheet[0][0]);
        $this->assertSame(['Sotildi (avans)', 2, 150000], $sheet[2]);

        // 04:59 still the 17th; 05:00 a new business day with nothing sold yet
        $this->at('2026-11-18 04:59');
        $this->assertSame(2, $this->report()['sold']['count']);
        $this->at('2026-11-18 05:00');
        $report = $this->report();
        $this->assertSame(['2026-11-18', 0], [$report['from'], $report['sold']['count']]);
        $this->assertSame(['count' => 2, 'amount' => '150000.00'], $report['outstanding']);
    }

    public function test_midday_report_is_the_calendar_day(): void
    {
        $this->at('2026-11-17 17:00'); // 12:00 UTC
        $this->openShift();
        $this->sell('100000')->assertCreated();

        $report = $this->report();
        $this->assertSame(['2026-11-17', 1], [$report['from'], $report['sold']['count']]);
    }

    public function test_the_lists_status_filter_follows_the_branchs_business_day(): void
    {
        $this->at('2026-11-17 12:00');
        $this->openShift();
        $this->sell('100000', 'cash', ['valid_until' => '2026-11-17'])->assertCreated();

        // 00:30 local: the till still takes it (its last business day), so the owner's list calls it active
        $this->at('2026-11-18 00:30');
        $this->owner($this->owner, 'GET', '/vouchers?status=active')->assertOk()->assertJsonCount(1, 'data.vouchers');
        $this->owner($this->owner, 'GET', '/vouchers?status=expired')->assertOk()->assertJsonCount(0, 'data.vouchers');

        $this->at('2026-11-18 05:00');
        $this->owner($this->owner, 'GET', '/vouchers?status=active')->assertOk()->assertJsonCount(0, 'data.vouchers');
        $this->owner($this->owner, 'GET', '/vouchers?status=expired')->assertOk()->assertJsonCount(1, 'data.vouchers');
    }

    /**
     * @return list<list<mixed>>
     */
    private function exportReportSheet(): array
    {
        $file = $this->owner($this->owner, 'GET', '/vouchers/export')->assertOk()->baseResponse;
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $file);
        $reader = new Reader();
        $reader->open($file->getFile()->getPathname());
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() === 'Hisobot') {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
            }
        }
        $reader->close();

        return $rows;
    }
}
