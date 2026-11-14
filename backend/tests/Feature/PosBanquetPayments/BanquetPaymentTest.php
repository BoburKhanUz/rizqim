<?php

namespace Tests\Feature\PosBanquetPayments;

use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Pos\Banquet\BanquetEventStatus;
use App\Models\AuditLog;
use App\Models\BanquetPayment;
use App\Models\BanquetPaymentEntry;
use App\Models\CashMovement;
use App\Models\FiscalReceipt;
use App\Models\PosShift;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P6-53: taking scheduled payments at the till (cash, card, static QR: an ADVANCE receipt each, the money in the shift
 * apart from the sales), the deposit confirming the event, a part of a row splitting it, idempotency, the refusals and
 * the manager's bank transfer (no shift, no receipt, counted in the paid total), tenant isolation.
 */
class BanquetPaymentTest extends TestCase
{
    use PosBanquetPaymentsFixtures;
    use RefreshDatabase;

    private PosShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBanquetPayments();
        $this->shift = $this->openShift('100000');
    }

    public function test_a_cash_deposit_is_an_advance_receipt_in_the_shift_and_confirms_the_event(): void
    {
        $event = $this->event('10000000');
        $deposit = $this->rows($event)->first();
        $key = (string) Str::uuid();

        $response = $this->payRow($event, $deposit, ['tender' => 'cash'], null, $key)->assertCreated()
            ->assertJsonPath('data.entry.type', 'payment')
            ->assertJsonPath('data.entry.amount', '3000000.00')
            ->assertJsonPath('data.fiscal.status', 'issued')
            ->assertJsonPath('data.schedule.event.status', 'confirmed')
            ->assertJsonPath('data.schedule.rows.0.status', 'paid')
            ->assertJsonPath('data.schedule.summary.paid', '3000000.00')
            ->assertJsonPath('data.schedule.summary.outstanding', '7000000.00');
        $this->assertNotNull($response->json('data.schedule.rows.0.fiscal.receipt_uuid'));
        // A retry with the same key: the same payment, one receipt
        $this->payRow($event, $deposit, ['tender' => 'cash'], null, $key)->assertCreated()->assertJsonPath('data.entry.uuid', $response->json('data.entry.uuid'));
        $this->assertSame(1, BanquetPaymentEntry::count());

        $receipt = FiscalReceipt::where('subject_type', 'banquet_payment_entry')->sole();
        $this->assertSame(FiscalReceiptType::ADVANCE, $receipt->type);
        $this->assertSame('3000000.00', Money::of($receipt->total)->toString());
        $this->assertSame('Tadbir zakladi (avans)', $receipt->lines[0]['name']);
        $this->assertSame('10305001001000000', $receipt->lines[0]['mxik_code'] ?? $receipt->lines[0]['mxik'] ?? null);

        $event->refresh();
        $this->assertSame(BanquetEventStatus::CONFIRMED, $event->status);
        $this->assertNull($event->hold_expires_at);
        $this->assertSame('3000000.00', $event->paid_total);
        $this->assertTrue(AuditLog::where('action', 'banquet.payment_taken')->exists());

        // The X report: the advance apart from the sales, its cash in the drawer, nothing copied into cash movements
        $x = $this->pos('GET', "/shifts/{$this->shift->uuid}/x-report")->assertOk()->json('data.report');
        $this->assertSame(['count' => 1, 'amount' => '3000000.00', 'cash' => '3000000.00', 'card' => '0.00', 'qr' => '0.00'], $x['banquets']['taken']);
        $this->assertSame('0.00', $x['sales']['received']);
        $this->assertSame('3000000.00', $x['cash']['banquets']);
        $this->assertSame('3100000.00', $x['cash']['expected']);
        $this->assertSame(0, CashMovement::where('type', 'sale')->count());
    }

    public function test_a_part_of_a_row_splits_it_and_the_deposit_confirms_once_paid_in_full(): void
    {
        $event = $this->event('10000000');
        $deposit = $this->rows($event)->first();

        $this->payRow($event, $deposit, ['tender' => 'card', 'amount' => '1000000', 'card_type' => 'humo', 'transaction_id' => 'T-1'])->assertCreated()
            ->assertJsonPath('data.schedule.event.status', 'hold')
            ->assertJsonCount(4, 'data.schedule.rows');
        $rows = $this->rows($event);
        $this->assertSame(['1000000.00', '2000000.00', '4000000.00', '3000000.00'], $rows->pluck('amount')->all());
        $this->assertSame(['paid', 'due', 'due', 'due'], $rows->map(fn ($row) => $row->status->value)->all());
        $this->assertSame('2026-11-14', $rows[1]->due_date->toDateString());

        $this->payRow($event, $rows[1], ['tender' => 'qr', 'reference' => 'CLICK-77'])->assertCreated()->assertJsonPath('data.schedule.event.status', 'confirmed');
        $this->assertSame(2, FiscalReceipt::where('subject_type', 'banquet_payment_entry')->where('type', 'advance')->count());
        $this->assertSame(['qr', 'card'], FiscalReceipt::where('subject_type', 'banquet_payment_entry')->orderByDesc('id')->get()->map(fn ($r) => $r->payment_form->value)->all());

        $x = $this->pos('GET', "/shifts/{$this->shift->uuid}/x-report")->assertOk()->json('data.report');
        $this->assertSame(['count' => 2, 'amount' => '3000000.00', 'cash' => '0.00', 'card' => '1000000.00', 'qr' => '2000000.00'], $x['banquets']['taken']);
        $this->assertSame('100000.00', $x['cash']['expected']);
    }

    public function test_refusals(): void
    {
        $event = $this->event('10000000');
        [$deposit, $interim] = $this->rows($event)->all();

        $this->payRow($event, $deposit, ['tender' => 'cash', 'amount' => '3000000.01'])->assertUnprocessable()->assertJsonPath('data.reason', 'amount_exceeds_row');
        $this->payRow($event, $deposit, ['tender' => 'card'])->assertUnprocessable()->assertJsonPath('data.reason', 'card_type_required');
        $this->payRow($event, $deposit, ['tender' => 'card', 'card_type' => 'uzcard'])->assertUnprocessable()->assertJsonPath('data.reason', 'transaction_id_required');
        $this->payRow($event, $deposit, ['tender' => 'account'])->assertUnprocessable();
        $this->withBearer($this->cashierToken)->postJson("/api/v1/pos/banquets/{$event->uuid}/payments/{$deposit->uuid}/pay", ['tender' => 'cash'])
            ->assertStatus(400)->assertJsonPath('data.reason', 'idempotency_key_required');

        // Paid once: not again
        $this->payRow($event, $deposit)->assertCreated();
        $this->payRow($event, $deposit)->assertConflict()->assertJsonPath('data.reason', 'payment_not_due');

        // The web panel has no drawer: no shift
        $this->panel($this->manager, 'POST', "/pos/banquets/{$event->uuid}/payments/{$interim->uuid}/pay", ['tender' => 'cash'])
            ->assertConflict()->assertJsonPath('data.reason', 'shift_not_open');

        // A cancelled event takes nothing
        $event->forceFill(['status' => BanquetEventStatus::CANCELLED])->save();
        $this->payRow($event, $interim)->assertConflict()->assertJsonPath('data.reason', 'event_not_payable');
    }

    public function test_a_bank_transfer_is_recorded_by_the_manager_without_shift_or_receipt(): void
    {
        $event = $this->event('10000000');
        $deposit = $this->rows($event)->first();

        $this->pos('POST', "/banquets/{$event->uuid}/payments/{$deposit->uuid}/bank-transfer", ['reference' => 'PP-1'])->assertForbidden();
        $this->panel($this->manager, 'POST', "/pos/banquets/{$event->uuid}/payments/{$deposit->uuid}/bank-transfer", ['reference' => 'PP-1', 'paid_on' => '2026-11-15'])
            ->assertUnprocessable()->assertJsonPath('data.reason', 'paid_on_future');
        $this->panel($this->manager, 'POST', "/pos/banquets/{$event->uuid}/payments/{$deposit->uuid}/bank-transfer", ['reference' => "To'lov topshirig'i 145", 'paid_on' => '2026-11-13'])
            ->assertCreated()
            ->assertJsonPath('data.entry.type', 'transfer')
            ->assertJsonPath('data.entry.tender', 'bank_transfer')
            ->assertJsonPath('data.entry.business_date', '2026-11-13')
            ->assertJsonPath('data.entry.shift_id', null)
            ->assertJsonPath('data.schedule.event.status', 'confirmed')
            ->assertJsonPath('data.schedule.event.paid_total', '3000000.00')
            ->assertJsonPath('data.schedule.rows.0.tender', 'bank_transfer')
            ->assertJsonPath('data.schedule.rows.0.reference', "To'lov topshirig'i 145");

        $this->assertSame(0, FiscalReceipt::where('subject_type', 'banquet_payment_entry')->count());
        $x = $this->pos('GET', "/shifts/{$this->shift->uuid}/x-report")->assertOk()->json('data.report');
        $this->assertSame(0, $x['banquets']['taken']['count']);
        $this->assertTrue(AuditLog::where('action', 'banquet.transfer_recorded')->exists());
    }

    public function test_another_stores_or_branchs_event_and_row_are_not_found(): void
    {
        $other = $this->makeStore('Boshqa');
        $foreign = $this->event('1000000', '2027-01-20', ['store_id' => $other->id], $this->makeBranch($other, 'B'));
        $mine = $this->event('1000000');
        $foreignRow = $this->rows($foreign)->first();

        $this->pos('GET', "/banquets/{$foreign->uuid}/payments")->assertNotFound();
        $this->payRow($foreign, $foreignRow)->assertNotFound();
        $this->pos('POST', "/banquets/{$mine->uuid}/payments/{$foreignRow->uuid}/pay", ['tender' => 'cash'])->assertNotFound()->assertJsonPath('data.reason', 'payment_not_found');
        $this->panel($this->owner, 'GET', "/pos/banquets/{$foreign->uuid}/cancellation")->assertNotFound();

        // Another branch of the same store: the till acts in its own branch only
        $there = $this->event('1000000', '2027-01-20', [], $this->otherBranch);
        $this->pos('GET', "/banquets/{$there->uuid}/payments")->assertNotFound();
        $this->assertSame('due', BanquetPayment::where('banquet_event_id', $foreign->id)->first()->status->value);
    }
}
