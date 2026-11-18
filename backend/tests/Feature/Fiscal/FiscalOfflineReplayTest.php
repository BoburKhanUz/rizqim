<?php

namespace Tests\Feature\Fiscal;

use App\Domain\Pos\Billing\BillFiscalizer;
use App\Models\FiscalReceipt;
use App\Models\Order;
use App\Models\OrderBill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\PosSync\PosSyncFixtures;
use Tests\TestCase;

/**
 * Wave M3: the offline till replays its queue (the answer was lost) in the next month, while the sale receipt is
 * still waiting for the provider; the bill's own retry asks again in the new month. One receipt,
 * in the month it was first written, one key.
 */
class FiscalOfflineReplayTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
    }

    public function test_an_offline_sale_replayed_across_a_month_boundary_has_one_receipt(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $settings = (array) $this->store->fiscal_settings;
        $this->store->forceFill(['fiscal_settings' => ['sandbox' => ['fail_attempts' => 2]] + $settings])->save();
        $sale = $this->walkInSale();

        $this->sync($sale['ops'])->assertOk()->assertJsonPath('data.results.2.status', 'applied');
        $bill = OrderBill::where('order_id', Order::where('external_id', $sale['order'])->value('id'))->sole();
        $receipt = FiscalReceipt::where('subject_type', OrderBill::FISCAL_SUBJECT)->where('subject_id', $bill->id)->sole();
        $this->assertSame('pending', $receipt->status->value);

        // 00:00:01 UTC on the first of the next month (the till was offline again): the same batch again, and the bill asks for its receipt again
        Carbon::setTestNow(Carbon::parse('2026-11-01 00:00:01', 'UTC'));
        $this->sync($sale['ops'])->assertOk()->assertJsonPath('data.results.2.duplicate', true);
        app(BillFiscalizer::class)->issue($bill);
        $this->store->forceFill(['fiscal_settings' => $settings])->save();
        app(BillFiscalizer::class)->issue($bill->fresh());
        app(\App\Domain\Fiscal\FiscalService::class)->attempt((int) $receipt->id, force: true);

        $this->assertSame(1, FiscalReceipt::where('type', 'sale')->count());
        $this->assertSame(1, DB::table('fiscal_receipt_keys')->where('idempotency_key', $receipt->idempotency_key)->count());
        $this->assertSame('fiscal_receipts_2026_10', DB::selectOne('SELECT tableoid::regclass::text AS p FROM fiscal_receipts WHERE id = ?', [$receipt->id])->p);
        $this->assertSame('issued', $receipt->refresh()->status->value);
        $this->assertSame($receipt->uuid, $bill->refresh()->getAttribute('fiscal_receipt_uuid'));
        $this->assertSame('paid', $bill->status->value);
    }
}
