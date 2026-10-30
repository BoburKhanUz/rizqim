<?php

namespace Tests\Feature\PosTakeaway;

use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Pos\Takeaway\PackagingRules;
use App\Models\AuditLog;
use App\Models\BranchPackagingRule;
use App\Models\FiscalReceipt;
use App\Models\Order;
use App\Services\Payment\Fiscal\OnlineFiscalReceiptBuilder;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-34 (B8): packaging lines by the branch's rules — a bag per takeaway, a bowl per soup portion — on POS takeaways
 * and Telegram pickups (never the hall), each its own bill and receipt line with its own MXIK, removable by the
 * cashier with a reason, split over bills to the tiyin, and the owner's rules API.
 */
class PosPackagingTest extends TestCase
{
    use PosTakeawayFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosTakeaway();
    }

    public function test_a_takeaway_gets_a_bag_and_a_bowl_per_soup_portion_after_the_discount(): void
    {
        // 2 soups (50 000) + 1 shashlik (18 000): a bag 1 000, 2 bowls 6 000
        $opened = $this->takeaway([[$this->soup, 2], [$this->shashlik, 1]])->assertCreated()
            ->assertJsonPath('data.charges.items_subtotal', '68000.00')
            ->assertJsonPath('data.charges.packaging_amount', '7000.00')
            ->assertJsonPath('data.packaging_amount', '7000.00')
            ->assertJsonPath('data.total_price', '75000.00')
            ->assertJsonPath('data.charges.packaging_lines.0.name', 'Paket')
            ->assertJsonPath('data.charges.packaging_lines.0.quantity', 1)
            ->assertJsonPath('data.charges.packaging_lines.0.amount', '1000.00')
            ->assertJsonPath('data.charges.packaging_lines.1.name', "Sho'rva idishi")
            ->assertJsonPath('data.charges.packaging_lines.1.quantity', 2)
            ->assertJsonPath('data.charges.packaging_lines.1.amount', '6000.00')
            ->assertJsonPath('data.charges.packaging_lines.1.mxik', self::BOWL_MXIK);

        // A 10% discount is on the dishes only: the packaging is never discounted
        $uuid = (string) $opened->json('data.uuid');
        $this->tillAs('POST', "/pos/orders/{$uuid}/discount", ['version' => (int) $opened->json('data.version'), 'kind' => 'percent', 'value' => '10', 'reason' => 'Doimiy mijoz'])
            ->assertOk()
            ->assertJsonPath('data.charges.discount', '6800.00')
            ->assertJsonPath('data.charges.packaging_amount', '7000.00')
            ->assertJsonPath('data.total_price', '68200.00');

        // More soup later: one more bowl
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $this->tillAs('POST', "/pos/orders/{$uuid}/rounds", ['version' => (int) $order->version, 'items' => [['variant_id' => $this->soup->uuid, 'quantity' => 1]]])
            ->assertOk()
            ->assertJsonPath('data.charges.packaging_lines.1.quantity', 3)
            ->assertJsonPath('data.charges.packaging_amount', '10000.00');
    }

    public function test_the_hall_and_a_counter_order_carried_to_the_table_get_no_packaging(): void
    {
        $this->takeaway([[$this->soup, 1]], ['guests_count' => 1], 'pos_hall')->assertCreated()
            ->assertJsonPath('data.charges.packaging_amount', '0.00')
            ->assertJsonCount(0, 'data.charges.packaging_lines');

        $this->useProfile($this->branch, 'kafe');
        $this->takeaway([[$this->soup, 1]], ['table_label' => '12'])->assertCreated()
            ->assertJsonPath('data.charges.packaging_amount', '0.00');
    }

    public function test_the_cashier_takes_the_bag_off_with_a_reason_and_puts_it_back(): void
    {
        $opened = $this->takeaway([[$this->soup, 1]])->assertCreated()->assertJsonPath('data.total_price', '29000.00');
        $uuid = (string) $opened->json('data.uuid');

        $this->tillAs('POST', "/pos/orders/{$uuid}/packaging/{$this->bag->id}/remove", ['version' => (int) $opened->json('data.version')])
            ->assertStatus(422);

        $removed = $this->tillAs('POST', "/pos/orders/{$uuid}/packaging/{$this->bag->id}/remove", ['version' => (int) $opened->json('data.version'), 'reason' => "Mijoz o'z paketini olib keldi"])
            ->assertOk()
            ->assertJsonPath('data.total_price', '28000.00')
            ->assertJsonPath('data.charges.packaging_lines.0.removed', true)
            ->assertJsonPath('data.charges.packaging_lines.0.amount', '0.00')
            ->assertJsonPath('data.charges.packaging_lines.0.removed_reason', "Mijoz o'z paketini olib keldi");
        $log = AuditLog::where('action', 'pos.packaging_removed')->sole();
        $this->assertSame((int) $this->cashier->id, (int) $log->user_id);

        // A stale version, a second removal, a line the order does not have
        $this->tillAs('POST', "/pos/orders/{$uuid}/packaging/{$this->bag->id}/restore", ['version' => (int) $opened->json('data.version')])
            ->assertStatus(409)->assertJsonPath('data.reason', 'version_conflict');
        $this->tillAs('POST', "/pos/orders/{$uuid}/packaging/{$this->bag->id}/remove", ['version' => (int) $removed->json('data.version'), 'reason' => 'Yana'])
            ->assertStatus(422)->assertJsonPath('data.reason', 'already_removed');
        $this->tillAs('POST', "/pos/orders/{$uuid}/packaging/999999/remove", ['version' => (int) $removed->json('data.version'), 'reason' => 'Yana'])
            ->assertStatus(422)->assertJsonPath('data.reason', 'packaging_not_found');

        $restored = $this->tillAs('POST', "/pos/orders/{$uuid}/packaging/{$this->bag->id}/restore", ['version' => (int) $removed->json('data.version')])
            ->assertOk()->assertJsonPath('data.total_price', '29000.00')->assertJsonPath('data.charges.packaging_lines.0.removed', false);
        $this->assertTrue(AuditLog::where('action', 'pos.packaging_restored')->exists());

        // Once a bill is being paid the packaging stays as billed
        $this->payAll($uuid, (int) $restored->json('data.version'));
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $this->tillAs('POST', "/pos/orders/{$uuid}/packaging/{$this->bag->id}/remove", ['version' => (int) $order->version, 'reason' => 'Kech'])
            ->assertStatus(422)->assertJsonPath('data.reason', 'order_closed');
    }

    public function test_each_packaging_line_is_its_own_receipt_line_with_its_own_mxik(): void
    {
        $opened = $this->takeaway([[$this->soup, 2]])->assertCreated();
        $bill = $this->payAll((string) $opened->json('data.uuid'), (int) $opened->json('data.version'));
        $this->assertSame('7000.00', $bill['packaging_amount']);
        $this->assertSame([['rule_id' => $this->bag->id, 'name' => 'Paket', 'quantity' => 1, 'unit' => 'dona', 'amount' => '1000.00'], ['rule_id' => $this->bowl->id, 'name' => "Sho'rva idishi", 'quantity' => 2, 'unit' => 'dona', 'amount' => '6000.00']], $bill['packaging_lines']);

        $receipt = FiscalReceipt::where('subject_type', 'order_bill')->sole();
        $this->assertSame(FiscalReceiptStatus::ISSUED, $receipt->status);
        $lines = collect($receipt->lines)->keyBy('name');
        $this->assertSame('57000.00', Money::of($receipt->total)->toString());
        // The bag takes the store's packaging code, the bowl its own
        $this->assertSame(self::PACKAGING_MXIK, $lines['Paket']['mxik_code']);
        $this->assertSame('1500001', $lines['Paket']['package_code']);
        $this->assertSame('1000.00', $lines['Paket']['amount']);
        $this->assertSame(self::BOWL_MXIK, $lines["Sho'rva idishi"]['mxik_code']);
        $this->assertSame('2500002', $lines["Sho'rva idishi"]['package_code']);
        $this->assertSame('2.000', $lines["Sho'rva idishi"]['quantity']);
        $this->assertSame('3000.00', $lines["Sho'rva idishi"]['unit_price']);
        $this->assertSame('57000.00', Money::sum(...array_column($receipt->lines, 'amount'))->toString());
    }

    public function test_split_bills_share_the_packaging_to_the_tiyin(): void
    {
        // 1 soup + 1 shashlik + a bag: 43 000 + 4 000 = 47 000, in 3 bills
        $opened = $this->takeaway([[$this->soup, 1], [$this->shashlik, 1]])->assertCreated()->assertJsonPath('data.total_price', '47000.00');
        $bills = $this->tillAs('POST', '/pos/orders/' . $opened->json('data.uuid') . '/bills', ['version' => (int) $opened->json('data.version'), 'mode' => 'equal', 'parts' => 3])
            ->assertSuccessful()->json('data.bills');
        $this->assertCount(3, $bills);
        $this->assertSame('4000.00', Money::sum(...array_column($bills, 'packaging_amount'))->toString());
        foreach ($bills as $bill) {
            $this->tillAs('POST', '/pos/bills/' . $bill['uuid'] . '/payments', ['shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => $bill['total']]]])->assertSuccessful();
            $this->assertSame($bill['packaging_amount'], Money::sum(...array_column($bill['packaging_lines'], 'amount'))->toString());
        }

        $receipts = FiscalReceipt::where('subject_type', 'order_bill')->get();
        $this->assertCount(3, $receipts);
        $packaging = $receipts->flatMap(fn (FiscalReceipt $receipt) => collect($receipt->lines)->whereIn('mxik_code', [self::PACKAGING_MXIK, self::BOWL_MXIK]));
        $this->assertSame('4000.00', Money::sum(...$packaging->pluck('amount')->all())->toString());
        foreach ($receipts as $receipt) {
            $this->assertSame(Money::of($receipt->total)->toString(), Money::sum(...array_column($receipt->lines, 'amount'))->toString());
        }
    }

    public function test_a_telegram_pickup_and_delivery_get_their_rules_and_the_receipt_lines(): void
    {
        $this->soup->product->forceFill(['package_code' => '1100001'])->save();
        $this->order([['variant_id' => $this->soup->uuid, 'quantity' => 2]])->assertCreated();
        $pickup = Order::where('channel', 'telegram')->latest('id')->firstOrFail();
        $this->assertSame('57000.00', Money::of($pickup->total_price)->toString());
        $this->assertSame('7000.00', Money::of($pickup->packaging_amount)->toString());
        $this->assertSame(['Paket', "Sho'rva idishi"], array_column($pickup->packaging_lines ?? [], 'name'));
        $this->assertSame('57000.00', Money::of($pickup->payments()->value('amount'))->toString());

        $receipt = app(OnlineFiscalReceiptBuilder::class)->build($pickup->refresh(), true, true);
        $this->assertSame([], array_diff($receipt->problems, ['store_tin_missing']));
        $this->assertSame(['10202001009000001', self::PACKAGING_MXIK, self::BOWL_MXIK], array_map(fn ($line) => $line->mxikCode, $receipt->lines));
        $this->assertSame('57000.00', Money::sum(...array_map(fn ($line) => $line->amount, $receipt->lines))->toString());

        // The bowl rule is not for delivery: only the bag; a dine-in order none
        $lines = [['variant_model' => $this->soup->fresh('product'), 'quantity' => \App\Support\Quantity::of('1')]];
        $delivery = app(PackagingRules::class)->forCheckout($this->branch, 'delivery', $lines);
        $this->assertSame(['Paket'], array_column($delivery['lines'], 'name'));
        $this->assertSame('1000.00', $delivery['amount']->toString());
        $this->assertSame([], app(PackagingRules::class)->forCheckout($this->branch, 'dine_in', $lines)['lines']);
    }

    public function test_without_the_module_no_packaging_and_an_order_keeps_the_lines_it_has(): void
    {
        $opened = $this->takeaway([[$this->soup, 1]])->assertCreated()->assertJsonPath('data.charges.packaging_amount', '4000.00');

        $this->branch->forceFill(['pos_settings' => array_merge($this->branch->pos_settings ?? [], ['modules' => ['packaging' => false]])])->save();
        $this->takeaway([[$this->soup, 1]])->assertCreated()->assertJsonPath('data.charges.packaging_amount', '0.00');

        // The first order keeps its bag and bowl (frozen as they were) when it changes
        $uuid = (string) $opened->json('data.uuid');
        $this->tillAs('POST', "/pos/orders/{$uuid}/rounds", ['version' => (int) $opened->json('data.version'), 'items' => [['variant_id' => $this->shashlik->uuid, 'quantity' => 1]]])
            ->assertOk()->assertJsonPath('data.charges.packaging_amount', '4000.00')->assertJsonPath('data.total_price', '47000.00');

        $this->tillAs('POST', "/pos/orders/{$uuid}/packaging/{$this->bag->id}/remove", ['version' => 2, 'reason' => 'x'])
            ->assertStatus(403)->assertJsonPath('data.reason', 'module_disabled');
        $this->assertSame([], $this->tillAs('GET', '/pos/bootstrap')->assertOk()->json('data.takeaway.packaging'));
    }

    public function test_the_owner_manages_the_rules(): void
    {
        $this->withBearer(null);
        $base = "/api/v1/owner/branches/{$this->branch->id}/packaging-rules";
        $this->actingAs($this->owner, 'sanctum')->getJson($base)->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Paket')->assertJsonPath('data.0.basis_label', 'Buyurtmaga');

        // An item rule names one category or one product
        $this->actingAs($this->owner, 'sanctum')->postJson($base, ['name' => 'Qopqoq', 'basis' => 'item', 'unit_price' => '500', 'order_types' => ['takeaway']])
            ->assertStatus(422)->assertJsonValidationErrors('category_id');
        $this->actingAs($this->owner, 'sanctum')->postJson($base, ['name' => 'Qopqoq', 'basis' => 'item', 'unit_price' => '500', 'order_types' => ['hall']])
            ->assertStatus(422)->assertJsonValidationErrors('order_types.0');
        $this->actingAs($this->owner, 'sanctum')->postJson($base, ['name' => 'Qopqoq', 'basis' => 'item', 'unit_price' => '500', 'order_types' => ['takeaway'], 'product_id' => $this->tea->product_id, 'mxik' => '123'])
            ->assertStatus(422)->assertJsonValidationErrors('mxik');

        $id = (int) $this->actingAs($this->owner, 'sanctum')->postJson($base, ['name' => 'Qopqoq', 'basis' => 'item', 'unit_price' => '500', 'quantity' => 1, 'order_types' => ['takeaway'], 'product_id' => $this->tea->product_id])
            ->assertCreated()->assertJsonPath('data.product_id', $this->tea->product_id)->assertJsonPath('data.unit_price', '500.00')->json('data.id');
        $this->assertTrue(AuditLog::where('action', 'settings.packaging_rule_created')->exists());

        $this->actingAs($this->owner, 'sanctum')->putJson("{$base}/{$id}", ['unit_price' => '700', 'is_active' => false])
            ->assertOk()->assertJsonPath('data.unit_price', '700.00')->assertJsonPath('data.is_active', false);
        $this->assertTrue(AuditLog::where('action', 'settings.packaging_rule_changed')->exists());

        // An inactive rule does not apply
        $this->app['auth']->forgetGuards();
        $this->takeaway([[$this->tea, 1]])->assertCreated()->assertJsonPath('data.charges.packaging_amount', '1000.00');

        $this->withBearer(null);
        $this->actingAs($this->owner, 'sanctum')->deleteJson("{$base}/{$id}")->assertOk()->assertJsonPath('data.id', $id);
        $this->assertNull(BranchPackagingRule::find($id));
        $this->assertTrue(AuditLog::where('action', 'settings.packaging_rule_deleted')->exists());

        // A choyxona branch has no packaging module
        $this->useProfile($this->branch, 'choyxona');
        $this->actingAs($this->owner, 'sanctum')->getJson($base)->assertStatus(403)->assertJsonPath('data.reason', 'module_disabled');
    }

    public function test_the_bootstrap_carries_the_rules_for_offline_pricing(): void
    {
        $section = $this->tillAs('GET', '/pos/bootstrap')->assertOk()->json('data.takeaway');
        $this->assertSame(['Paket', "Sho'rva idishi"], array_column($section['packaging'], 'name'));
        $this->assertSame(['takeaway', 'pickup'], $section['packaging'][1]['order_types']);
        $this->assertSame($this->soups->id, $section['packaging'][1]['category_id']);
    }

    /**
     * The fixed case pos_data's offline pricing parity test (offline_packaging_test.dart) mirrors: 2 soups (25 000, the
     * bowl's category) and a shashlik (18 000) taken away offline: a bag 1 000 and 2 bowls 6 000, 75 000 in all.
     */
    public function test_an_offline_takeaway_is_priced_as_the_till_priced_it(): void
    {
        $order = (string) \Illuminate\Support\Str::uuid();
        $bill = (string) \Illuminate\Support\Str::uuid();
        $response = $this->sync([
            $this->op('order.open', [
                'order' => $order,
                'local_number' => 'K1-007',
                'channel' => 'pos_takeaway',
                'items' => [$this->line($this->soup, '2'), $this->line($this->shashlik, '1')],
                'send' => true,
                'menu_taken_at' => now()->subHour()->toJSON(),
            ], null, 30),
            $this->op('bill.create', ['order' => $order, 'bill' => $bill, 'expected_total' => '75000'], null, 20),
        ])->assertOk();

        $this->assertSame(['applied', 'applied'], [$response->json('data.results.0.status'), $response->json('data.results.1.status')]);
        $this->assertNull($response->json('data.results.1.refs.flags'));
        $this->assertSame('75000.00', $response->json('data.results.1.refs.bill_total'));
        $model = Order::where('uuid', $response->json('data.results.0.refs.order_uuid'))->firstOrFail();
        $this->assertSame('75000.00', (string) $model->total_price);
        $this->assertSame([['Paket', 1, '1000.00'], ["Sho'rva idishi", 2, '6000.00']], array_map(fn (array $line) => [$line['name'], $line['quantity'], $line['amount']], $model->packaging_lines ?? []));
    }

    public function test_portions_count_a_piece_line_rounded_up_and_a_weighed_line_once(): void
    {
        $this->assertSame(2, PackagingRules::portions(\App\Support\Quantity::of('1.5'), false));
        $this->assertSame(3, PackagingRules::portions(\App\Support\Quantity::of('3'), false));
        $this->assertSame(1, PackagingRules::portions(\App\Support\Quantity::of('2.5'), true));
        $this->assertSame(0, PackagingRules::portions(\App\Support\Quantity::of('0'), false));
    }
}
