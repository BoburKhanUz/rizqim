<?php

namespace Tests\Feature\PosBilling;

use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Domain\Fiscal\FiscalService;
use App\Enums\UserRole;
use App\Models\FiscalReceipt;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PosShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P3-26 (#24): the till prints the real receipt. A bill payment, a fiscal retry and a refund answer with the receipt
 * as registered in `fiscal.receipt` (fiscal sign, number, QR, terminal, issued at, lines and payments as sent), and
 * GET /pos/fiscal-receipts/{uuid} reads it again for pos.take_payment staff of its branch only.
 */
class PosFiscalSnapshotTest extends TestCase
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
            'fiscal_settings' => [
                'enabled' => true,
                'driver' => 'sandbox',
                'charges' => ['service_charge' => ['mxik' => '10202002001000001']],
            ],
        ])->save();
        $this->openShift('0');
    }

    private function menuOrder(): Order
    {
        $order = $this->posOrder([], ['service_charge_amount' => '20000']);
        OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $this->variant->id,
            'product_name' => 'Osh',
            'quantity' => '2',
            'unit' => 'piece',
            'unit_price' => '100000',
            'total_price' => '200000',
        ]);
        $order->forceFill(['subtotal' => '200000', 'total_price' => '220000'])->save();

        return $order->refresh();
    }

    public function test_a_payment_answers_with_the_sale_receipt_to_print(): void
    {
        $order = $this->menuOrder();
        $bill = $this->bill($order)->assertCreated()->json('data.bills.0');
        $this->assertNull($bill['fiscal']['receipt'], 'no receipt before the payment');

        $response = $this->pay($bill['uuid'], [['tender' => 'cash', 'amount' => '120000', 'tendered_amount' => '150000'], ['tender' => 'card', 'amount' => '100000', 'card_type' => 'uzcard', 'transaction_id' => '4411']])
            ->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $fiscal = $response->json('data.bill.fiscal');
        $stored = FiscalReceipt::where('subject_type', 'order_bill')->firstOrFail();

        $this->assertSame('issued', $fiscal['status']);
        $this->assertSame($stored->uuid, $fiscal['receipt_uuid']);
        $receipt = $fiscal['receipt'];
        $this->assertSame($stored->uuid, $receipt['uuid']);
        $this->assertSame('sale', $receipt['type']);
        $this->assertSame('mixed', $receipt['payment_form']);
        $this->assertSame($stored->fiscal_sign, $receipt['fiscal_sign']);
        $this->assertNotEmpty($receipt['fiscal_sign']);
        $this->assertSame($stored->receipt_number, $receipt['receipt_number']);
        $this->assertSame($stored->qr_url, $receipt['qr_url']);
        $this->assertStringStartsWith('SANDBOX-', (string) $receipt['terminal_id']);
        $this->assertTrue($receipt['non_fiscal']);
        $this->assertNotNull($receipt['issued_at']);
        $this->assertSame('220000.00', $receipt['total']);
        $this->assertSame(['Osh', 'Xizmat haqi'], array_map(fn (array $line) => mb_substr($line['name'], 0, 3) === 'Osh' ? 'Osh' : 'Xizmat haqi', $receipt['lines']));
        $this->assertSame('10202001001000001', $receipt['lines'][0]['mxik_code']);
        $this->assertSame([['form' => 'cash', 'amount' => '120000.00'], ['form' => 'card', 'amount' => '100000.00']], array_map(fn (array $payment) => ['form' => $payment['form'], 'amount' => $payment['amount']], $receipt['payments']));
        $this->assertSame('uzcard', $receipt['payments'][1]['card_type']);

        // The bills list carries it too
        $this->pos('GET', "/orders/{$order->uuid}/bills")->assertOk()->assertJsonPath('data.bills.0.fiscal.receipt.fiscal_sign', $receipt['fiscal_sign']);
    }

    public function test_a_retry_and_a_refund_answer_with_their_receipts(): void
    {
        $this->store->forceFill(['fiscal_settings' => $this->store->fiscal_settings + ['sandbox' => ['fail_attempts' => 1]]])->save();
        $order = $this->menuOrder();
        $bill = $this->bill($order)->json('data.bills.0.uuid');

        $pending = $this->pay($bill, [['tender' => 'cash', 'amount' => '220000']])->assertOk()->json('data.bill.fiscal');
        $this->assertSame('pending', $pending['status']);
        $this->assertSame('pending', $pending['receipt']['status']);
        $this->assertNull($pending['receipt']['fiscal_sign']);

        // The provider answers on its retry; the till's "qayta urinish" then reads the registered receipt
        app(FiscalService::class)->retryNow(FiscalReceipt::where('subject_type', 'order_bill')->firstOrFail());
        $retried = $this->pos('POST', "/bills/{$bill}/fiscal-retry")->assertOk()->assertJsonPath('data.bill.status', 'paid')->json('data.bill.fiscal');
        $this->assertSame('issued', $retried['status']);
        $this->assertNotEmpty($retried['receipt']['fiscal_sign']);

        $this->store->forceFill(['fiscal_settings' => ['sandbox' => ['fail_attempts' => 0]] + $this->store->fiscal_settings])->save();
        $payment = Payment::firstOrFail();
        $refund = $this->posAs($this->manager, 'POST', '/refunds', ['payment_uuid' => $payment->uuid, 'amount' => '22000', 'reason' => 'Xato', 'shift_uuid' => PosShift::value('uuid')])
            ->assertCreated()->json('data.fiscal');
        $this->assertSame('issued', $refund['status']);
        $this->assertSame('refund', $refund['receipt']['type']);
        $this->assertSame('22000.00', $refund['receipt']['total']);
        $this->assertSame($retried['receipt_uuid'], $refund['receipt']['original_receipt_uuid']);
        $this->assertNotEmpty($refund['receipt']['fiscal_sign']);
    }

    public function test_the_till_reads_a_receipt_of_its_own_branch_only(): void
    {
        $order = $this->menuOrder();
        $bill = $this->bill($order)->json('data.bills.0.uuid');
        $uuid = (string) $this->pay($bill, [['tender' => 'cash', 'amount' => '220000']])->json('data.bill.fiscal.receipt_uuid');

        $this->pos('GET', "/fiscal-receipts/{$uuid}")->assertOk()
            ->assertJsonPath('data.uuid', $uuid)
            ->assertJsonPath('data.type', 'sale')
            ->assertJsonPath('data.lines.0.mxik_code', '10202001001000001')
            ->assertJsonMissingPath('data.provider_payload');

        // A waiter has no pos.take_payment; another branch's cashier or terminal and another store: not found
        $this->posAs($this->waiter, 'GET', "/fiscal-receipts/{$uuid}")->assertForbidden();
        $otherCashier = $this->makeStaff(UserRole::CASHIER, $this->otherBranch);
        $this->posAs($otherCashier, 'GET', "/fiscal-receipts/{$uuid}")->assertNotFound();
        $otherTill = $this->pairTerminal($this->owner, $this->otherBranch);
        $this->pos('GET', "/fiscal-receipts/{$uuid}", [], $this->pinSession($otherTill, $this->owner))->assertNotFound();
        $foreignStore = $this->makeStore('Begona');
        $foreignOwner = $this->makeStaff(UserRole::OWNER, null, $foreignStore);
        $this->posAs($foreignOwner, 'GET', "/fiscal-receipts/{$uuid}")->assertNotFound();
        $this->posAs($this->owner, 'GET', '/fiscal-receipts/' . \Illuminate\Support\Str::uuid())->assertNotFound();
        $this->posAs($this->owner, 'GET', "/fiscal-receipts/{$uuid}")->assertOk();
    }
}
