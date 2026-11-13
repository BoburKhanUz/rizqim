<?php

namespace Tests\Feature\PosVouchers;

use App\Domain\Fiscal\FiscalReceiptType;
use App\Models\CashMovement;
use App\Models\FiscalReceipt;
use App\Models\PosShift;
use App\Models\Voucher;
use App\Models\VoucherEntry;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P6-43: a gift certificate sold at the till (an ADVANCE receipt, the money in the shift apart from the sales), then
 * spent on two bills (the sale receipts at the full bill total, the certificate part as prepayment), never twice and
 * never past its balance; a refund of a certificate-paid bill goes back onto it.
 */
class PosVoucherSaleTest extends TestCase
{
    use PosVouchersFixtures;
    use RefreshDatabase;

    private PosShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpVouchers();
        $this->shift = $this->openShift('100000');
    }

    public function test_advance_then_two_sales_with_receipts_and_the_balance_used_once(): void
    {
        $key = (string) Str::uuid();
        $sold = $this->sell('200000', 'cash', ['buyer_name' => 'Aziz', 'buyer_phone' => '90 123 45 67'], $key)->assertCreated()
            ->assertJsonPath('data.face_value', '200000.00')
            ->assertJsonPath('data.balance', '200000.00')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.sale.tender', 'cash')
            ->assertJsonPath('data.fiscal.status', 'issued')
            ->assertJsonPath('data.buyer.phone', '+99890*****67')
            ->json('data');
        $this->assertMatchesRegularExpression('/^[A-Z3469]{4}-[A-Z3469]{4}-[A-Z3469]{4}$/', $sold['code']);
        $this->assertSame($sold['code'], $sold['print']['qr']);
        // The same Idempotency-Key: the same certificate, one receipt
        $this->sell('200000', 'cash', ['buyer_name' => 'Aziz', 'buyer_phone' => '90 123 45 67'], $key)->assertCreated()->assertJsonPath('data.uuid', $sold['uuid']);
        $this->assertSame(1, Voucher::count());

        $advance = FiscalReceipt::where('subject_type', 'voucher')->sole();
        $this->assertSame(FiscalReceiptType::ADVANCE, $advance->type);
        $this->assertSame('200000.00', Money::of($advance->total)->toString());
        $this->assertCount(1, $advance->lines);
        $this->assertSame('cash', $advance->payment_form->value);

        // The shift: the advance apart from the sales, its cash in the drawer, no cash movement copied
        $x = $this->pos('GET', "/shifts/{$this->shift->uuid}/x-report")->assertOk()->json('data.report');
        $this->assertSame(['count' => 1, 'amount' => '200000.00', 'cash' => '200000.00', 'card' => '0.00', 'qr' => '0.00'], $x['vouchers']['sold']);
        $this->assertSame('0.00', $x['sales']['received']);
        $this->assertSame('200000.00', $x['cash']['vouchers']);
        $this->assertSame('300000.00', $x['cash']['expected']);
        $this->assertSame(0, CashMovement::where('type', 'sale')->count());

        // First bill 150 000: 120 000 from the certificate, the rest in cash
        $first = $this->billOf($this->menuOrder('150000'));
        $this->pay($first, [['tender' => 'voucher', 'amount' => '120000', 'voucher_code' => strtolower(str_replace('-', ' ', $sold['code']))], ['tender' => 'cash', 'amount' => '30000']])
            ->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $sale = FiscalReceipt::where('subject_type', 'order_bill')->sole();
        $this->assertSame(FiscalReceiptType::SALE, $sale->type);
        $this->assertSame('150000.00', Money::of($sale->total)->toString());
        $parts = collect($sale->payments);
        $this->assertSame([true, false], $parts->pluck('prepayment')->all());
        $this->assertSame('cash', $parts->first()['form']);
        $this->assertSame('80000.00', Voucher::sole()->balance);

        // Second bill: the 80 000 left, then nothing
        $second = $this->billOf($this->menuOrder('100000'));
        $this->pay($second, [['tender' => 'voucher', 'amount' => '90000', 'voucher_code' => $sold['code']]])->assertConflict()
            ->assertJsonPath('data.reason', 'voucher_insufficient')->assertJsonPath('data.balance', '80000.00');
        $this->pay($second, [['tender' => 'voucher', 'amount' => '80000', 'voucher_code' => $sold['code']], ['tender' => 'card', 'amount' => '20000', 'card_type' => 'humo', 'transaction_id' => '77']])
            ->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $this->assertSame('0.00', Voucher::sole()->balance);
        $this->assertSame('used', Voucher::sole()->status->value);

        $third = $this->billOf($this->menuOrder('10000'));
        $this->pay($third, [['tender' => 'voucher', 'amount' => '1000', 'voucher_code' => $sold['code']]])->assertConflict()->assertJsonPath('data.reason', 'voucher_used');

        $this->assertSame(['sale', 'redemption', 'redemption'], VoucherEntry::orderBy('id')->pluck('type')->map->value->all());
        $this->assertSame(['200000.00', '80000.00', '0.00'], VoucherEntry::orderBy('id')->pluck('balance_after')->all());

        // The X report: the redemptions as the voucher tender, the receipts add up to the bills
        $x = $this->pos('GET', "/shifts/{$this->shift->uuid}/x-report")->assertOk()->json('data.report');
        $this->assertSame(['count' => 2, 'amount' => '200000.00'], $x['vouchers']['redeemed']);
        $this->assertSame('200000.00', collect($x['by_tender'])->firstWhere('tender', 'voucher')['amount']);
        $this->assertSame('330000.00', $x['cash']['expected']);
    }

    public function test_one_certificate_once_per_bill_and_the_code_is_required(): void
    {
        $code = $this->sell('50000')->assertCreated()->json('data.code');
        $bill = $this->billOf($this->menuOrder('100000'));

        $this->pay($bill, [['tender' => 'voucher', 'amount' => '10000']])->assertUnprocessable()->assertJsonPath('data.reason', 'voucher_code_required');
        $this->pay($bill, [['tender' => 'voucher', 'amount' => '10000', 'voucher_code' => $code], ['tender' => 'voucher', 'amount' => '10000', 'voucher_code' => $code]])
            ->assertConflict()->assertJsonPath('data.reason', 'voucher_already_on_bill');
        // Nothing was taken by the refused request
        $this->assertSame('50000.00', Voucher::sole()->balance);
        $this->pay($bill, [['tender' => 'voucher', 'amount' => '10000', 'voucher_code' => $code]])->assertOk();
        $this->pay($bill, [['tender' => 'voucher', 'amount' => '10000', 'voucher_code' => $code]])->assertConflict()->assertJsonPath('data.reason', 'voucher_already_on_bill');
        $this->assertSame('40000.00', Voucher::sole()->balance);
    }

    public function test_a_refund_of_a_certificate_paid_bill_goes_back_onto_it_never_cash(): void
    {
        $code = $this->sell('100000', 'card', ['card_type' => 'uzcard', 'transaction_id' => 'RRN1'])->assertCreated()->json('data.code');
        $this->assertSame('card', FiscalReceipt::where('subject_type', 'voucher')->sole()->payment_form->value);
        $bill = $this->billOf($this->menuOrder('60000'));
        $this->pay($bill, [['tender' => 'voucher', 'amount' => '60000', 'voucher_code' => $code]])->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $payment = \App\Models\Payment::where('provider', 'voucher')->sole();
        $this->assertSame('card', FiscalReceipt::where('subject_type', 'order_bill')->sole()->payments[0]['form']);
        $cashBefore = $this->pos('GET', "/shifts/{$this->shift->uuid}/x-report")->json('data.report.cash.expected');

        $this->posAs($this->manager, 'POST', '/refunds', ['payment_uuid' => $payment->uuid, 'amount' => '25000', 'reason' => 'Taom sovuq', 'shift_uuid' => $this->shift->uuid])
            ->assertCreated()->assertJsonPath('data.fiscal.status', 'issued');
        $this->assertSame('65000.00', Voucher::sole()->balance);
        $entry = VoucherEntry::where('type', 'refund')->sole();
        $this->assertSame('25000.00', $entry->amount);
        $refund = FiscalReceipt::where('type', 'refund')->sole();
        $this->assertTrue($refund->payments[0]['prepayment']);
        $this->assertSame(0, CashMovement::where('type', 'refund')->count());

        // The rest of it, then not a tiyin more
        $this->posAs($this->manager, 'POST', '/refunds', ['payment_uuid' => $payment->uuid, 'amount' => '35000', 'reason' => 'Taom sovuq', 'shift_uuid' => $this->shift->uuid])->assertCreated();
        $this->assertSame('100000.00', Voucher::sole()->balance);
        $this->assertSame('active', Voucher::sole()->status->value);
        $this->posAs($this->manager, 'POST', '/refunds', ['payment_uuid' => $payment->uuid, 'amount' => '1', 'reason' => 'Taom sovuq', 'shift_uuid' => $this->shift->uuid])->assertUnprocessable();
        $this->cashierToken = $this->pinSession($this->till, $this->cashier);
        $this->assertSame($cashBefore, $this->pos('GET', "/shifts/{$this->shift->uuid}/x-report")->json('data.report.cash.expected'));
    }

    public function test_sale_input_is_checked_before_any_money(): void
    {
        $this->sell('500')->assertUnprocessable()->assertJsonPath('data.reason', 'face_value_invalid');
        $this->sell('10000.50')->assertUnprocessable()->assertJsonPath('data.reason', 'face_value_invalid');
        $this->sell('10000', 'card')->assertUnprocessable()->assertJsonPath('data.reason', 'card_type_required');
        $this->sell('10000', 'cash', ['buyer_phone' => '123'])->assertUnprocessable()->assertJsonPath('data.reason', 'buyer_phone_invalid');
        $this->sell('10000', 'cash', ['valid_until' => '2020-01-01'])->assertUnprocessable()->assertJsonPath('data.reason', 'valid_until_invalid');
        $this->sell('10000', 'prepayment')->assertUnprocessable();
        $this->assertSame(0, Voucher::count());
        $this->assertSame(0, FiscalReceipt::count());
    }
}
