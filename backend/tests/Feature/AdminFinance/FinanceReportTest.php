<?php

namespace Tests\Feature\AdminFinance;

use App\Enums\OrderStatus;
use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderLedgerReversal;
use App\Models\OrderStatusHistory;
use App\Models\Store;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/**
 * P4-23: Moliya → Oylik hisobot: RIZQIM's revenue for an Asia/Tashkent month — commission by channel (less partial
 * refunds; a refunded order counts nothing) and subscriptions, per store — and its Excel export (admin.export).
 */
class FinanceReportTest extends TestCase
{
    use FinanceFixtures;
    use RefreshDatabase;

    private function delivered(Store $store, ?string $channel, string $total, string $commission, string $deliveredAt, OrderStatus $status = OrderStatus::DELIVERED): Order
    {
        $order = $this->financeOrder($store, ['status' => $status, 'total_price' => $total, 'commission_amount' => $commission, 'commission_channel' => $channel]);
        $history = new OrderStatusHistory();
        $history->forceFill(['order_id' => $order->id, 'status' => OrderStatus::DELIVERED->value, 'created_at' => $this->at($deliveredAt), 'updated_at' => $this->at($deliveredAt)])->save();

        return $order;
    }

    /**
     * October 2026: store A — courier 100 000 (15 000), pickup at 23:30 on the 31st (2 500), a courier order with
     * 5 000 of its 15 000 commission taken back by a part refund, a refunded order (nothing), one in November (not
     * here), a legacy order without a channel (1 000); store B — the Start invoice for October (149 000, 100 000 paid).
     *
     * @return array{0: Store, 1: Store}
     */
    private function october(): array
    {
        $a = $this->v2Store('Alfa');
        $this->delivered($a, 'rizqim_courier', '100000', '15000', '2026-10-05 12:00');
        $this->delivered($a, 'pickup', '50000', '2500', '2026-10-31 23:30');
        $part = $this->delivered($a, 'rizqim_courier', '100000', '15000', '2026-10-10 12:00');
        OrderLedgerReversal::create(['order_id' => $part->id, 'store_id' => $a->id, 'is_full' => false, 'components' => ['commission_debt' => '5000.00', 'commission_wallet' => '0.00']]);
        $this->delivered($a, 'own_delivery', '80000', '8000', '2026-10-12 12:00', OrderStatus::REFUNDED);
        $this->delivered($a, 'own_delivery', '80000', '8000', '2026-11-01 00:10');
        $this->delivered($a, null, '10000', '1000', '2026-10-20 12:00');

        $this->withoutTrials();
        $b = $this->v2Store('Beta');
        $this->billing()->assignPlan($b, $this->v2('start'), false, null, $this->at('2026-10-01 00:00'));
        $this->billing()->run($this->at('2026-11-01 00:15'));
        $this->ledger()->reduceDebt($b, Money::of('100000'), 'Click top-up');

        return [$a, $b];
    }

    public function test_the_month_counts_commission_by_channel_and_subscriptions_per_store(): void
    {
        [$a, $b] = $this->october();
        $token = $this->confirmedSession($this->makeMember(PlatformRole::FINANCE));

        $report = $this->financeJson('GET', '/finance/reports/monthly?period=2026-10', $token)->assertOk()->json('data');

        $this->assertSame('2026-10', $report['period']);
        $this->assertSame(['orders' => 4, 'gmv' => '260000.00', 'commission' => '28500.00', 'commission_refunded' => '5000.00', 'subscription' => '149000.00', 'total' => '177500.00'], $report['totals']);
        $channels = array_column($report['channels'], null, 'channel');
        $this->assertSame(['orders' => 2, 'commission' => '25000.00', 'commission_refunded' => '5000.00'], array_intersect_key($channels['rizqim_courier'], ['orders' => 1, 'commission' => 1, 'commission_refunded' => 1]));
        $this->assertSame('2500.00', $channels['pickup']['commission']);
        $this->assertSame('0.00', $channels['own_delivery']['commission']);
        $this->assertSame('1000.00', $channels['unknown']['commission']);
        $this->assertSame('Kanal yozilmagan', $channels['unknown']['label']);
        $this->assertSame(['invoices' => 1, 'invoiced' => '149000.00', 'paid' => '100000.00', 'outstanding' => '49000.00'], $report['subscriptions']);

        $this->assertSame([$b->id, $a->id], array_map(fn (array $row) => $row['store']['id'], $report['stores']));
        $this->assertSame(['149000.00', '0.00', '149000.00'], [$report['stores'][0]['subscription'], $report['stores'][0]['commission'], $report['stores'][0]['total']]);
        $this->assertEquals(['rizqim_courier' => '25000.00', 'pickup' => '2500.00', 'unknown' => '1000.00'], $report['stores'][1]['commission_by_channel']);

        $this->financeJson('GET', '/finance/reports/monthly?period=2026-13', $token)->assertStatus(422);
        $this->assertSame('8000.00', $this->financeJson('GET', '/finance/reports/monthly?period=2026-11', $token)->json('data.totals.commission'));
    }

    public function test_the_excel_export_needs_the_export_permission_and_is_audited(): void
    {
        $this->october();
        $viewer = $this->confirmedSession($this->makeMember(PlatformRole::VIEWER));
        $this->financeJson('GET', '/finance/reports/monthly?period=2026-10', $viewer)->assertOk();
        $this->financeJson('GET', '/finance/reports/monthly/export?period=2026-10', $viewer)->assertForbidden();

        $finance = $this->makeMember(PlatformRole::FINANCE);
        $response = $this->financeJson('GET', '/finance/reports/monthly/export?period=2026-10', $this->confirmedSession($finance))
            ->assertOk()->assertDownload('rizqim-daromad-2026-10.xlsx');

        $reader = new Reader();
        $reader->open($response->baseResponse->getFile()->getPathname());
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();

        $this->assertSame(['Umumiy', 'Ovqatxonalar'], array_keys($sheets));
        $summary = array_column(array_filter($sheets['Umumiy'], fn (array $row) => count($row) === 2), 1, 0);
        $this->assertSame([177500, 28500, 149000], [(int) $summary['Jami daromad'], (int) $summary['Komissiya'], (int) $summary['Obuna']]);
        $this->assertSame(['Beta', 149000], [$sheets['Ovqatxonalar'][1][1], (int) end($sheets['Ovqatxonalar'][1])]);

        $entry = AuditLog::where('action', 'admin.finance.report_exported')->sole();
        $this->assertSame($finance->id, (int) $entry->user_id);
        $this->assertSame('2026-10', $entry->payload_json['period']);
    }
}
