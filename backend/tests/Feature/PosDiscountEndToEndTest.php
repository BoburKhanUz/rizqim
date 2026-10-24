<?php

namespace Tests\Feature;

use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Fiscal\FiscalService;
use App\Domain\Fiscal\MenuFiscalLineBuilder;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Enums\UserRole;
use App\Models\DiningTable;
use App\Models\FiscalReceipt;
use App\Models\Hall;
use App\Models\ProductVariant;
use App\Models\ServiceFeeSetting;
use App\Services\Telegram\TelegramMessenger;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosBooking\FakeTelegramMessenger;
use Tests\TestCase;

/**
 * P3-37 end to end on the real fiscal module (FiscalService with the sandbox driver, MenuFiscalLineBuilder) and the
 * real manager approvals: the cashier gives 10 % (their own limit) on a table's dishes, a round added later is
 * discounted too, the bill is split in two, both are paid, and the two SALE receipts carry the dishes after their
 * share of the discount (each receipt's total is its bill's) while the X and Z reports count the discount.
 */
class PosDiscountEndToEndTest extends TestCase
{
    use MenuFixtures;
    use RefreshDatabase;
    use StaffOrderFixtures;

    private string $cashierToken;

    private DiningTable $table;

    private ProductVariant $tea;

    private ProductVariant $bread;

    private ProductVariant $plov;

