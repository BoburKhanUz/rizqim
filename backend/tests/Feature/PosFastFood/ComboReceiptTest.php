<?php

namespace Tests\Feature\PosFastFood;

use App\Models\FiscalReceipt;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * P6-31: a combo on the bill and the receipts (owner decision 30.10.2026): one line with its price and quantity and its
 * parts under it without prices; the fiscal receipt one line with the combo's own MXIK (`single`), or by its parts
 * (`split`: shares by the parts' list prices adding up to the tiyin); a refund of the combo; a Telegram order with a
 * combo; an offline sale synced; the product sales report counts the combo as the combo and its parts apart.
 */
class ComboReceiptTest extends TestCase
{
    use ComboFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCombo();
        $this->makeCombo();
    }

    /**
     * A takeaway with one combo (picks), billed and paid in cash: [order, bill json, pay response].
     *
     * @param  list<array<string, mixed>>  $picks
     * @return array{0: Order, 1: array<string, mixed>, 2: TestResponse}
     */
    private function soldCombo(array $picks, int $quantity = 1): array
    {
        $uuid = $this->counterOrder([$this->comboLine($picks, $quantity)])->assertCreated()->json('data.uuid');
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $bill = $this->tillAs('POST', "/pos/orders/{$uuid}/bills", ['version' => (int) $order->version, 'mode' => 'single'])->assertCreated()->json('data.bills.0');
        $paid = $this->tillAs('POST', "/pos/bills/{$bill['uuid']}/payments", ['tenders' => [['tender' => 'cash', 'amount' => $bill['total']]]])->assertOk();

        return [$order->refresh(), $bill, $paid];
    }

    private function receiptOf(string $billUuid): FiscalReceipt
    {
        return FiscalReceipt::query()->where('subject_type', 'order_bill')->where('subject_id', \App\Models\OrderBill::where('uuid', $billUuid)->value('id'))->firstOrFail();
    }

    private function splitMode(): void
    {
        $this->store->forceFill(['fiscal_settings' => ['combo_fiscal_mode' => 'split'] + $this->store->fiscal_settings])->save();
    }

    public function test_the_bill_shows_one_combo_line_with_its_parts_without_prices_and_the_fiscal_line_is_the_combo(): void
    {
        [, $bill, $paid] = $this->soldCombo([['option_id' => $this->option($this->chickenLavash)], ['option_id' => $this->option($this->hotSauce)]], 2);

        $this->assertCount(1, $bill['items']);
        $item = $bill['items'][0];
        $this->assertSame('Lavash kombo', $item['product_name']);
        $this->assertSame(2, $item['quantity']);
        $this->assertSame('100000.00', $item['amount']);
        $this->assertSame(['Asosiy — Tovuq lavash (Katta)', 'Ichimlik — 0,4 l Pepsi', 'Sous — Achchiq sous (Dona)'], array_column($item['combo_parts'], 'label'));
        foreach ($item['combo_parts'] as $part) {
            $this->assertArrayNotHasKey('price_delta', $part);
            $this->assertArrayNotHasKey('list_price', $part);
        }

        $paid->assertJsonPath('data.bill.status', 'paid');
        $lines = $this->receiptOf($bill['uuid'])->lines;
        $this->assertCount(1, $lines);
        $this->assertSame('10202001009000001', $lines[0]['mxik_code']);
        $this->assertSame('Lavash kombo (Kombo)', $lines[0]['name']);
        $this->assertSame('2.000', (string) $lines[0]['quantity']);
        $this->assertSame('100000.00', Money::of($lines[0]['amount'])->toString());
    }

    public function test_split_mode_receipts_the_parts_with_shares_that_add_up_to_the_tiyin(): void
    {
        $this->splitMode();
        // 45 000 over list prices 32 000 / 8 000 / 2 000: 34 285.71 + 8 571.43 + 2 142.86 (largest remainder)
        [, $bill, $paid] = $this->soldCombo([['option_id' => $this->option($this->fanta)], ['option_id' => $this->option($this->hotSauce)]]);
        $paid->assertJsonPath('data.bill.status', 'paid');

        $receipt = $this->receiptOf($bill['uuid']);
        $lines = $receipt->lines;
        $this->assertSame(['10202001011000001', '10202001014000001', '10202001016000001'], array_column($lines, 'mxik_code'));
        $this->assertSame(['34285.71', '8571.43', '2142.86'], array_map(fn ($amount) => Money::of($amount)->toString(), array_column($lines, 'amount')));
        $this->assertSame('45000.00', Money::sum(...array_column($lines, 'amount'))->toString());
        $this->assertSame('45000.00', Money::of($receipt->total)->toString());
        // The guest's bill still shows the one combo line
        $this->assertCount(1, $bill['items']);

        // A refund of the combo gives back the whole line; its receipt splits it the same way and adds up
        $payment = Payment::query()->where('order_id', $bill['order_uuid'] ? Order::where('uuid', $bill['order_uuid'])->value('id') : 0)->firstOrFail();
        $this->tillAs('POST', '/pos/refunds', [
            'payment_uuid' => $payment->uuid, 'amount' => '45000', 'reason' => 'Mijoz qaytardi', 'shift_uuid' => $this->shift->uuid,
            'approval_token' => $this->managerApproval('pos.refund'),
        ])->assertCreated()->assertJsonPath('data.fiscal.status', 'issued');
        $refund = FiscalReceipt::query()->where('type', 'refund')->firstOrFail();
        $this->assertSame('45000.00', Money::sum(...array_column($refund->lines, 'amount'))->toString());
        $this->assertCount(3, $refund->lines);
    }

    public function test_split_shares_follow_the_discount_and_two_combos(): void
    {
        $this->splitMode();
        // Two combos at 52 000 (tovuq +5 000, ayran +2 000): 104 000 over 30 000 / 6 000 list prices
        [, $bill] = $this->soldCombo([['option_id' => $this->option($this->chickenLavash)], ['option_id' => $this->option($this->ayran)]], 2);
        $lines = $this->receiptOf($bill['uuid'])->lines;
        $this->assertSame(['86666.67', '17333.33'], array_map(fn ($amount) => Money::of($amount)->toString(), array_column($lines, 'amount')));
        $this->assertSame(['2.000', '2.000'], array_map(fn ($quantity) => (string) $quantity, array_column($lines, 'quantity')));
        $this->assertSame('104000.00', Money::sum(...array_column($lines, 'amount'))->toString());

        // A 10% discount: the parts share what is left of the combo line (40 500 over 32 000 / 8 000 / 2 000)
        $uuid = $this->counterOrder([$this->comboLine([['option_id' => $this->option($this->fanta)], ['option_id' => $this->option($this->hotSauce)]])])->assertCreated()->json('data.uuid');
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->actingAs($this->manager, 'sanctum')->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/pos/orders/{$uuid}/discount", ['version' => (int) $order->version, 'kind' => 'percent', 'value' => '10', 'reason' => 'Doimiy mijoz'])->assertOk();
        $this->app['auth']->forgetGuards();
        $bill = $this->tillAs('POST', "/pos/orders/{$uuid}/bills", ['version' => (int) $order->refresh()->version, 'mode' => 'single'])->assertCreated()->json('data.bills.0');
        $this->assertSame('40500.00', $bill['total']);
        $this->tillAs('POST', "/pos/bills/{$bill['uuid']}/payments", ['tenders' => [['tender' => 'cash', 'amount' => $bill['total']]]])->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $lines = $this->receiptOf($bill['uuid'])->lines;
        $this->assertSame(['30857.14', '7714.29', '1928.57'], array_map(fn ($amount) => Money::of($amount)->toString(), array_column($lines, 'amount')));
        $this->assertSame('40500.00', Money::sum(...array_column($lines, 'amount'))->toString());
    }

    public function test_a_telegram_order_with_a_combo(): void
    {
        $uuid = $this->order([$this->comboLine([['option_id' => $this->option($this->ayran)]])])->assertCreated()->json('data.order_uuid');
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $this->assertSame('47000.00', Money::of($order->subtotal)->toString());

        $combo = OrderItem::where('order_id', $order->id)->firstOrFail();
        $this->assertSame(['Lavash', 'Ayran'], array_column($combo->combo_parts, 'name'));
        $parts = $combo->comboChildren()->get();
        $this->assertSame(['Lavash', 'Ayran'], $parts->pluck('product_name')->all());
        $this->assertTrue($parts->every(fn (OrderItem $part) => $part->kitchen_status->value === 'pending' && $part->fire_mode === null));

        // The customer's order card lists the parts; the kitchen cooks them and the order is ready after the last one
        $mine = collect($this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/orders')->assertOk()->json('data.data') ?? [])->firstWhere('uuid', $uuid)
            ?? collect($this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/orders')->json('data'))->firstWhere('uuid', $uuid);
        $this->assertSame(['Lavash', 'Ayran'], array_column($mine['items'][0]['combo_parts'], 'name'));
        $this->assertCount(1, $mine['items']);
        $this->setStatus($order, $this->manager, 'accepted')->assertOk();
        $cook = $this->makeCook();
        foreach ($parts as $part) {
            $this->actingAs($cook, 'sanctum')->postJson("/api/v1/staff/orders/{$uuid}/items/{$part->id}/ready")->assertOk();
        }
        $this->assertSame('ready', $order->refresh()->status->value);

        // The server checks the picks of a Telegram order too
        $this->order([$this->comboLine([['option_id' => $this->option($this->pepsi)], ['option_id' => $this->option($this->fanta)]])])
            ->assertStatus(422)->assertJsonPath('code', 'combo_slot_limit');
    }

    public function test_an_offline_combo_sale_is_synced_with_its_parts(): void
    {
        $order = (string) Str::uuid();
        $line = $this->line($this->comboVariant, '1') + ['combo' => [['option_id' => $this->option($this->ayran)], ['option_id' => $this->option($this->garlicSauce)]], 'combo_delta' => '2000'];
        $this->sync([$this->op('order.open', [
            'order' => $order, 'local_number' => 'K1-007', 'channel' => 'pos_takeaway', 'items' => [$line], 'send' => true,
            'menu_taken_at' => now()->subHour()->toJSON(),
        ])])->assertOk()->assertJsonPath('data.results.0.status', 'applied');

        $sold = Order::where('external_id', $order)->firstOrFail();
        $this->assertSame('47000.00', Money::of($sold->subtotal)->toString());
        $combo = OrderItem::where('order_id', $sold->id)->firstOrFail();
        $this->assertSame(['Lavash', 'Ayran', 'Sarimsoqli sous'], array_column($combo->combo_parts, 'name'));
        $this->assertSame(3, $combo->comboChildren()->whereNotNull('sent_at')->count());
        $this->assertEmpty(array_filter((array) $sold->sync_flags, fn ($flag) => ($flag['flag'] ?? null) === 'offline_price_mismatch'));

        // The owner changed a delta while the till was offline: the server's price applies and the manager sees it
        \App\Models\ComboSlotOption::query()->where('uuid', $this->option($this->ayran))->update(['price_delta' => '3000']);
        $second = (string) Str::uuid();
        $line = $this->line($this->comboVariant, '1') + ['combo' => [['option_id' => $this->option($this->ayran)]], 'combo_delta' => '2000'];
        $this->sync([$this->op('order.open', ['order' => $second, 'local_number' => 'K1-008', 'channel' => 'pos_takeaway', 'items' => [$line]])])->assertOk();
        $sold = Order::where('external_id', $second)->firstOrFail();
        $this->assertSame('48000.00', Money::of($sold->subtotal)->toString());
        $this->assertNotEmpty(array_filter((array) $sold->sync_flags, fn ($flag) => ($flag['flag'] ?? null) === 'offline_price_mismatch'));
    }

    public function test_the_product_sales_report_counts_the_combo_as_the_combo_and_its_parts_apart(): void
    {
        $this->soldCombo([['option_id' => $this->option($this->fanta)]], 2);
        $this->soldCombo([]);
        // A lavash sold on its own
        $uuid = $this->counterOrder([['variant_id' => $this->lavash->uuid, 'quantity' => 1]])->assertCreated()->json('data.uuid');
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $bill = $this->tillAs('POST', "/pos/orders/{$uuid}/bills", ['version' => (int) $order->version, 'mode' => 'single'])->assertCreated()->json('data.bills.0');
        $this->tillAs('POST', "/pos/bills/{$bill['uuid']}/payments", ['tenders' => [['tender' => 'cash', 'amount' => $bill['total']]]])->assertOk();
        $this->assertSame(3, Order::where('status', 'delivered')->count());

        $this->app['auth']->forgetGuards();
        $report = $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/reports/top-products?from=' . now()->subDay()->toDateString() . '&to=' . now()->addDay()->toDateString())
            ->assertOk()->json('data');
        $products = collect($report['products'])->keyBy('name');
        $this->assertSame(3, $products['Lavash kombo']['quantity']);
        $this->assertSame('135000.00', $products['Lavash kombo']['revenue']);
        $this->assertSame(1, $products['Lavash']['quantity']);
        $this->assertArrayNotHasKey('Fanta', $products->all());

        $parts = collect($report['combo_parts'])->keyBy('name');
        $this->assertSame(['in_combos' => 3, 'alone' => 1, 'total' => 4], array_intersect_key($parts['Lavash'], array_flip(['in_combos', 'alone', 'total'])));
        $this->assertSame(2, $parts['Fanta']['in_combos']);
        $this->assertSame(1, $parts['Pepsi']['in_combos']);
    }

    private function managerApproval(string $permission): string
    {
        return (string) $this->tillAs('POST', '/pos/approvals', ['permission' => $permission, 'user_id' => $this->manager->id, 'pin' => self::MANAGER_PIN])
            ->assertCreated()->json('data.token');
    }
}
