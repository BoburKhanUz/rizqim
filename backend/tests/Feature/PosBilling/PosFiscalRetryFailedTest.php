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
 * Review P3-35 (review A #4) on P3-11's real FiscalService and the sandbox driver: a sale receipt the provider refused
 * is sent again (the same receipt) when the till asks again (POST /pos/bills/{uuid}/fiscal-retry), when the order is
 * closed and when the shift closes, so the bill is paid and the order closed once the cause is fixed.
 */
class PosFiscalRetryFailedTest extends TestCase
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

    private function reject(bool $reject): void
    {
        $this->store->forceFill(['fiscal_settings' => ['sandbox' => ['reject' => $reject]] + $this->store->fiscal_settings])->save();
    }

    public function test_the_tills_retry_sends_a_refused_receipt_again(): void
    {
        $this->reject(true);
        $order = $this->menuOrder();
        $bill = $this->bill($order)->json('data.bills.0.uuid');
        $this->pay($bill, [['tender' => 'cash', 'amount' => '369000']])->assertOk()->assertJsonPath('data.bill.fiscal.status', 'failed');

        // The owner fixed the cause; the cashier presses "retry"
        $this->reject(false);
        $this->pos('POST', "/bills/{$bill}/fiscal-retry")->assertOk()
            ->assertJsonPath('data.bill.status', 'paid')
            ->assertJsonPath('data.bill.fiscal.status', 'issued')
            ->assertJsonPath('data.order.status', 'delivered');

        $receipt = FiscalReceipt::where('subject_type', 'order_bill')->sole();
        $this->assertSame(FiscalReceiptStatus::ISSUED, $receipt->status);
        $this->assertSame(2, $receipt->attempts);
        $this->assertSame(1, FiscalReceipt::count()); // the same receipt, never a second one
    }

    public function test_closing_the_shift_sends_a_refused_receipt_again(): void
    {
        $this->reject(true);
        $order = $this->menuOrder();
        $bill = $this->bill($order)->json('data.bills.0.uuid');
        $this->pay($bill, [['tender' => 'cash', 'amount' => '369000']])->assertOk();

        $shift = \App\Models\PosShift::firstOrFail();
        $this->posAs($this->manager, 'POST', "/shifts/{$shift->uuid}/close", ['counted_breakdown' => ['100000' => 3]])
            ->assertStatus(409)->assertJsonPath('data.reason', 'shift_has_open_orders');

        $this->reject(false);
        $this->posAs($this->manager, 'POST', "/shifts/{$shift->uuid}/close", ['counted_breakdown' => ['100000' => 3]])->assertOk();
        $this->assertSame(OrderStatus::DELIVERED, $order->refresh()->status);
        $this->assertSame(FiscalReceiptStatus::ISSUED, FiscalReceipt::where('subject_type', 'order_bill')->sole()->status);
    }
}
