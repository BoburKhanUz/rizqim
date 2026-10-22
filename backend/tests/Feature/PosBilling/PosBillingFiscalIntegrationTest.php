<?php

namespace Tests\Feature\PosBilling;

use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Fiscal\FiscalService;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Fiscal\MenuFiscalLineBuilder;
use App\Enums\OrderStatus;
use App\Models\FiscalReceipt;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P3-18 on P3-11's real receipts (FiscalService, MenuFiscalLineBuilder, the sandbox driver): a split bill's sale
 * receipt with a deposit offset and a nasiya part passes the fiscal validator and adds up; a receipt the provider
 * confirms only on retry pays the bill and closes the order through fiscal.receipt_updated; a refund receipt names
 * the sale.
 */
class PosBillingFiscalIntegrationTest extends TestCase
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
        $this->assertInstanceOf(MenuFiscalLineBuilder::class, app(FiscalLineBuilder::class));

        $this->product->forceFill(['mxik_code' => '10202001001000001'])->save();
        $this->store->forceFill([
            'tin' => '301234567',
            'tax_regime' => 'vat',
            'fiscal_settings' => [
                'enabled' => true,
                'driver' => 'sandbox',
                'charges' => ['service_charge' => ['mxik' => '10202002001000001'], 'room_fee' => ['mxik' => '10202002001000002']],
            ],
        ])->save();
        $this->openShift('0');
    }

    private function menuOrder(): Order
    {
        $order = $this->posOrder([], ['discount' => '10000', 'service_charge_amount' => '29000', 'room_fee_amount' => '50000']);
        foreach ([['2', '200000'], ['1.5', '100000']] as [$quantity, $total]) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_variant_id' => $this->variant->id,
                'product_name' => 'Osh',
                'quantity' => $quantity,
                'unit' => 'piece',
                'unit_price' => '100000',
                'total_price' => $total,
            ]);
        }
        $order->forceFill(['subtotal' => '300000', 'total_price' => '369000'])->save();

        return $order->refresh();
    }

    public function test_split_bills_get_valid_sale_receipts_with_the_deposit_and_nasiya_parts(): void
    {
        $order = $this->menuOrder();
        $this->advances->deposits[$order->id] = '100000';
        $bills = $this->bill($order, 'equal', ['parts' => 3])->assertCreated()->json('data.bills');
        $this->assertSame('369000.00', $this->sumOf(array_column($bills, 'total')));

        $this->pay($bills[0]['uuid'], [['tender' => 'prepayment', 'amount' => '100000'], ['tender' => 'cash', 'amount' => '23000']])->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $this->pay($bills[1]['uuid'], [['tender' => 'card', 'amount' => '123000', 'card_type' => 'humo', 'transaction_id' => '55']])->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $this->pay($bills[2]['uuid'], [['tender' => 'account', 'amount' => '123000', 'account_uuid' => '9b2f3c1e-7a44-4c55-9a11-2d3e4f5a6b7c']], ['approval_token' => $this->approval('customer_accounts.manage')])
            ->assertOk()->assertJsonPath('data.bill.status', 'paid')->assertJsonPath('data.order.status', 'delivered');

        $receipts = FiscalReceipt::where('subject_type', 'order_bill')->orderBy('id')->get();
        $this->assertCount(3, $receipts);
        foreach ($receipts as $k => $receipt) {
            $this->assertSame(FiscalReceiptStatus::ISSUED, $receipt->status);
            $this->assertSame(FiscalReceiptType::SALE, $receipt->type);
            $this->assertSame($bills[$k]['total'], Money::of($receipt->total)->toString());
        }
        $this->assertSame(['cash', 'card', 'cash'], $receipts->map(fn ($receipt) => $receipt->payment_form->value)->all());
        $this->assertSame('369000.00', Money::sum(...$receipts->pluck('total')->all())->toString());
    }

    public function test_a_receipt_confirmed_on_retry_pays_the_bill_and_closes_the_order(): void
    {
        $this->store->forceFill(['fiscal_settings' => $this->store->fiscal_settings + ['sandbox' => ['fail_attempts' => 1]]])->save();
        $order = $this->menuOrder();
        $bill = $this->bill($order)->json('data.bills.0.uuid');

        $this->pay($bill, [['tender' => 'cash', 'amount' => '369000']])->assertOk()
            ->assertJsonPath('data.bill.fiscal.status', 'pending')
            ->assertJsonPath('data.order.status', 'accepted');

        $receipt = FiscalReceipt::where('subject_type', 'order_bill')->firstOrFail();
        app(FiscalService::class)->retryNow($receipt);

        $this->assertSame(OrderStatus::DELIVERED, $order->refresh()->status);
        $this->getJson("/api/v1/pos/orders/{$order->uuid}/bills")->assertOk()
            ->assertJsonPath('data.bills.0.status', 'paid')
            ->assertJsonPath('data.bills.0.fiscal.receipt_uuid', $receipt->uuid);

        // A refund names the sale and its lines are a share of it
        $this->store->forceFill(['fiscal_settings' => ['sandbox' => ['fail_attempts' => 0]] + $this->store->fiscal_settings])->save();
        $payment = Payment::firstOrFail();
        $this->posAs($this->manager, 'POST', '/refunds', ['payment_uuid' => $payment->uuid, 'amount' => '36900', 'reason' => 'Xato', 'shift_uuid' => \App\Models\PosShift::value('uuid')])
            ->assertCreated()->assertJsonPath('data.fiscal.status', 'issued');
        $refund = FiscalReceipt::where('type', 'refund')->firstOrFail();
        $this->assertSame($receipt->id, $refund->original_receipt_id);
        $this->assertSame('36900.00', Money::of($refund->total)->toString());
    }
}
