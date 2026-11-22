<?php

namespace Tests\Feature\PosQr;

use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Domain\Pos\QrPayments\UzQr\UzQrOperatorProtocol as Api;
use App\Models\FiscalReceipt;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Review 11 M3 (the finding): a UzQR payment is receipted as a bank CARD payment, but it never went through the bank
 * terminal and the shift keeps it as a `qr` tender. A smart kassa's end-of-day comparison with source `provider`
 * (SmartKassaGateway::cardTotals: the card payments of the kassa's receipts) now counts every UzQR as bank-terminal
 * card money, against ShiftCardTotals (card tenders only): a false difference of the whole UzQR turnover, an owner
 * alert, and the UzQR RRNs as unmatched bank transactions.
 */
class UzQrBankTerminalTest extends TestCase
{
    use PosQrFixtures;
    use RefreshDatabase;
    use UzQrOperatorFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpQr();
        $this->app->forgetInstance(FiscalIssuer::class);
        $this->app->forgetInstance(FiscalLineBuilder::class);
        $this->product->forceFill(['mxik_code' => '10202001001000001'])->save();
        $this->setUpOperator();
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'driver' => 'null', 'smart_kassa_driver' => 'sandbox', 'uzqr' => ['driver' => 'uzqr_operator']]])->save();
        $this->till['device']->forceFill(['smart_kassa' => true, 'fiscal_kassa_id' => '301234567-0042'])->save();
    }

    private function menuOrder(string $total): Order
    {
        $order = $this->posOrder([]);
        OrderItem::create(['order_id' => $order->id, 'product_variant_id' => $this->variant->id, 'product_name' => 'Osh', 'quantity' => '1', 'unit' => 'piece', 'unit_price' => $total, 'total_price' => $total]);
        $order->forceFill(['subtotal' => $total, 'total_price' => $total])->save();

        return $order->refresh();
    }

    public function test_a_uzqr_payment_is_not_bank_terminal_money_in_the_provider_comparison(): void
    {
        $shift = $this->openShift('0');
        // One card sale through the bank terminal
        $card = (string) $this->bill($this->menuOrder('100000'))->assertCreated()->json('data.bills.0.uuid');
        $this->pay($card, [['tender' => 'card', 'amount' => '100000', 'card_type' => 'uzcard', 'transaction_id' => '000111222333']])->assertOk();

        // One UzQR sale (never on the bank terminal)
        $this->setTillKey()->assertOk();
        $this->fakeOperator([Api::PATH_CREATE => $this->createdAnswer(), Api::PATH_STATUS => $this->paidAnswer(14300000), Api::PATH_CANCEL => ['success' => true]]);
        $bill = (string) $this->bill($this->menuOrder('143000'))->assertCreated()->json('data.bills.0.uuid');
        $qr = $this->qr($bill, 'uzqr')->assertCreated()->json('data.qr.uuid');
        $this->pos('GET', "/qr/{$qr}")->assertOk()->assertJsonPath('data.qr.status', 'paid');
        $this->assertSame(2, FiscalReceipt::where('subject_type', 'order_bill')->where('driver', 'sandbox')->count());

        $this->pos('PUT', "/shifts/{$shift->uuid}/bank-report", ['source' => 'provider'])->assertOk();
        $close = $this->pos('POST', "/shifts/{$shift->uuid}/close", ['counted_breakdown' => []])->assertOk();

        // The bank terminal took 100 000; Rizqim's card money is 100 000: no difference expected
        $this->assertSame(
            ['100000.00', '100000.00', '0.00'],
            [$close->json('data.bank_terminal.bank_total'), $close->json('data.bank_terminal.rizqim_total'), $close->json('data.bank_terminal.difference')],
        );
    }
}
