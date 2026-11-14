<?php

namespace Tests\Feature\PosBanquetPayments;

use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Pos\Banquet\BanquetEventStatus;
use App\Models\AuditLog;
use App\Models\BanquetCancellation;
use App\Models\BanquetEvent;
use App\Models\BanquetPayment;
use App\Models\BanquetPaymentEntry;
use App\Models\FiscalReceipt;
use App\Models\PosShift;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-53: cancelling an event at each band of the default rule table (≥ 30 days 0 %, 14–29 days 30 %, < 14 days 100 %
 * retained of what was paid), the manager's override (reason, audit, owner alert), the refund paths (cash from the
 * drawer of the open shift, card, bank transfer marked to return by transfer), the receipts (REFUND against each
 * advance, SALE of the cancellation service paid by prepayment), the X/Z, and a cancel with nothing paid.
 */
class BanquetCancellationTest extends TestCase
{
    use PosBanquetPaymentsFixtures;
    use RefreshDatabase;

    private PosShift $managerShift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBanquetPayments();
        $this->openShift('100000');
        $this->managerShift = $this->openShift('0', $this->managerTill['device'], $this->manager);
    }

    /** An event on $date with its deposit (3 000 000) paid in cash and 1 000 000 of the interim by card */
    private function paidEvent(string $date): BanquetEvent
    {
        $event = $this->event('10000000', $date);
        [$deposit, $interim] = $this->rows($event)->all();
        $this->payRow($event, $deposit)->assertCreated();
        $this->payRow($event, $interim, ['tender' => 'card', 'amount' => '1000000', 'card_type' => 'uzcard', 'transaction_id' => 'RRN-1'])->assertCreated();

        return $event->refresh();
    }

    private function cancel(BanquetEvent $event, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->pos('POST', "/banquets/{$event->uuid}/cancellation", $data + ['reason' => "Mijoz to'yni ko'chirdi"], $this->managerToken);
    }

    public function test_thirty_days_or_more_refunds_everything_against_the_advance_receipts(): void
    {
        $event = $this->paidEvent('2027-01-20'); // 67 days ahead

        $this->panel($this->manager, 'GET', "/pos/banquets/{$event->uuid}/cancellation")->assertOk()
            ->assertJsonPath('data.days_before', 67)
            ->assertJsonPath('data.rule_percent', '0')
            ->assertJsonPath('data.paid', '4000000.00')
            ->assertJsonPath('data.retained', '0.00')
            ->assertJsonPath('data.refund', '4000000.00')
            ->assertJsonPath('data.cash_refund', '3000000.00')
            ->assertJsonPath('data.rows.0.refund_path', 'cash_drawer')
            ->assertJsonPath('data.rows.1.refund_path', 'card');

        $this->cancel($event)->assertOk()
            ->assertJsonPath('data.event.status', 'cancelled')
            ->assertJsonPath('data.cancellation.refund', '4000000.00')
            ->assertJsonCount(2, 'data.rows');

        $event->refresh();
        $this->assertSame(BanquetEventStatus::CANCELLED, $event->status);
        $this->assertSame(0, BanquetPayment::where('banquet_event_id', $event->id)->where('status', 'due')->count());
        $refunds = FiscalReceipt::where('subject_type', 'banquet_payment_entry')->where('type', FiscalReceiptType::REFUND->value)->orderBy('id')->get();
        $this->assertCount(2, $refunds);
        $advances = FiscalReceipt::where('subject_type', 'banquet_payment_entry')->where('type', FiscalReceiptType::ADVANCE->value)->orderBy('id')->pluck('uuid')->all();
        $this->assertSame($advances, $refunds->map(fn ($r) => $r->originalReceipt?->uuid)->all());
        $this->assertSame(['3000000.00', '1000000.00'], $refunds->map(fn ($r) => Money::of($r->total)->toString())->all());
        $this->assertSame(['refunded', 'refunded'], $this->rows($event)->map(fn ($r) => $r->status->value)->all());

        // The cash refund came from the manager's till drawer; the card one is listed on that shift, not in cash
        $x = $this->pos('GET', "/shifts/{$this->managerShift->uuid}/x-report", [], $this->managerToken)->assertOk()->json('data.report');
        $this->assertSame(['count' => 2, 'amount' => '4000000.00', 'cash' => '3000000.00'], $x['banquets']['refunded']);
        $this->assertSame('3000000.00', $x['cash']['banquet_refunds']);
        $this->assertSame('-3000000.00', $x['cash']['expected']);
        $this->assertStringContainsString('Qaytariladi: 4 000 000', $this->alerts->sent[0]);
    }

    public function test_fourteen_to_twenty_nine_days_retains_thirty_percent_from_the_oldest_payment(): void
    {
        $event = $this->paidEvent('2026-12-04'); // 20 days ahead

        $this->cancel($event)->assertOk()
            ->assertJsonPath('data.cancellation.rule_percent', '30')
            ->assertJsonPath('data.cancellation.retained', '1200000.00')
            ->assertJsonPath('data.cancellation.refund', '2800000.00');

        [$deposit, $card] = $this->rows($event)->all();
        $this->assertSame(['retained', '1200000.00', '1800000.00'], [$deposit->status->value, $deposit->retained_amount, $deposit->refunded_amount]);
        $this->assertSame(['refunded', '0.00', '1000000.00'], [$card->status->value, $card->retained_amount, $card->refunded_amount]);

        $sale = FiscalReceipt::where('subject_type', 'banquet_payment_entry')->where('type', FiscalReceiptType::SALE->value)->sole();
        $this->assertSame('1200000.00', Money::of($sale->total)->toString());
        $this->assertSame('Bekor qilish haqi', $sale->lines[0]['name']);
        $this->assertTrue((bool) collect($sale->payments)->first()['prepayment']);
        $this->assertSame('cash', collect($sale->payments)->first()['form']);
        $this->assertSame(2, FiscalReceipt::where('subject_type', 'banquet_payment_entry')->where('type', FiscalReceiptType::REFUND->value)->count());
        $this->assertSame(['refund', 'retain', 'refund'], BanquetPaymentEntry::where('banquet_event_id', $event->id)->where('type', '!=', 'payment')->orderBy('id')->pluck('type')->map->value->all());
    }

    public function test_under_fourteen_days_retains_everything(): void
    {
        $event = $this->paidEvent('2026-11-24'); // 10 days ahead

        $this->cancel($event)->assertOk()
            ->assertJsonPath('data.cancellation.rule_percent', '100')
            ->assertJsonPath('data.cancellation.retained', '4000000.00')
            ->assertJsonPath('data.cancellation.refund', '0.00');
        $this->assertSame(0, FiscalReceipt::where('type', FiscalReceiptType::REFUND->value)->count());
        $this->assertSame(2, FiscalReceipt::where('subject_type', 'banquet_payment_entry')->where('type', FiscalReceiptType::SALE->value)->count());
        // Nothing refunded: no approval needed, no shift needed (the web panel may do it)
    }

    public function test_the_manager_overrides_the_retained_part_with_a_reason(): void
    {
        $event = $this->paidEvent('2026-11-24');

        $this->cancel($event, ['retained' => '500000'])->assertUnprocessable()->assertJsonPath('data.reason', 'override_reason_required');
        $this->cancel($event, ['retained' => '4000000.01', 'override_reason' => 'x'])->assertUnprocessable()->assertJsonPath('data.reason', 'retained_invalid');
        $this->assertSame(BanquetEventStatus::HOLD === $event->refresh()->status ? 'hold' : 'confirmed', $event->status->value);

        $this->cancel($event, ['retained' => '500000', 'override_reason' => "Doimiy mijoz, ega rozi"])->assertOk()
            ->assertJsonPath('data.cancellation.computed_retained', '4000000.00')
            ->assertJsonPath('data.cancellation.retained', '500000.00')
            ->assertJsonPath('data.cancellation.refund', '3500000.00')
            ->assertJsonPath('data.cancellation.override_reason', 'Doimiy mijoz, ega rozi');

        $audit = AuditLog::where('action', 'banquet.cancelled')->latest('id')->first();
        $this->assertSame('500000.00', $audit->payload_json['retained'] ?? null);
        $this->assertStringContainsString("menejer o'zgartirdi: Doimiy mijoz", end($this->alerts->sent));
    }

    public function test_cash_refunds_need_the_drawer_and_a_bank_transfer_is_marked_to_return_by_transfer(): void
    {
        $event = $this->event('10000000', '2027-01-20');
        [$deposit, $interim] = $this->rows($event)->all();
        $this->payRow($event, $deposit)->assertCreated();
        $this->panel($this->manager, 'POST', "/pos/banquets/{$event->uuid}/payments/{$interim->uuid}/bank-transfer", ['reference' => 'PP-9'])->assertCreated();

        // From the web panel there is no drawer to pay the cash back from
        $this->panel($this->manager, 'POST', "/pos/banquets/{$event->uuid}/cancellation", ['reason' => 'Bekor'])
            ->assertConflict()->assertJsonPath('data.reason', 'refund_needs_shift');
        // A cashier may not cancel at all (banquets.manage)
        $this->pos('POST', "/banquets/{$event->uuid}/cancellation", ['reason' => 'Bekor'])->assertForbidden();

        $this->cancel($event)->assertOk()->assertJsonPath('data.cancellation.refund', '7000000.00');
        $transfer = BanquetPaymentEntry::where('banquet_event_id', $event->id)->where('type', 'refund')->where('tender', 'bank_transfer')->sole();
        $this->assertTrue($transfer->transfer_pending);
        $this->assertNull($transfer->shift_id);
        $this->assertNull($transfer->fiscal_receipt_uuid);
        $this->assertSame(1, FiscalReceipt::where('type', FiscalReceiptType::REFUND->value)->count());
    }

    public function test_the_cancellers_own_pos_refund_is_the_recorded_approval(): void
    {
        // The manager role holds pos.refund: the approval is the canceller's own
        $event = $this->paidEvent('2027-01-20');
        $this->cancel($event)->assertOk();
        $this->assertSame($this->manager->id, (int) BanquetCancellation::sole()->approved_by);
    }

    public function test_nothing_paid_just_releases_the_event(): void
    {
        $event = $this->event('10000000', '2026-11-20');

        $this->panel($this->manager, 'POST', "/pos/banquets/{$event->uuid}/cancellation", ['reason' => 'Mijoz rad etdi'])->assertOk()
            ->assertJsonPath('data.event.status', 'cancelled')
            ->assertJsonPath('data.cancellation.paid', '0.00')
            ->assertJsonCount(0, 'data.rows');
        $this->assertSame([], $this->alerts->sent);
        $this->assertSame(0, FiscalReceipt::count());

        // Not twice
        $this->panel($this->manager, 'POST', "/pos/banquets/{$event->uuid}/cancellation", ['reason' => 'Yana'])
            ->assertConflict()->assertJsonPath('data.reason', 'event_not_cancellable');
    }
}
