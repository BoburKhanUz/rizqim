<?php

namespace Tests\Feature;

use App\Domain\Fiscal\FiscalException;
use App\Domain\Fiscal\FiscalPayment;
use App\Domain\Fiscal\FiscalPaymentForm;
use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Fiscal\FiscalSnapshot;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P3 integration fixes on the fiscal core: the nasiya part of a sale is a `credit` payment (only on a sale, never
 * also a prepayment; carried through the snapshot, the receipt API and the sandbox answer), and a store whose fiscal
 * mode is off may sell lines without MXIK codes (non_fiscal receipts) while a fiscal store still may not.
 */
class FiscalCreditAndNonFiscalTest extends TestCase
{
    use FiscalFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFiscal();
    }

    private function expectReason(string $reason, callable $call, ?string $message = null): void
    {
        try {
            $call();
            $this->fail("Expected FiscalException {$reason}");
        } catch (FiscalException $e) {
            $this->assertSame($reason, $e->reason, $e->getMessage());
            if ($message !== null) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    public function test_a_sale_part_sold_on_credit_is_a_credit_payment(): void
    {
        $sale = $this->issuer()->issue($this->document(lines: [$this->line('850000')], payments: [
            $this->cash('350000'),
            new FiscalPayment(FiscalPaymentForm::CASH, Money::of(500000), credit: true),
        ]));
        $this->assertTrue($sale->isIssued());

        $receipt = $this->receipt($sale->receiptUuid);
        $this->assertSame([false, true], array_column($receipt->payments, 'credit'));
        $this->assertSame([false, false], array_column($receipt->payments, 'prepayment'));
        // Rebuilt for a retry exactly as sent
        $this->assertTrue($receipt->toDocument()->payments[1]->credit);
        // The sandbox answers like a provider: what was not received now
        $this->assertSame('500000.00', $receipt->getAttribute('provider_payload')['credit']);
        $this->assertSame('0.00', $receipt->getAttribute('provider_payload')['prepaid']);

        // A sale fully on credit takes the credit part's form; card + credit is card
        $all = $this->issuer()->issue($this->document(payments: [new FiscalPayment(FiscalPaymentForm::CASH, Money::of(100000), credit: true)]));
        $this->assertSame(FiscalPaymentForm::CASH, $this->receipt($all->receiptUuid)->payment_form);
        $card = $this->issuer()->issue($this->document(payments: [$this->card('40000'), new FiscalPayment(FiscalPaymentForm::CASH, Money::of(60000), credit: true)]));
        $this->assertSame(FiscalPaymentForm::CARD, $this->receipt($card->receiptUuid)->payment_form);

        // Older stored rows without the flag read as credit: false
        $this->assertFalse(FiscalSnapshot::payment(['form' => 'cash', 'amount' => '1.00', 'prepayment' => true])->credit);
    }

    public function test_credit_is_only_for_a_sale_and_never_also_a_prepayment(): void
    {
        $deposit = $this->lines()->forCharge($this->store, 'deposit', Money::of(100000));
        $this->expectReason(FiscalException::INVALID_DOCUMENT, fn () => $this->issuer()->issue($this->document(
            FiscalReceiptType::ADVANCE, [$deposit], [new FiscalPayment(FiscalPaymentForm::CASH, Money::of(100000), credit: true)],
        )), 'faqat sotuv chekida');

        $this->expectReason(FiscalException::INVALID_DOCUMENT, fn () => $this->issuer()->issue($this->document(
            payments: [new FiscalPayment(FiscalPaymentForm::CASH, Money::of(100000), prepayment: true, credit: true)],
        )), 'ham avans hisobidan, ham nasiyaga');
    }

    public function test_the_receipt_api_shows_the_credit_flag(): void
    {
        $sale = $this->issuer()->issue($this->document(lines: [$this->line('100000')], payments: [
            new FiscalPayment(FiscalPaymentForm::CASH, Money::of(100000), credit: true),
        ]));

        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/fiscal/receipts/{$sale->receiptUuid}")->assertOk()
            ->assertJsonPath('data.payments.0.credit', true)
            ->assertJsonPath('data.payments.0.prepayment', false);
    }

    public function test_a_store_outside_fiscal_mode_sells_lines_without_mxik(): void
    {
        $this->product->forceFill(['mxik_code' => null, 'package_code' => '777'])->save();
        $this->store->forceFill(['fiscal_settings' => ['enabled' => false]])->save();

        $item = $this->orderItem('2', '50000');
        $line = $this->lines()->forOrderItem($item, Quantity::of(2), Money::of(100000));
        $this->assertSame('', $line->mxikCode);
        $this->assertNull($line->packageCode);
        $room = $this->lines()->forCharge($this->store, 'room_fee', Money::of(30000));
        $this->assertSame('', $room->mxikCode);

        $sale = $this->issuer()->issue($this->document(lines: [$line, $room]));
        $this->assertTrue($sale->isIssued());
        $receipt = $this->receipt($sale->receiptUuid);
        $this->assertSame(FiscalReceiptStatus::ISSUED, $receipt->status);
        $this->assertTrue($receipt->non_fiscal);
        $this->assertSame('null', $receipt->driver);
        $this->assertNull($receipt->fiscal_sign);

        // Advance and credit receipts: their charge line may carry no code either
        config(['fiscal.charges.deposit.mxik' => null, 'fiscal.charges.credit.mxik' => null]);
        $deposit = $this->lines()->forCharge($this->store, 'deposit', Money::of(50000));
        $this->assertTrue($this->issuer()->issue($this->document(FiscalReceiptType::ADVANCE, [$deposit]))->isIssued());
        $credit = $this->lines()->forCharge($this->store, 'credit', Money::of(50000));
        $this->assertTrue($this->issuer()->issue($this->document(FiscalReceiptType::CREDIT, [$credit]))->isIssued());

        // A code that is there must still be a valid one
        $this->expectReason(FiscalException::INVALID_DOCUMENT, fn () => $this->issuer()->issue($this->document(lines: [$this->line('1000', '123')])), '17 ta raqam');
    }

    public function test_a_fiscal_store_still_refuses_lines_without_mxik(): void
    {
        $this->product->forceFill(['mxik_code' => null])->save();
        $item = $this->orderItem('2', '50000');

        $this->expectReason(FiscalException::MXIK_MISSING, fn () => $this->lines()->forOrderItem($item, Quantity::of(2), Money::of(100000)));
        $this->expectReason(FiscalException::MXIK_MISSING, fn () => $this->lines()->forCharge($this->store, 'room_fee', Money::of(1000)));
        $this->expectReason(FiscalException::MXIK_MISSING, fn () => $this->issuer()->issue($this->document(lines: [$this->line('1000', '')])), "MXIK'siz");
    }
}