    private ProductVariant $shashlik;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(TelegramMessenger::class, new FakeTelegramMessenger());
        $this->setUpLifecycleStore();
        $cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);

        $hall = Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => 'Asosiy zal', 'sort_order' => 1]);
        $this->table = DiningTable::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $hall->id, 'name' => 'Stol 3', 'kind' => 'table', 'seats' => 4]);
        ServiceFeeSetting::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $hall->id, 'service_charge_percent' => '10', 'place_fee_amount' => '0', 'place_fee_mode' => 'per_person']);

        $this->tea = $this->makeVariant($this->makeProduct('Choy', ['mxik_code' => '10202001005000001']), '5000', ['name' => 'Choynak', 'stock' => 1000]);
        $this->bread = $this->makeVariant($this->makeProduct('Non', ['mxik_code' => '10202001006000001']), '4000', ['name' => 'Dona', 'stock' => 1000]);
        $this->plov = $this->makeVariant($this->makeProduct('Osh', ['mxik_code' => '10202001001000001']), '120000', [
            'name' => 'Choyxona oshi', 'unit' => 'kg', 'quantity_step' => '0.5', 'min_quantity' => '0.5', 'stock' => 1000,
        ]);
        $this->shashlik = $this->makeVariant($this->makeProduct('Shashlik', ['mxik_code' => '10202001007000001']), '18000', ['name' => 'Six', 'stock' => 1000]);

        $this->store->forceFill([
            'tin' => '301234567',
            'tax_regime' => 'vat',
            'fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox', 'charges' => ['service_charge' => ['mxik' => '10202002005000001']]],
        ])->save();

        $till = $this->pairTerminal($this->owner, $this->branch);
        $this->cashierToken = $this->pinSession($till, $cashier);

        $this->assertInstanceOf(FiscalService::class, app(FiscalIssuer::class));
        $this->assertInstanceOf(MenuFiscalLineBuilder::class, app(FiscalLineBuilder::class));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function till(string $method, string $uri, array $data = []): TestResponse
    {
        $this->withBearer($this->cashierToken);
        if ($method !== 'GET') {
            $this->withHeader('Idempotency-Key', (string) Str::uuid());
        }

        return $this->json($method, '/api/v1' . $uri, $data);
    }

    /**
     * @param  iterable<mixed>  $amounts
     */
    private function sum(iterable $amounts): string
    {
        return Money::sum(...array_map(fn ($amount) => (string) $amount, is_array($amounts) ? $amounts : iterator_to_array($amounts)))->toString();
    }

    public function test_a_discounted_order_split_in_two_gets_receipts_and_reports_that_add_up(): void
    {
        $shift = (string) $this->till('POST', '/pos/shifts', ['opening_cash' => '100000'])->assertCreated()->json('data.uuid');

        // 5 shashlik 90 000, 2 tea 10 000, 1 kg plov 120 000: 220 000 of dishes
        $order = $this->till('POST', '/pos/orders', [
            'channel' => 'pos_hall', 'table_id' => $this->table->id, 'guests_count' => 2, 'send' => true,
            'items' => [['variant_id' => $this->shashlik->uuid, 'quantity' => 5], ['variant_id' => $this->tea->uuid, 'quantity' => 2], ['variant_id' => $this->plov->uuid, 'quantity' => '1']],
        ])->assertCreated()->json('data');
        $this->assertSame('220000.00', $order['subtotal']);

        // The cashier's own 10 %: 22 000 off, the service charge on what is left
        $order = $this->till('POST', "/pos/orders/{$order['uuid']}/discount", ['version' => $order['version'], 'kind' => 'percent', 'value' => '10', 'reason' => 'Doimiy mehmon'])
            ->assertOk()->json('data');
        $this->assertSame(['22000.00', '19800.00', '217800.00'], [$order['discount'], $order['service_charge_amount'], $order['total_price']]);

        // 5 bread later: 240 000 of dishes, the 10 % follows (24 000), service charge 21 600, total 237 600
        $order = $this->till('POST', "/pos/orders/{$order['uuid']}/rounds", ['version' => $order['version'], 'send' => true, 'items' => [['variant_id' => $this->bread->uuid, 'quantity' => 5]]])
            ->assertOk()->json('data');
        $this->assertSame(['240000.00', '24000.00', '21600.00', '237600.00'], [$order['subtotal'], $order['discount'], $order['service_charge_amount'], $order['total_price']]);

        // Split in two equal bills
        $bills = $this->till('POST', "/pos/orders/{$order['uuid']}/bills", ['version' => $order['version'], 'mode' => 'equal', 'parts' => 2])->assertCreated()->json('data.bills');
        $this->assertSame(['118800.00', '118800.00'], array_column($bills, 'total'));
        $this->assertSame('24000.00', $this->sum(array_column($bills, 'discount')));
        $this->assertSame('240000.00', $this->sum(array_column($bills, 'subtotal')));
        foreach ($bills as $bill) {
            // Each bill: its dishes less their discount, plus its service charge
            $net = $this->sum(array_map(fn (array $item) => Money::of($item['amount'])->minus($item['discount_amount'])->toString(), $bill['items']));
            $this->assertSame($bill['total'], Money::of($net)->plus($bill['service_charge_amount'])->toString());
            $this->assertSame($bill['discount'], $this->sum(array_column($bill['items'], 'discount_amount')));
        }

        // Bill 1 by card, bill 2 in cash: the order closes
        $this->till('POST', "/pos/bills/{$bills[0]['uuid']}/payments", ['tenders' => [['tender' => 'card', 'amount' => '118800', 'card_type' => 'humo', 'transaction_id' => 'RRN-1']]])
            ->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $this->till('POST', "/pos/bills/{$bills[1]['uuid']}/payments", ['tenders' => [['tender' => 'cash', 'amount' => '118800', 'tendered_amount' => '120000']]])
            ->assertOk()->assertJsonPath('data.bill.status', 'paid')->assertJsonPath('data.order.status', 'delivered');

        // Two SALE receipts, each for its bill's total; the dish lines are after the discount, the service charge its own line
        $sales = FiscalReceipt::where('type', FiscalReceiptType::SALE->value)->orderBy('id')->get();
        $this->assertCount(2, $sales);
        foreach ($sales as $k => $sale) {
            $this->assertSame(FiscalReceiptStatus::ISSUED, $sale->status);
            $this->assertSame($bills[$k]['total'], Money::of($sale->total)->toString());
            $this->assertSame(Money::of($sale->total)->toString(), $this->sum(array_column($sale->lines, 'amount')));
            $this->assertSame(Money::of($sale->total)->toString(), $this->sum(array_column($sale->payments, 'amount')));

            $service = collect($sale->lines)->firstWhere('name', 'Xizmat haqi');
            $this->assertSame($bills[$k]['service_charge_amount'], Money::of($service['amount'])->toString());
            $dishes = collect($sale->lines)->reject(fn (array $line) => $line['name'] === 'Xizmat haqi');
            $this->assertSame(Money::of($bills[$k]['subtotal'])->minus($bills[$k]['discount'])->toString(), $this->sum($dishes->pluck('amount')));
        }
        $this->assertSame('237600.00', $this->sum($sales->pluck('total')));
        // Over both receipts the dishes are 240 000 less 24 000; plov 120 000 less its 12 000 share
        $dishLines = collect([...$sales[0]->lines, ...$sales[1]->lines])->reject(fn (array $line) => $line['name'] === 'Xizmat haqi');
        $this->assertSame('216000.00', $this->sum($dishLines->pluck('amount')));
        $this->assertSame('108000.00', $this->sum($dishLines->filter(fn (array $line) => str_starts_with($line['name'], 'Osh'))->pluck('amount')));

        // X: the discount counted on both bills
        $report = $this->till('GET', "/pos/shifts/{$shift}/x-report")->assertOk()->json('data.report');
        $this->assertSame(['count' => 2, 'amount' => '24000.00'], $report['discounts']);
        $this->assertSame(['240000.00', '24000.00', '21600.00', '237600.00', '237600.00'], [
            $report['sales']['subtotal'], $report['sales']['discount'], $report['sales']['service_charge'], $report['sales']['bills_total'], $report['sales']['received'],
        ]);

        // Z: 100 000 opening + 118 800 cash = 218 800 counted; the report kept with the same discount
        $z = $this->till('POST', "/pos/shifts/{$shift}/close", ['counted_breakdown' => ['200000' => 1, '10000' => 1, '5000' => 1, '2000' => 1, '1000' => 1, '500' => 1, '200' => 1, '100' => 1]])
            ->assertOk()->json('data');
        $this->assertSame(['closed', '218800.00', '0.00'], [$z['status'], $z['expected_cash'], $z['difference']]);
        $this->assertSame(['count' => 2, 'amount' => '24000.00'], $z['summary']['discounts']);
        $this->assertSame('24000.00', $z['summary']['sales']['discount']);
    }
}
