<?php

namespace Tests\Feature\PosFastFood;

use App\Domain\Pos\Takeaway\PackagingOrderType;
use App\Domain\Pos\Takeaway\PackagingRules;
use App\Models\BranchPackagingRule;
use App\Models\Category;
use App\Models\Order;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Owner decision 03.11.2026 (docs/POS_SPEC.md "Qadoq"): a drink inside a combo counts for the item-basis packaging rules
 * as its own dish — a cup per drink includes the combo's drinks (the parts' dishes, their pieces × the combo line's
 * quantity); the combo line itself still counts for a rule of the combo product or its category; nothing counts twice.
 * The fixed cases are the ones of pos/packages/pos_data/test/offline_packaging_test.dart ("combo parts"): the till
 * prices them the same offline.
 */
class ComboPackagingTest extends TestCase
{
    use ComboFixtures;
    use RefreshDatabase;

    private Category $drinks;

    private BranchPackagingRule $cup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCombo();
        $this->makeCombo();

        $this->drinks = Category::create(['name' => 'Ichimliklar', 'slug' => 'ichimlik-' . Str::random(5), 'is_active' => true]);
        foreach ([$this->pepsi, $this->fanta, $this->ayran] as $drink) {
            $drink->product->forceFill(['category_id' => $this->drinks->id])->save();
        }
        $rule = fn (array $attributes) => BranchPackagingRule::create($attributes + [
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'quantity' => 1, 'unit' => 'dona',
            'order_types' => ['takeaway', 'pickup', 'delivery'],
        ]);
        $rule(['name' => 'Paket', 'basis' => 'order', 'unit_price' => '1500', 'sort_order' => 1]);
        $this->cup = $rule(['name' => 'Stakan', 'basis' => 'item', 'category_id' => $this->drinks->id, 'unit_price' => '1000', 'sort_order' => 2]);
        $rule(['name' => 'Kombo qutisi', 'basis' => 'item', 'product_id' => $this->comboProduct->id, 'unit_price' => '2000', 'sort_order' => 3]);
    }

    public function test_the_fixed_cases_count_the_drinks_inside_combos(): void
    {
        $rules = app(PackagingRules::class);
        $dish = fn (?int $product, ?int $category, string $quantity, array $parts = [], bool $measured = false) => [
            'product_id' => $product, 'category_id' => $category, 'quantity' => Quantity::of($quantity), 'measured' => $measured,
            'parts' => array_map(fn (array $part) => [
                'product_id' => $part[0], 'category_id' => $part[1], 'quantity' => Quantity::of($part[2]), 'measured' => $part[3] ?? false,
            ], $parts),
        ];
        $view = fn (array $lines) => array_map(fn (array $line) => [$line['name'], $line['quantity'], $line['amount']], $lines);
        $combo = (int) $this->comboProduct->id;
        $drinks = (int) $this->drinks->id;

        // A: 2 combos (lavash, pepsi, a sauce) and a pepsi of its own: 2 + 1 cups, 2 combo boxes, one bag
        $a = $rules->linesFor((int) $this->branch->id, PackagingOrderType::TAKEAWAY, [
            $dish($combo, null, '2', [[(int) $this->lavash->product_id, null, '1'], [(int) $this->pepsi->product_id, $drinks, '1'], [(int) $this->hotSauce->product_id, null, '1']]),
            $dish((int) $this->pepsi->product_id, $drinks, '1'),
        ]);
        $this->assertSame([['Paket', 1, '1500.00'], ['Stakan', 3, '3000.00'], ['Kombo qutisi', 2, '4000.00']], $view($a));
        $this->assertSame('8500.00', PackagingRules::total($a)->toString());

        // B: 3 combos of 2 ayran each (6 cups) and a weighed drink part (once per combo line: 1 more); the cup taken off
        $b = $rules->linesFor((int) $this->branch->id, PackagingOrderType::TAKEAWAY, [
            $dish($combo, null, '3', [[(int) $this->ayran->product_id, $drinks, '2'], [(int) $this->fanta->product_id, $drinks, '1', true]]),
        ], [(int) $this->cup->id => "Mijoz o'z stakanini olib keldi"]);
        $this->assertSame([['Paket', 1, '1500.00'], ['Stakan', 7, '0.00'], ['Kombo qutisi', 3, '6000.00']], $view($b));
        $this->assertTrue($b[1]['removed']);

        // C: a combo whose own category is the drinks: the combo line counts for it, and so does its drink (two dishes)
        $c = $rules->linesFor((int) $this->branch->id, PackagingOrderType::TAKEAWAY, [
            $dish($combo, $drinks, '1', [[(int) $this->pepsi->product_id, $drinks, '1']]),
        ]);
        $this->assertSame([['Paket', 1, '1500.00'], ['Stakan', 2, '2000.00'], ['Kombo qutisi', 1, '2000.00']], $view($c));
    }

    public function test_a_counter_takeaway_with_combos_gets_a_cup_per_drink_inside_them(): void
    {
        $response = $this->counterOrder([
            $this->comboLine([], 2),
            ['variant_id' => $this->pepsi->uuid, 'quantity' => 1],
        ])->assertCreated();
        $order = Order::where('uuid', $response->json('data.uuid'))->firstOrFail();
        $this->assertSame(['Paket' => 1, 'Stakan' => 3, 'Kombo qutisi' => 2], array_column($order->packaging_lines ?? [], 'quantity', 'name'));
        $this->assertSame('8500.00', Money::of($order->packaging_amount)->toString());
        $this->assertSame('106500.00', Money::of($order->total_price)->toString());

        // The combo voided: its cups go with it (the pepsi of its own keeps one)
        $combo = $order->items()->whereNotNull('combo_parts')->firstOrFail();
        $this->tillAs('POST', "/pos/orders/{$order->uuid}/items/{$combo->id}/void", [
            'version' => (int) $order->refresh()->version, 'reason' => 'Mijoz voz kechdi', 'approval_token' => $this->managerApproval('orders.void_item'),
        ])->assertOk();
        $this->assertSame(['Paket' => 1, 'Stakan' => 1], array_column($order->refresh()->packaging_lines ?? [], 'quantity', 'name'));
    }

    private function managerApproval(string $permission): string
    {
        return (string) $this->tillAs('POST', '/pos/approvals', ['permission' => $permission, 'user_id' => $this->manager->id, 'pin' => self::MANAGER_PIN])
            ->assertCreated()->json('data.token');
    }

    public function test_a_telegram_pickup_with_a_combo_gets_the_cup_of_its_drink(): void
    {
        $uuid = $this->order([$this->comboLine([['option_id' => $this->option($this->ayran)]])])->assertCreated()->json('data.order_uuid');
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $this->assertSame(['Paket' => 1, 'Stakan' => 1, 'Kombo qutisi' => 1], array_column($order->packaging_lines ?? [], 'quantity', 'name'));
        $this->assertSame('4500.00', Money::of($order->packaging_amount)->toString());
    }
}
