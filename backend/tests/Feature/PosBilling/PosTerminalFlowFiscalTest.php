<?php

namespace Tests\Feature\PosBilling;

use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Fiscal\FiscalService;
use App\Models\FiscalReceipt;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-16 (D5 a, b) on P3-11's real receipts and the sandbox driver: a card paid on a bank terminal in "faqat terminal"
 * mode goes on the sale receipt (form card, RRN, last four digits) and the till reads it back; a till flagged as a
 * smart kassa has its receipts routed to the smart kassa driver and registered on its own provider kassa, and the
 * provider's card total of that kassa is the bank side of the end-of-day comparison.
 */
class PosTerminalFlowFiscalTest extends TestCase
{
    use PosBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosBilling();
        $this->app->forgetInstance(FiscalIssuer::class);
        $this->app->forgetInstance(FiscalLineBuilder::class);
        $this->assertInstanceOf(FiscalService::class, app(FiscalIssuer::class));

        $this->product->forceFill(['mxik_code' => '10202001001000001'])->save();
        $this->store->forceFill([
            'tin' => '301234567',
            'tax_regime' => 'vat',
            'fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox'],
        ])->save();
    }

    private function menuOrder(string $total = '200000'): Order
    {
        $order = $this->posOrder([]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $this->variant->id,
            'product_name' => 'Osh',
            'quantity' => '1',
            'unit' => 'piece',
            'unit_price' => $total,
            'total_price' => $total,
        ]);
        $order->forceFill(['subtotal' => $total, 'total_price' => $total])->save();

        return $order->refresh();
    }

    private function payByCard(string $total, string $rrn, ?string $last4 = null): string
    {
        $bill = (string) $this->bill($this->menuOrder($total))->assertCreated()->json('data.bills.0.uuid');
        $this->pay($bill, [array_filter(['tender' => 'card', 'amount' => $total, 'card_type' => 'uzcard', 'transaction_id' => $rrn, 'card_last4' => $last4])])
            ->assertOk()->assertJsonPath('data.bill.status', 'paid');

        return $bill;
    }

    public function test_the_sale_receipt_carries_the_terminal_rrn_and_the_last_four_digits(): void
    {
        $this->openShift('0');
        $bill = $this->payByCard('200000', '000123456789', '4417');

        $receipt = FiscalReceipt::where('subject_type', 'order_bill')->firstOrFail();
        $this->assertSame(FiscalReceiptStatus::ISSUED, $receipt->status);
        $this->assertSame('card', $receipt->payment_form->value);
        $this->assertSame('sandbox', $receipt->driver);
        $this->assertSame('4417', $receipt->payments[0]['card_last4']);

        $this->pos('GET', "/fiscal-receipts/{$receipt->uuid}")->assertOk()
            ->assertJsonPath('data.payment_form', 'card')
            ->assertJsonPath('data.payments.0.form', 'card')
            ->assertJsonPath('data.payments.0.transaction_id', '000123456789')
            ->assertJsonPath('data.payments.0.card_last4', '4417');
        $this->getJson("/api/v1/pos/orders/" . Order::firstOrFail()->uuid . '/bills')->assertOk()
            ->assertJsonPath('data.bills.0.uuid', $bill)
            ->assertJsonPath('data.bills.0.fiscal.receipt.payments.0.card_last4', '4417');

        // A receipt stored before the field existed reads it as null, and a retry rebuilds it exactly
        $payments = $receipt->payments;
        unset($payments[0]['card_last4']);
        $receipt->forceFill(['payments' => $payments])->save();
        $this->pos('GET', "/fiscal-receipts/{$receipt->uuid}")->assertOk()->assertJsonPath('data.payments.0.card_last4', null);
        $this->assertNull($receipt->refresh()->toDocument()->payments[0]->cardLast4);
    }

    public function test_a_smart_kassa_routes_its_receipts_to_its_provider_kassa(): void
    {
        // The store's ordinary tills go nowhere fiscal; its smart kassas go to the provider's virtual kassa
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'driver' => 'null', 'smart_kassa_driver' => 'sandbox']])->save();
        $this->getJson('/api/v1/owner/fiscal/settings', ['Authorization' => 'Bearer ' . $this->owner->createToken('t')->plainTextToken])->assertOk()
            ->assertJsonPath('data.fiscal_settings.smart_kassa_driver', 'sandbox')
            ->assertJsonPath('data.fiscal_settings.active_driver', 'null');

        $shift = $this->openShift('0');
        $this->payByCard('150000', '501');
        $this->assertSame('null', FiscalReceipt::latest('id')->firstOrFail()->driver);

        $this->till['device']->forceFill(['smart_kassa' => true, 'fiscal_kassa_id' => 'RHMT-0042'])->save();
        $this->payByCard('100000', '502');
        $this->payByCard('60000', '503');
        $receipt = FiscalReceipt::latest('id')->firstOrFail();
        $this->assertSame('sandbox', $receipt->driver);
        $this->assertSame('RHMT-0042', $receipt->terminal_id);

        // The provider's own card total of that kassa is the bank side (the first sale went through no kassa of it)
        $this->pos('PUT', "/shifts/{$shift->uuid}/bank-report", ['source' => 'provider'])->assertOk()
            ->assertJsonPath('data.source', 'provider')
            ->assertJsonPath('data.bank_total', '160000.00')
            ->assertJsonPath('data.bank_count', 2)
            ->assertJsonPath('data.rizqim_total', '310000.00')
            ->assertJsonPath('data.difference', '-150000.00')
            ->assertJsonPath('data.unmatched.bank', [])
            ->assertJsonPath('data.unmatched.rizqim.0.rrn', '501');
    }

    public function test_the_smart_kassa_driver_setting_is_validated_and_defaults_to_the_driver(): void
    {
        $token = $this->owner->createToken('t')->plainTextToken;
        $this->putJson('/api/v1/owner/fiscal/settings', ['fiscal_settings' => ['smart_kassa_driver' => 'rahmat']], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(422)->assertJsonValidationErrors('fiscal_settings.smart_kassa_driver');
        $this->putJson('/api/v1/owner/fiscal/settings', ['fiscal_settings' => ['smart_kassa_driver' => 'null']], ['Authorization' => "Bearer {$token}"])
            ->assertOk()->assertJsonPath('data.fiscal_settings.smart_kassa_driver', 'null');
        $this->putJson('/api/v1/owner/fiscal/settings', ['fiscal_settings' => ['smart_kassa_driver' => null]], ['Authorization' => "Bearer {$token}"])
            ->assertOk()->assertJsonPath('data.fiscal_settings.smart_kassa_driver', 'sandbox');
        $this->assertArrayNotHasKey('smart_kassa_driver', (array) $this->store->refresh()->fiscal_settings);
    }
}
